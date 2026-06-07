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
        $defaultMode = ($permissions['edit'] || $permissions['review']) ? 'edit' : 'view';
        $mode = $this->resolveMode($forceMode, $defaultMode, $permissions);

        return $this->buildConfig(
            key: (string) $contract->document_key,
            title: $contract->number.'.docx',
            documentUrl: $this->internalRouteUrl('contract', $contract->id, 'document', $contract->document_key),
            callbackUrl: $this->internalRouteUrl('contract', $contract->id, 'callback', $contract->document_key),
            permissions: $permissions,
            mode: $mode,
            lang: $contract->language ?: 'ru',
            user: $user,
        );
    }

    public function templateEditorConfig(ContractTemplate $template, User $user, ?string $forceMode = null): array
    {
        $permissions = $this->permissionSet(edit: true, review: true, comment: true);
        $mode = $this->resolveMode($forceMode, 'edit', $permissions);

        return $this->buildConfig(
            key: (string) $template->document_key,
            title: $template->name.'.docx',
            documentUrl: $this->internalRouteUrl('template', $template->id, 'document', $template->document_key),
            callbackUrl: $this->internalRouteUrl('template', $template->id, 'callback', $template->document_key),
            permissions: $permissions,
            mode: $mode,
            lang: $template->language ?: 'ru',
            user: $user,
        );
    }

    public function orderEditorConfig(Order $order, User $user, ?string $forceMode = null): array
    {
        $permissions = $this->permissionSet(edit: true, review: true, comment: true);
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

    public function resolvePermissions(Contract $contract, User $user): array
    {
        if ($contract->status === Contract::STATUS_DRAFT && $contract->canBeEditedBy($user)) {
            return $this->permissionSet(edit: true, review: true, comment: true);
        }

        if ($contract->status === Contract::STATUS_IN_REVIEW && $contract->isCurrentApprover($user)) {
            return $this->permissionSet(edit: false, review: true, comment: true);
        }

        return $this->permissionSet(edit: false, review: false, comment: false);
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
                'lang' => $lang,
                'callbackUrl' => $callbackUrl,
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => $this->customization($mode),
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

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function resolveMode(?string $forceMode, string $default, array $permissions): string
    {
        $mode = in_array($forceMode, ['edit', 'view', 'review'], true)
            ? $forceMode
            : $default;

        if ($mode === 'edit' && ! ($permissions['edit'] ?? false)) {
            return ($permissions['review'] ?? false) ? 'review' : 'view';
        }

        return $mode;
    }

    /**
     * Customization block. trackChanges is set explicitly per mode so
     * OnlyOffice doesn't fall back to the user's saved preference (which
     * stuck ON from earlier sessions and forced the editor into the
     * Reviewing badge even when we opened in mode=edit).
     *
     * @return array<string, mixed>
     */
    private function customization(string $mode): array
    {
        $isReview = $mode === 'review';

        return [
            'forcesave' => true,
            'autosave' => true,
            'compactHeader' => false,
            'review' => [
                'trackChanges' => $isReview,
                'showReviewChanges' => $isReview,
                'reviewDisplay' => 'markup',
                'hoverMode' => true,
            ],
        ];
    }

    private function permissionSet(bool $edit, bool $review, bool $comment): array
    {
        return [
            'edit' => $edit,
            'review' => $review,
            'comment' => $comment,
            'download' => true,
            'print' => true,
            'fillForms' => $edit,
        ];
    }

    private function internalRouteUrl(string $subject, int $id, string $action, ?string $sharedKey): string
    {
        $prefix = match ($subject) {
            'template' => "/onlyoffice/template/{$id}/{$action}",
            'order' => "/onlyoffice/order/{$id}/{$action}",
            default => "/onlyoffice/{$id}/{$action}",
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
