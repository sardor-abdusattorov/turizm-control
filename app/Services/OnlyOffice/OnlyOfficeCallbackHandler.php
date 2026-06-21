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
        $status = (int) $request->input('status', 0);
        $url = $request->input('url');

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
                    'causer' => $this->resolveCauser($request),
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

    private function resolveCauser(Request $request): ?User
    {
        $userIds = $request->input('users', []);

        if (! is_array($userIds) || $userIds === []) {
            return null;
        }

        return User::find($userIds[0] ?? null);
    }
}
