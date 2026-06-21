<?php

namespace App\Services\OnlyOffice;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OnlyOfficeService
{
    private JwtSigner $signer;

    public function __construct()
    {
        $this->signer = new JwtSigner((string) config('onlyoffice.jwt_secret'));
    }

    public function apiScriptUrl(): string
    {
        return $this->trim(config('onlyoffice.public_url')).'/web-apps/apps/api/documents/api.js';
    }

    public function internalDownloadUrl(string $url): string
    {
        return str_replace(
            $this->trim(config('onlyoffice.public_url')),
            $this->trim(config('onlyoffice.internal_url')),
            $url,
        );
    }

    public function editorConfig(Contract $contract, User $user, ?string $forceMode = null): array
    {
        $permissions = $this->resolvePermissions($contract, $user);
        $defaultMode = $permissions['edit'] ? 'edit' : 'view';
        $mode = $this->resolveMode($forceMode, $defaultMode, $permissions);

        return $this->buildConfig(
            key: (string) $contract->document_key,
            title: $contract->number.'.docx',
            documentUrl: $this->internalRouteUrl('contract', $contract->id, 'document', $contract->document_key),
            callbackUrl: $this->internalRouteUrl('contract', $contract->id, 'callback', $contract->document_key),
            permissions: $permissions,
            mode: $mode,
            lang: self::editorLocale(),
            user: $user,
        );
    }

    /**
     * UI language for the editor — follows the panel's active locale, but
     * falls back to Russian because OnlyOffice has no Uzbek interface.
     */
    private static function editorLocale(): string
    {
        return app()->getLocale() === 'uz' ? 'ru' : app()->getLocale();
    }

    public function templateEditorConfig(ContractTemplate $template, User $user, ?string $forceMode = null): array
    {
        $permissions = $this->permissionSet(edit: true, review: false, comment: true, download: false, print: false);
        $mode = $this->resolveMode($forceMode, 'edit', $permissions);

        return $this->buildConfig(
            key: (string) $template->document_key,
            title: $template->name.'.docx',
            documentUrl: $this->internalRouteUrl('template', $template->id, 'document', $template->document_key),
            callbackUrl: $this->internalRouteUrl('template', $template->id, 'callback', $template->document_key),
            permissions: $permissions,
            mode: $mode,
            lang: self::editorLocale(),
            user: $user,
        );
    }

    public function orderEditorConfig(Order $order, User $user, ?string $forceMode = null): array
    {
        $permissions = $this->permissionSet(edit: true, review: false, comment: true, download: true, print: true);
        $mode = $this->resolveMode($forceMode, 'edit', $permissions);
        $extension = $order->extension() ?: 'docx';

        return $this->buildConfig(
            key: (string) $order->document_key,
            title: basename((string) $order->file_path),
            documentUrl: $this->internalRouteUrl('order', $order->id, 'document', $order->document_key),
            callbackUrl: $this->internalRouteUrl('order', $order->id, 'callback', $order->document_key),
            permissions: $permissions,
            mode: $mode,
            lang: app()->getLocale() ?: 'ru',
            user: $user,
            fileType: $extension,
            documentType: $this->documentTypeForExtension($extension),
        );
    }

    public function convertToPdf(Contract $contract): ?string
    {
        $payload = [
            'async' => false,
            'filetype' => 'docx',
            'outputtype' => 'pdf',
            'key' => Str::random(20),
            'title' => $contract->number.'.docx',
            'url' => $this->internalRouteUrl('contract', $contract->id, 'document', $contract->document_key),
        ];

        $payload['token'] = $this->signer->encode($payload);

        $fileUrl = Http::asJson()
            ->acceptJson()
            ->post($this->converterUrl(), $payload)
            ->json('fileUrl');

        return is_string($fileUrl) ? $fileUrl : null;
    }

    public function verifyCallback(array $body, ?string $header): ?array
    {
        $token = null;

        if ($header && str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
        } elseif (isset($body['token']) && is_string($body['token'])) {
            $token = $body['token'];
        }

        if (! $token) {
            return null;
        }

        $decoded = $this->signer->decode($token);

        if ($decoded === null) {
            return null;
        }

        return $decoded['payload'] ?? $decoded;
    }

    /**
     * Whether JWT validation is configured. When the secret is empty (only
     * happens in local dev / tests with no editor wired up) the callback
     * handler runs in degraded mode and trusts the raw body. In production
     * the secret MUST be set so every callback is signed and verified.
     */
    public function isJwtConfigured(): bool
    {
        return $this->jwtSecret() !== '';
    }

    public function jwtSecret(): string
    {
        return (string) config('onlyoffice.jwt_secret');
    }

    public function resolvePermissions(Contract $contract, User $user): array
    {
        $canExport = $this->canExportContract($contract, $user);

        // Anyone allowed to edit the contract — the author while it is a
        // draft / in review / rejected, plus the current approver — edits the
        // document directly. Track changes / review mode is never used.
        if ($contract->canBeEditedBy($user)) {
            return $this->permissionSet(edit: true, review: false, comment: true, download: $canExport, print: $canExport);
        }

        return $this->permissionSet(edit: false, review: false, comment: false, download: $canExport, print: $canExport);
    }

    private function canExportContract(Contract $contract, User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $contract->status === Contract::STATUS_APPROVED;
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function buildConfig(
        string $key,
        string $title,
        string $documentUrl,
        string $callbackUrl,
        array $permissions,
        string $mode,
        string $lang,
        User $user,
        string $fileType = 'docx',
        string $documentType = 'word',
    ): array {
        $config = [
            'documentType' => $documentType,
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'document' => [
                'fileType' => $fileType,
                'key' => $key,
                'title' => $title,
                'url' => $documentUrl,
                'permissions' => $permissions,
            ],
            'editorConfig' => [
                'mode' => $mode,
                'lang' => $this->normaliseLang($lang),
                'callbackUrl' => $callbackUrl,
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => $this->customization(),
            ],
        ];

        $config['token'] = $this->signer->encode($config);

        return $config;
    }

    private function documentTypeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'xls', 'xlsx', 'csv' => 'cell',
            'ppt', 'pptx' => 'slide',
            'pdf' => 'pdf',
            default => 'word',
        };
    }

    private function normaliseLang(string $lang): string
    {
        $supported = [
            'af', 'ar', 'az', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en',
            'es', 'eu', 'fi', 'fr', 'gl', 'hu', 'hy', 'id', 'it', 'ja',
            'ka', 'kk', 'ko', 'lo', 'lv', 'ms', 'nb', 'nl', 'pl', 'pt',
            'ro', 'ru', 'sk', 'sl', 'sr', 'sv', 'tr', 'uk', 'vi', 'zh',
        ];

        if (in_array($lang, $supported, true)) {
            return $lang;
        }

        return match ($lang) {
            'uz' => 'ru',
            default => 'en',
        };
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function resolveMode(?string $forceMode, string $default, array $permissions): string
    {
        $mode = in_array($forceMode, ['edit', 'view'], true)
            ? $forceMode
            : $default;

        if ($mode === 'edit' && ! ($permissions['edit'] ?? false)) {
            return 'view';
        }

        return $mode;
    }

    /**
     * Customization block. Track changes / review markup is force-disabled —
     * the document is always edited directly, never via suggestions — and set
     * explicitly so OnlyOffice can't fall back to a per-user preference that
     * stuck ON from an earlier session.
     *
     * @return array<string, mixed>
     */
    private function customization(): array
    {
        return [
            'forcesave' => true,
            'autosave' => true,
            'compactHeader' => false,
            'plugins' => false,
            'help' => false,
            'about' => false,
            'feedback' => ['visible' => false],
            'review' => [
                'trackChanges' => false,
                'showReviewChanges' => false,
                'reviewDisplay' => 'markup',
                'hoverMode' => true,
            ],
        ];
    }

    private function permissionSet(
        bool $edit,
        bool $review,
        bool $comment,
        bool $download = true,
        bool $print = true,
    ): array {
        return [
            'edit' => $edit,
            'review' => $review,
            'comment' => $comment,
            'download' => $download,
            'print' => $print,
            'fillForms' => $edit,
        ];
    }

    private function internalRouteUrl(string $subject, int $id, string $action, ?string $sharedKey): string
    {
        // 'document' fetches the docx, 'callback' (alias for save-callback) is
        // the post-save webhook OnlyOffice hits when the user finishes editing.
        $path = $action === 'callback' ? 'save-callback' : $action;
        $prefix = match ($subject) {
            'template' => "/contract-templates/{$id}/{$path}",
            'order' => "/orders/{$id}/{$path}",
            default => "/contracts/{$id}/{$path}",
        };

        return $this->callbackHost().$prefix.'?shared_key='.$sharedKey;
    }

    private function callbackHost(): string
    {
        return $this->trim(config('onlyoffice.callback_host'));
    }

    private function converterUrl(): string
    {
        return $this->trim(config('onlyoffice.internal_url')).'/converter';
    }

    private function trim(mixed $url): string
    {
        return rtrim((string) $url, '/');
    }
}
