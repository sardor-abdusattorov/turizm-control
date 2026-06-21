<?php

namespace App\Services\Telegram;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Renders every bot screen as ['text' => ..., 'keyboard' => ...]. Pure
 * presentation: no IO, no state, no decisions about what to send — those
 * live in TelegramBot. Easier to unit-test and to localise.
 */
class BotMenuBuilder
{
    public const PAGE_SIZE = 5;

    public function __construct(public BotRoleResolver $roles) {}

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function localePicker(): array
    {
        return [
            'text' => __('app.telegram.choose_language'),
            'keyboard' => [
                [
                    $this->cbBtn('🇷🇺 Русский', 'lang:ru'),
                    $this->cbBtn('🇺🇿 Oʻzbekcha', 'lang:uz'),
                ],
                [$this->cbBtn('🇬🇧 English', 'lang:en')],
            ],
        ];
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function mainMenu(User $user): array
    {
        $awaiting = $this->roles->awaitingMyDecisionCount($user);
        $myContracts = $this->roles->myContractsCount($user);
        $pendingDirector = $this->roles->pendingDirectorHandoffCount($user);

        $rows = [];

        if ($awaiting > 0) {
            $rows[] = [$this->cbBtn(
                "⏳ {$this->labelAwaiting($user)} · {$awaiting}",
                'aw:1',
            )];
        }

        if ($pendingDirector > 0) {
            $rows[] = [$this->cbBtn(
                '📨 '.__('app.telegram.menu_send_to_director')." · {$pendingDirector}",
                'mc:1',
            )];
        }

        if ($myContracts > 0) {
            $rows[] = [$this->cbBtn(
                '📑 '.__('app.telegram.menu_my_contracts')." · {$myContracts}",
                'mc:1',
            )];
        }

        $rows[] = [
            $this->cbBtn('🌐 '.__('app.telegram.menu_language'), 'lang'),
            $this->urlBtn('📋 '.__('app.telegram.menu_open_in_system'), $this->panelUrl()),
        ];

        return [
            'text' => $this->headline(__('app.telegram.menu_title'))
                .__('app.telegram.menu_hint'),
            'keyboard' => $rows,
        ];
    }

    /**
     * "Awaiting my decision" list.
     *
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    public function awaitingList(User $user, int $page): array
    {
        $query = Contract::query()
            ->whereHas('approvers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', ContractApprover::STATUS_PENDING)
            )
            ->orderByDesc('id');

        return $this->renderContractList(
            query: $query,
            page: $page,
            title: __('app.telegram.list_awaiting_title'),
            emptyMessage: __('app.telegram.list_awaiting_empty'),
            callbackPrefix: 'aw',
        );
    }

    /**
     * Manager's own contracts.
     *
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    public function myContractsList(User $user, int $page): array
    {
        $query = Contract::query()
            ->where('responsible_id', $user->id)
            ->orderByDesc('id');

        return $this->renderContractList(
            query: $query,
            page: $page,
            title: __('app.telegram.list_mine_title'),
            emptyMessage: __('app.telegram.list_mine_empty'),
            callbackPrefix: 'mc',
        );
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function contractCard(Contract $contract, User $viewer): array
    {
        $contract->loadMissing(['approvers.user', 'contact', 'currency', 'responsible']);

        $lines = [
            $this->headline("№ {$contract->number}"),
        ];

        if ($contract->title) {
            $lines[] = "«{$this->htmlEscape($contract->title)}»";
            $lines[] = '';
        }

        $lines[] = __('app.telegram.field_status').': '.$this->statusBadge($contract);
        $lines[] = __('app.telegram.field_amount').': '
            .number_format((float) $contract->amount, 0, '.', ' ')
            .' '.$this->htmlEscape($contract->currency?->short_name ?? '');

        if ($contract->contact?->name) {
            $lines[] = __('app.telegram.field_contact').': '.$this->htmlEscape($contract->contact->name);
        }

        $lines[] = __('app.telegram.field_responsible').': '.$this->htmlEscape($contract->responsible?->name ?? '—');

        $chainLines = $this->renderChain($contract);

        if ($chainLines !== []) {
            $lines[] = '';
            $lines[] = '<b>'.__('app.telegram.field_chain').':</b>';

            foreach ($chainLines as $chainLine) {
                $lines[] = $chainLine;
            }
        }

        $keyboard = [];

        if ($contract->canBeApprovedBy($viewer)) {
            $keyboard[] = [
                $this->cbBtn('✅ '.__('app.action.approve'), "approve:{$contract->id}"),
                $this->cbBtn('❌ '.__('app.action.reject'), "reject:{$contract->id}"),
            ];
        }

        if ($contract->canBeSentToDirectorBy($viewer)) {
            $keyboard[] = [$this->cbBtn(
                '📨 '.__('app.action.send_to_director'),
                "sdir:{$contract->id}",
            )];
        }

        $keyboard[] = [$this->urlBtn(
            '📋 '.__('app.telegram.btn_open_in_system'),
            $this->contractUrl($contract),
        )];
        $keyboard[] = [$this->cbBtn('‹ '.__('app.telegram.btn_back'), 'menu')];

        return [
            'text' => implode("\n", $lines),
            'keyboard' => $keyboard,
        ];
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function notificationApprovalRequested(Contract $contract): array
    {
        $contract->loadMissing(['responsible', 'currency']);

        $lines = [
            $this->headline('📨 '.__('app.notification.approval_requested.title')),
            __('app.notification.approval_requested.body', ['number' => $contract->number]),
            '',
            __('app.telegram.field_amount').': '
                .number_format((float) $contract->amount, 0, '.', ' ')
                .' '.$this->htmlEscape($contract->currency?->short_name ?? ''),
            __('app.telegram.field_responsible').': '.$this->htmlEscape($contract->responsible?->name ?? '—'),
        ];

        return [
            'text' => implode("\n", $lines),
            'keyboard' => [
                [
                    $this->cbBtn('✅ '.__('app.action.approve'), "approve:{$contract->id}"),
                    $this->cbBtn('❌ '.__('app.action.reject'), "reject:{$contract->id}"),
                ],
                [$this->urlBtn('📋 '.__('app.telegram.btn_open_in_system'), $this->contractUrl($contract))],
            ],
        ];
    }

    public function rejectPromptText(Contract $contract): string
    {
        return $this->headline('❌ '.__('app.telegram.reject_prompt_title'))
            .__('app.telegram.reject_prompt_body', ['number' => $contract->number]);
    }

    /** @return array<int, array<int, array<string, string>>> */
    public function rejectPromptKeyboard(): array
    {
        return [[$this->cbBtn('✖ '.__('app.action.cancel'), 'cancel')]];
    }

    public function decisionStamp(Contract $contract, string $decision, ?string $comment = null): string
    {
        $title = $decision === 'approve'
            ? '✅ '.__('app.telegram.decision_approved')
            : '❌ '.__('app.telegram.decision_rejected');

        $lines = [
            $this->headline($title),
            "№ {$contract->number}",
            now()->translatedFormat('d M Y H:i'),
        ];

        if ($comment !== null && $comment !== '') {
            $lines[] = '';
            $lines[] = '<i>'.$this->htmlEscape($comment).'</i>';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Builder<Contract>  $query
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    private function renderContractList($query, int $page, string $title, string $emptyMessage, string $callbackPrefix): array
    {
        $page = max(1, $page);
        $total = (clone $query)->count();

        if ($total === 0) {
            return [
                'text' => $this->headline($title).$emptyMessage,
                'keyboard' => [[$this->cbBtn('‹ '.__('app.telegram.btn_back'), 'menu')]],
            ];
        }

        $pages = (int) ceil($total / self::PAGE_SIZE);
        $page = min($page, $pages);

        $rows = (clone $query)
            ->forPage($page, self::PAGE_SIZE)
            ->get();

        $lines = [
            $this->headline($title),
            __('app.telegram.list_total', ['count' => $total]),
        ];

        $keyboard = [];

        foreach ($rows as $contract) {
            $keyboard[] = [$this->cbBtn(
                "№ {$contract->number} · ".$this->shortStatus($contract),
                "view:{$contract->id}",
            )];
        }

        if ($pages > 1) {
            $nav = [];

            if ($page > 1) {
                $nav[] = $this->cbBtn('‹ '.__('app.telegram.btn_prev'), "{$callbackPrefix}:".($page - 1));
            }

            $nav[] = $this->cbBtn(__('app.telegram.page_of', ['page' => $page, 'total' => $pages]), 'noop');

            if ($page < $pages) {
                $nav[] = $this->cbBtn(__('app.telegram.btn_next').' ›', "{$callbackPrefix}:".($page + 1));
            }

            $keyboard[] = $nav;
        }

        $keyboard[] = [$this->cbBtn('‹ '.__('app.telegram.btn_main_menu'), 'menu')];

        return [
            'text' => implode("\n", $lines),
            'keyboard' => $keyboard,
        ];
    }

    /** @return array<int, string> */
    private function renderChain(Contract $contract): array
    {
        $rows = $contract->approvers
            ->filter(fn (ContractApprover $a) => ! in_array($a->status, [
                ContractApprover::STATUS_INVALIDATED,
                ContractApprover::STATUS_SKIPPED,
            ], true))
            ->sortBy('order')
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->map(function (ContractApprover $a) {
            $icon = match ($a->status) {
                ContractApprover::STATUS_APPROVED => '✅',
                ContractApprover::STATUS_REJECTED => '❌',
                ContractApprover::STATUS_PENDING => '⏳',
                default => '◻️',
            };

            $name = $this->htmlEscape($a->user?->name ?? '—');
            $dept = $a->user?->department?->name
                ? ' · '.$this->htmlEscape($a->user->department->name)
                : '';

            return "  {$icon} {$name}{$dept}";
        })->all();
    }

    private function statusBadge(Contract $contract): string
    {
        return match ($contract->status) {
            Contract::STATUS_DRAFT => '⚪ '.__('app.contract.status.draft'),
            Contract::STATUS_IN_REVIEW => '🟦 '.__('app.contract.status.in_review'),
            Contract::STATUS_PENDING_DIRECTOR => '🟧 '.__('app.contract.status.pending_director'),
            Contract::STATUS_IN_REVIEW_DIRECTOR => '🟪 '.__('app.contract.status.in_review_director'),
            Contract::STATUS_APPROVED => '🟩 '.__('app.contract.status.approved'),
            Contract::STATUS_REJECTED => '🟥 '.__('app.contract.status.rejected'),
            default => (string) $contract->status->label(),
        };
    }

    private function shortStatus(Contract $contract): string
    {
        return $this->statusBadge($contract);
    }

    private function labelAwaiting(User $user): string
    {
        if ($this->roles->isDirector($user)) {
            return __('app.telegram.menu_awaiting_director');
        }

        if ($this->roles->isLawyer($user)) {
            return __('app.telegram.menu_awaiting_legal');
        }

        if ($this->roles->isAccountant($user)) {
            return __('app.telegram.menu_awaiting_accounting');
        }

        return __('app.telegram.menu_awaiting_generic');
    }

    private function headline(string $title): string
    {
        return "<b>{$this->htmlEscape($title)}</b>\n\n";
    }

    private function htmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function contractUrl(Contract $contract): string
    {
        return ContractResource::getUrl('view', ['record' => $contract->id]);
    }

    private function panelUrl(): string
    {
        return config('app.url').'/admin';
    }

    /** @return array<string, string> */
    private function cbBtn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    /** @return array<string, string> */
    private function urlBtn(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }
}
