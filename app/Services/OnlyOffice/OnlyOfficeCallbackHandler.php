<?php

namespace App\Services\OnlyOffice;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MrAdder\FilamentLogger\Facades\FilamentLogger;

class OnlyOfficeCallbackHandler
{
    public function __construct(public OnlyOfficeService $service) {}

    /**
     * Handle a /{subject}s/{id}/save-callback request from the editor server.
     *
     * @param  Closure(string $body): void  $persist  Writes the docx bytes
     * @param  Closure(list<int> $editorIds, bool $contentChanged): void  $onFinalSave  Runs after status=2 — the session ended (rotate key, reinvalidate, etc.)
     * @param  array<string, mixed>  $logProperties  Extra properties to merge into the activity log entry
     * @param  Closure(list<int> $editorIds, bool $contentChanged): void|null  $onForcesave  Runs after status=6 — the document was persisted mid-session (reinvalidate, but do NOT rotate the key)
     * @param  Closure(string $body): bool|null  $hasContentChanged  Decides, before the new bytes overwrite the old, whether the document actually changed (defaults to "always changed" when omitted)
     */
    public function handle(
        Request $request,
        Model $subject,
        string $savedEvent,
        string $forcesaveEvent,
        string $logDescription,
        Closure $persist,
        Closure $onFinalSave,
        array $logProperties = [],
        ?Closure $onForcesave = null,
        ?Closure $hasContentChanged = null,
    ): array {
        $logEvent = $savedEvent;
        $payload = $this->verifiedPayload($request);
        $status = (int) ($payload['status'] ?? 0);
        $url = $payload['url'] ?? null;

        if (! in_array($status, [2, 6], true) || ! is_string($url)) {
            return ['error' => 0];
        }

        try {
            $body = Http::timeout(60)
                ->get($this->service->internalDownloadUrl($url))
                ->body();

            // Decide whether the document actually changed BEFORE the new bytes
            // overwrite the old ones. This is what separates a real edit from a
            // no-op "save" the editor emits just for opening and closing the
            // document — only a real change may reset the approval chain.
            $changed = $hasContentChanged === null ? true : (bool) $hasContentChanged($body);

            $persist($body);

            $editorIds = $this->resolveEditorIds($payload);
            $causer = $editorIds !== [] ? User::find($editorIds[0]) : null;

            // Hand the editor identities and the change flag to the hooks so
            // they can tell an author's mid-flow change (resets the chain) from
            // an approver's solo review tweak (keeps the contract in review),
            // and skip both when nothing actually changed. Passing every
            // editor — not just the first — keeps the decision correct when
            // several people co-edit the doc.
            if ($status === 2) {
                // Session ended: rotate the key and (if changed) reinvalidate.
                $onFinalSave($editorIds, $changed);
            } elseif ($status === 6 && $onForcesave !== null) {
                // Forcesave: the user hit save or the editor autosaved their
                // changes mid-session, so the edited bytes are already on disk
                // while the session is still open. Reinvalidate now instead of
                // waiting for status 2 (which, once a forcesave has flushed the
                // changes, often never carries them). The key is deliberately
                // NOT rotated here — that would break the live editing session.
                $onForcesave($editorIds, $changed);
            }

            Log::info($logEvent, [
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'status' => $status,
                'bytes' => strlen($body),
            ]);

            FilamentLogger::log(
                event: $status === 2 ? $savedEvent : $forcesaveEvent,
                description: $logDescription,
                options: [
                    'logName' => 'Document',
                    'subject' => $subject,
                    'causer' => $causer,
                    'properties' => array_merge(
                        ['status' => $status, 'bytes' => strlen($body)],
                        $logProperties,
                    ),
                ],
            );

            return ['error' => 0];
        } catch (\Throwable $e) {
            Log::error($logEvent.' failed', [
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'error' => $e->getMessage(),
            ]);

            return ['error' => 1];
        }
    }

    /**
     * Verify the OnlyOffice JWT and return the trusted payload (status / url
     * / users come from there, not from the raw body — the raw body is
     * untrusted). When the JWT secret is not configured we run in degraded
     * mode and fall back to the raw body so local dev without the editor
     * still works; production must set ONLYOFFICE_JWT_SECRET.
     *
     * @return array<string, mixed>
     */
    private function verifiedPayload(Request $request): array
    {
        if (! $this->service->isJwtConfigured()) {
            return $request->all();
        }

        $payload = $this->service->verifyCallback(
            $request->all(),
            $request->header('Authorization'),
        );

        abort_if($payload === null, 403, 'OnlyOffice callback rejected: missing or invalid JWT.');

        return $payload;
    }

    public function ensureSharedKey(Request $request, ?string $expected): void
    {
        $provided = (string) $request->query('shared_key', '');

        abort_unless(
            $provided !== '' && hash_equals((string) $expected, $provided),
            403,
        );
    }

    public function persistToDisk(string $disk, string $path): Closure
    {
        return fn (string $body) => Storage::disk($disk)->put($path, $body);
    }

    /**
     * The ids of every user OnlyOffice reports as having edited the document
     * during the session being saved. Normalised to a unique list of ints.
     *
     * @param  array<string, mixed>  $payload  verified JWT payload (trusted)
     * @return list<int>
     */
    private function resolveEditorIds(array $payload): array
    {
        $userIds = $payload['users'] ?? [];

        if (! is_array($userIds)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $userIds)));
    }
}
