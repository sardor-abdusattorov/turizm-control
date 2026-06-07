<?php

namespace App\Services\OnlyOffice;

use App\Models\Contract;
use App\Models\ContractTemplate;
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

    public function editorConfig(Contract $contract, User $user): array
    {
        $permissions = $this->resolvePermissions($contract, $user);
        $mode = ($permissions['edit'] || $permissions['review']) ? 'edit' : 'view';

        $config = [
            'documentType' => 'word',
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'document' => [
                'fileType' => 'docx',
                'key' => (string) $contract->document_key,
                'title' => $contract->number.'.docx',
                'url' => $this->documentUrl($contract),
                'permissions' => $permissions,
            ],
            'editorConfig' => [
                'mode' => $mode,
                'lang' => $contract->language ?: 'ru',
                'callbackUrl' => $this->callbackUrl($contract),
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => $this->reviewCustomization(),
            ],
        ];

        $config['token'] = $this->signer->encode($config);

        return $config;
    }

    public function templateEditorConfig(ContractTemplate $template, User $user): array
    {
        $config = [
            'documentType' => 'word',
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'document' => [
                'fileType' => 'docx',
                'key' => (string) $template->document_key,
                'title' => $template->name.'.docx',
                'url' => $this->templateDocumentUrl($template),
                'permissions' => $this->permissionSet(edit: true, review: true, comment: true),
            ],
            'editorConfig' => [
                'mode' => 'edit',
                'lang' => $template->language ?: 'ru',
                'callbackUrl' => $this->templateCallbackUrl($template),
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => $this->reviewCustomization(),
            ],
        ];

        $config['token'] = $this->signer->encode($config);

        return $config;
    }

    /**
     * Default customization block — track-changes ON for everyone, with the
     * review pane open so editors see who changed what without flipping
     * any switches first.
     *
     * @return array<string, mixed>
     */
    private function reviewCustomization(): array
    {
        return [
            'forcesave' => true,
            'autosave' => true,
            'compactHeader' => false,
            'review' => [
                'trackChanges' => true,
                'showReviewChanges' => true,
                'reviewDisplay' => 'markup',
                'hoverMode' => true,
            ],
        ];
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

    public function convertToPdf(Contract $contract): ?string
    {
        $payload = [
            'async' => false,
            'filetype' => 'docx',
            'outputtype' => 'pdf',
            'key' => Str::random(20),
            'title' => $contract->number.'.docx',
            'url' => $this->documentUrl($contract),
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

    private function documentUrl(Contract $contract): string
    {
        return $this->callbackHost()."/onlyoffice/{$contract->id}/document?shared_key={$contract->document_key}";
    }

    private function callbackUrl(Contract $contract): string
    {
        return $this->callbackHost()."/onlyoffice/{$contract->id}/callback?shared_key={$contract->document_key}";
    }

    private function templateDocumentUrl(ContractTemplate $template): string
    {
        return $this->callbackHost()."/onlyoffice/template/{$template->id}/document?shared_key={$template->document_key}";
    }

    private function templateCallbackUrl(ContractTemplate $template): string
    {
        return $this->callbackHost()."/onlyoffice/template/{$template->id}/callback?shared_key={$template->document_key}";
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
