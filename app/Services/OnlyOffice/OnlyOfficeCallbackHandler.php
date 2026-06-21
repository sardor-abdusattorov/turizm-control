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
     * @param  Closure(): void  $onFinalSave  Runs after status=2 (rotate key, refresh model, etc.)
     * @param  array<string, mixed>  $logProperties  Extra properties to merge into the activity log entry
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

            $persist($body);

            if ($status === 2) {
                $onFinalSave();
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
                    'causer' => $this->resolveCauser($payload),
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
     * @param  array<string, mixed>  $payload  verified JWT payload (trusted)
     */
    private function resolveCauser(array $payload): ?User
    {
        $userIds = $payload['users'] ?? [];

        if (! is_array($userIds) || $userIds === []) {
            return null;
        }

        return User::find($userIds[0] ?? null);
    }
}
