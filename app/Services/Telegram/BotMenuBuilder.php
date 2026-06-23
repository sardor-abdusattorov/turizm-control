<?php

namespace App\Services\Telegram;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Filament\Facades\Filament;
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
            ],
        ];
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function mainMenu(User $user): array
    {
        $awaiting = $this->roles->awaitingMyDecisionCount($user);
        $myContracts = $this->roles->myContractsCount($user);
        $pendingDirector = $this->roles->pendingDirectorHandoffCount($user);
        $history = $this->roles->historyCount($user);
        $canSeeAll = $this->roles->canSeeAllContracts($user);

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

        if ($history > 0) {
            $rows[] = [$this->cbBtn(
                '📜 '.__('app.telegram.menu_history')." · {$history}",
                'hist:1',
            )];
        }

        if ($canSeeAll) {
            $rows[] = [$this->cbBtn(
                '🔎 '.__('app.telegram.menu_all_contracts')." · {$this->roles->allContractsCount($user)}",
                'all:1',
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
     * Contracts the user already decided on (approved or rejected),
     * sorted by their action time so the most recent verdict is on top.
     *
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    public function historyList(User $user, int $page): array
    {
        $query = Contract::query()
            ->whereHas('approvers', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    ContractApprover::STATUS_APPROVED,
                    ContractApprover::STATUS_REJECTED,
                ])
            )
            ->orderByDesc(
                ContractApprover::query()
                    ->select('acted_at')
                    ->whereColumn('contract_approvers.contract_id', 'contracts.id')
                    ->where('user_id', $user->id)
                    ->whereIn('status', [
                        ContractApprover::STATUS_APPROVED,
                        ContractApprover::STATUS_REJECTED,
                    ])
                    ->orderByDesc('acted_at')
                    ->limit(1)
            );

        return $this->renderContractList(
            query: $query,
            page: $page,
            title: __('app.telegram.list_history_title'),
            emptyMessage: __('app.telegram.list_history_empty'),
            callbackPrefix: 'hist',
        );
    }

    /**
     * All contracts visible system-wide — only super_admin / oversight
     * roles see this entry from the main menu.
     *
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    public function allContractsList(User $user, int $page): array
    {
        $query = Contract::query()->visibleTo($user)->orderByDesc('id');

        return $this->renderContractList(
            query: $query,
            page: $page,
            title: __('app.telegram.list_all_title'),
            emptyMessage: __('app.telegram.list_all_empty'),
            callbackPrefix: 'all',
        );
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
            '<b>№ '.$this->htmlEscape((string) $contract->number).'</b>',
        ];

        if ($contract->title) {
            $lines[] = '';
            $lines[] = '<b>«'.$this->htmlEscape($contract->title).'»</b>';
        }

        $lines[] = '';
        $lines[] = __('app.telegram.field_status').': '.$this->statusBadge($contract);
        $lines[] = __('app.telegram.field_amount').': <b>'.$this->formatAmount($contract).'</b>';

        if ($contract->contact?->name) {
            $lines[] = __('app.telegram.field_contact').': '.$this->htmlEscape($contract->contact->name);
        }

        $lines[] = __('app.telegram.field_responsible').': '.$this->htmlEscape($contract->responsible?->name ?? '—');

        $chainLines = $this->renderChain($contract);

        if ($chainLines !== []) {
            $lines[] = '';
            $lines[] = '<b>'.__('app.telegram.field_chain').'</b>';
            $lines[] = '<blockquote>'.implode("\n", $chainLines).'</blockquote>';
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
            __('app.notification.approval_requested.body', [
                'number' => $contract->number,
                'sender' => $this->htmlEscape($contract->responsible?->name ?? '—'),
            ]),
            '',
            __('app.telegram.field_amount').': '.$this->formatAmount($contract),
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
            ->with(['currency', 'contact', 'responsible'])
            ->forPage($page, self::PAGE_SIZE)
            ->get()
            ->values();

        $entries = [];
        $indexButtons = [];

        foreach ($rows as $i => $contract) {
            $position = $i + 1;
            $entries[] = $this->contractListEntry($contract, $position);
            $indexButtons[] = $this->cbBtn($this->indexEmoji($position), "view:{$contract->id}");
        }

        $body = '<b>'.$this->htmlEscape($title)."</b>\n"
            .__('app.telegram.list_total', ['count' => $total])."\n\n"
            .implode("\n\n", $entries);

        $keyboard = array_chunk($indexButtons, self::PAGE_SIZE);

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
            'text' => $body,
            'keyboard' => $keyboard,
        ];
    }

    /**
     * One compact-but-informative block per contract in a list: number +
     * status on the first line, the title, then amount / responsible /
     * counterparty so the row is meaningful without opening the card.
     */
    private function contractListEntry(Contract $contract, int $position): string
    {
        $lines = [
            "<b>{$position}. № {$this->htmlEscape((string) $contract->number)}</b>  ".$this->statusBadge($contract),
        ];

        if ($contract->title) {
            $lines[] = '<i>«'.$this->htmlEscape($this->truncate($contract->title, 56)).'»</i>';
        }

        $meta = '💰 <b>'.$this->formatAmount($contract).'</b>';

        if ($contract->responsible?->name) {
            $meta .= '   👤 '.$this->htmlEscape($contract->responsible->name);
        }

        $lines[] = $meta;

        if ($contract->contact?->name) {
            $lines[] = '🏢 '.$this->htmlEscape($contract->contact->name);
        }

        return '<blockquote>'.implode("\n", $lines).'</blockquote>';
    }

    private function formatAmount(Contract $contract): string
    {
        return trim(
            number_format((float) $contract->amount, 0, '.', ' ')
            .' '.$this->htmlEscape($contract->currency?->short_name ?? '')
        );
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit
            ? rtrim(mb_substr($value, 0, $limit - 1)).'…'
            : $value;
    }

    private function indexEmoji(int $position): string
    {
        return ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣'][$position - 1] ?? (string) $position;
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

            return "{$icon} {$name}{$dept}";
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
        // Telegram's HTML mode wants ONLY <, > and & escaped. Encoding
        // quotes (ENT_QUOTES) turned "yoʻli" into "yo&apos;li" in the
        // chat — the apostrophe entity is not in Telegram's accepted
        // list, so it leaked through unescaped.
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    private function contractUrl(Contract $contract): string
    {
        return ContractResource::getUrl('view', ['record' => $contract->id]);
    }

    private function panelUrl(): string
    {
        // The Filament panel is mounted on the site root in this project
        // (AdminPanelProvider::path('')), so don't slap an /admin suffix on
        // top of it. Filament::getUrl() resolves the panel's actual path,
        // so this stays correct if the path ever changes.
        return Filament::getUrl() ?: config('app.url');
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
