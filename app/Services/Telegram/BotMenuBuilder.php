<?php

namespace App\Services\Telegram;

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\User;
use App\Support\TelegramText;
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

    public function __construct(
        public BotRoleResolver $roles,
        public BotContractQueries $queries,
        public BotRequisitionQueries $requisitions,
        public BotProjectQueries $projects,
    ) {}

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

        $rqAwaiting = $this->roles->requisitionsAwaitingCount($user);
        $rqMine = $this->roles->myRequisitionsCount($user);

        if ($rqAwaiting > 0) {
            $rows[] = [$this->cbBtn(
                '📥 '.__('app.telegram.menu_rq_awaiting')." · {$rqAwaiting}",
                'rq:1',
            )];
        }

        if ($rqMine > 0) {
            $rows[] = [$this->cbBtn(
                '🧾 '.__('app.telegram.menu_rq_mine')." · {$rqMine}",
                'rqm:1',
            )];
        }

        if (($rqHistory = $this->roles->requisitionHistoryCount($user)) > 0) {
            $rows[] = [$this->cbBtn(
                '🗂 '.__('app.telegram.menu_rq_history')." · {$rqHistory}",
                'rqh:1',
            )];
        }

        if ($this->roles->canSeeProjects($user)) {
            $rows[] = [$this->cbBtn(
                '📆 '.__('app.telegram.menu_projects'),
                'pj:1',
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
        return $this->renderContractList(
            query: $this->queries->history($user),
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
        return $this->renderContractList(
            query: $this->queries->all($user),
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
        return $this->renderContractList(
            query: $this->queries->awaiting($user),
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
        return $this->renderContractList(
            query: $this->queries->mine($user),
            page: $page,
            title: __('app.telegram.list_mine_title'),
            emptyMessage: __('app.telegram.list_mine_empty'),
            callbackPrefix: 'mc',
        );
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function requisitionAwaitingList(User $user, int $page): array
    {
        return $this->renderRequisitionList(
            $this->requisitions->awaiting($user),
            $page,
            __('app.telegram.list_rq_awaiting_title'),
            __('app.telegram.list_rq_awaiting_empty'),
            'rq',
        );
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function requisitionMineList(User $user, int $page): array
    {
        return $this->renderRequisitionList(
            $this->requisitions->mine($user),
            $page,
            __('app.telegram.list_rq_mine_title'),
            __('app.telegram.list_rq_mine_empty'),
            'rqm',
        );
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function requisitionHistoryList(User $user, int $page): array
    {
        return $this->renderRequisitionList(
            $this->requisitions->history($user),
            $page,
            __('app.telegram.list_rq_history_title'),
            __('app.telegram.list_rq_history_empty'),
            'rqh',
        );
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function requisitionCard(Requisition $requisition, User $viewer): array
    {
        $requisition->loadMissing(['approvals.user', 'author', 'project']);

        $lines = ['<b>'.$this->htmlEscape((string) $requisition->number).'</b>'];

        if ($requisition->title) {
            $lines[] = '';
            $lines[] = '<b>«'.$this->htmlEscape($requisition->title).'»</b>';
        }

        $lines[] = '';
        $lines[] = __('app.telegram.field_status').': '.$this->requisitionBadge($requisition);
        $lines[] = __('app.telegram.field_author').': '.$this->htmlEscape($requisition->author?->name ?? '—');

        if ($requisition->project?->name) {
            $lines[] = __('app.label.project_single').': '.$this->htmlEscape($requisition->project->name);
        }

        if ($requisition->description) {
            $lines[] = '';
            $lines[] = '<blockquote>'.$this->htmlEscape($this->truncate($requisition->description, 400)).'</blockquote>';
        }

        $chain = $this->renderApprovalChain($requisition);

        if ($chain !== []) {
            $lines[] = '';
            $lines[] = '<b>'.__('app.telegram.field_chain').'</b>';
            $lines[] = '<blockquote>'.implode("\n", $chain).'</blockquote>';
        }

        $keyboard = [];

        if ($requisition->awaitsApprovalFrom($viewer)) {
            $keyboard[] = [
                $this->cbBtn('✅ '.__('app.approval.action.approve'), "rqa:{$requisition->id}"),
                $this->cbBtn('❌ '.__('app.approval.action.reject'), "rqr:{$requisition->id}"),
            ];
        } elseif ($requisition->acceptsRejectionFrom($viewer)) {
            // Still queued behind somebody: a veto is offered, an approval is not.
            $keyboard[] = [$this->cbBtn('❌ '.__('app.approval.action.reject'), "rqr:{$requisition->id}")];
        }

        $keyboard[] = [$this->urlBtn(
            '📋 '.__('app.telegram.btn_open_in_system'),
            RequisitionResource::getUrl('view', ['record' => $requisition->id]),
        )];
        $keyboard[] = [$this->cbBtn('‹ '.__('app.telegram.btn_back'), 'menu')];

        return ['text' => implode("\n", $lines), 'keyboard' => $keyboard];
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function projectList(int $page): array
    {
        return $this->renderList(
            query: $this->projects->active(),
            page: $page,
            title: __('app.telegram.list_projects_title'),
            emptyMessage: __('app.telegram.list_projects_empty'),
            callbackPrefix: 'pj',
            openPrefix: 'pjv',
            eagerLoad: ['order'],
            entry: fn (Project $project, int $position): string => $this->projectListEntry($project, $position),
        );
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    public function projectCard(Project $project): array
    {
        $project->loadMissing(['order', 'areaCurrency', 'standCurrency']);

        $lines = ['<b>'.$this->htmlEscape((string) $project->name).'</b>'];

        $lines[] = '';
        $lines[] = __('app.label.project_type').': '.$this->htmlEscape($project->type->label());

        if ($period = $this->projectPeriod($project)) {
            $lines[] = __('app.telegram.field_period').': '.$period;
        }

        if ($project->venue) {
            $lines[] = __('app.label.venue').': '.$this->htmlEscape($project->venue);
        }

        if ($project->order) {
            $lines[] = __('app.label.order_basis').': '.$this->htmlEscape(
                trim(($project->order->number ? $project->order->number.' · ' : '').$project->order->title),
            );
        }

        $contracts = $project->contracts()->count();

        if ($contracts > 0) {
            $lines[] = __('app.telegram.field_contracts').': <b>'.$contracts.'</b>';
        }

        if ($project->description) {
            $lines[] = '';
            $lines[] = '<blockquote>'.$this->htmlEscape($this->truncate($project->description, 400)).'</blockquote>';
        }

        return [
            'text' => implode("\n", $lines),
            'keyboard' => [
                [$this->urlBtn(
                    '📋 '.__('app.telegram.btn_open_in_system'),
                    BaseProjectResource::urlFor($project),
                )],
                [$this->cbBtn('‹ '.__('app.telegram.btn_back'), 'pj:1')],
            ],
        ];
    }

    /** @return array{text: string, keyboard: array<int, array<int, array<string, string>>>} */
    private function renderRequisitionList($query, int $page, string $title, string $emptyMessage, string $callbackPrefix): array
    {
        return $this->renderList(
            query: $query,
            page: $page,
            title: $title,
            emptyMessage: $emptyMessage,
            callbackPrefix: $callbackPrefix,
            openPrefix: 'rqv',
            eagerLoad: ['author', 'project', 'approvals.user'],
            entry: fn (Requisition $requisition, int $position): string => $this->requisitionListEntry($requisition, $position),
        );
    }

    private function requisitionListEntry(Requisition $requisition, int $position): string
    {
        $lines = [
            "<b>{$position}. {$this->htmlEscape((string) $requisition->number)}</b>  ".$this->requisitionBadge($requisition),
        ];

        if ($requisition->title) {
            $lines[] = '<i>«'.$this->htmlEscape($this->truncate($requisition->title, 56)).'»</i>';
        }

        $meta = [__('app.telegram.field_author').': '.$this->htmlEscape($requisition->author?->name ?? '—')];

        if ($progress = $requisition->approvalProgress()) {
            $meta[] = __('app.approval.column.chain').': '.$progress;
        }

        $lines[] = implode(' · ', $meta);

        return implode("\n", $lines);
    }

    private function projectListEntry(Project $project, int $position): string
    {
        $lines = ["<b>{$position}. {$this->htmlEscape((string) $project->name)}</b>"];

        $meta = [$this->htmlEscape($project->type->label())];

        if ($period = $this->projectPeriod($project)) {
            $meta[] = $period;
        }

        if ($project->venue) {
            $meta[] = $this->htmlEscape($this->truncate($project->venue, 40));
        }

        $lines[] = implode(' · ', $meta);

        return implode("\n", $lines);
    }

    private function projectPeriod(Project $project): ?string
    {
        if (! $project->starts_on) {
            return null;
        }

        return $project->ends_on
            ? $project->starts_on->format('d.m.Y').' — '.$project->ends_on->format('d.m.Y')
            : $project->starts_on->format('d.m.Y');
    }

    private function requisitionBadge(Requisition $requisition): string
    {
        $icon = match ($requisition->status) {
            RequisitionStatus::Draft => '📝',
            RequisitionStatus::InReview => '⏳',
            RequisitionStatus::Approved => '✅',
            RequisitionStatus::Rejected => '❌',
        };

        return $icon.' '.$this->htmlEscape($requisition->status->label());
    }

    /**
     * The chain as one line per step, marked with where it stands — the same
     * reading the register gives, sized for a phone.
     *
     * @return array<int, string>
     */
    private function renderApprovalChain(Requisition $requisition): array
    {
        return $requisition->activeApprovals()
            ->map(function ($approval): string {
                $icon = match ($approval->status) {
                    ApprovalStatus::Approved => '✅',
                    ApprovalStatus::Rejected => '❌',
                    ApprovalStatus::Pending => '⏳',
                    default => '▫️',
                };

                $line = $icon.' '.$this->htmlEscape($approval->user?->name ?? '—');

                if (filled($approval->comment)) {
                    $line .= "\n     <i>".$this->htmlEscape($this->truncate($approval->comment, 120)).'</i>';
                }

                return $line;
            })
            ->all();
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

    /**
     * Confirmation screen for unlinking the Telegram account.
     *
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    public function unlinkConfirm(): array
    {
        return [
            'text' => '<b>'.__('app.telegram.unlink_confirm_title')."</b>\n\n".__('app.telegram.unlink_confirm_body'),
            'keyboard' => [
                [
                    $this->cbBtn('🔓 '.__('app.telegram.unlink_confirm_yes'), 'unlink'),
                    $this->cbBtn('✖ '.__('app.action.cancel'), 'menu'),
                ],
            ],
        ];
    }

    /** @return array<int, array<int, array<string, string>>> */
    public function backToMenuKeyboard(): array
    {
        return [[$this->cbBtn('‹ '.__('app.telegram.btn_main_menu'), 'menu')]];
    }

    public function rejectPromptText(Contract $contract): string
    {
        return $this->headline('❌ '.__('app.telegram.reject_prompt_title'))
            .__('app.telegram.reject_prompt_body', ['number' => $contract->number]);
    }

    /** @return array<int, array<int, array<string, string>>> */
    public function rejectPromptKeyboard(): array
    {
        return [[$this->cancelButton()]];
    }

    /** @return array<string, string> */
    public function cancelButton(): array
    {
        return $this->cbBtn('✖ '.__('app.action.cancel'), 'cancel');
    }

    /** @return array<string, string> */
    public function skipCommentButton(string $callback): array
    {
        return $this->cbBtn('✅ '.__('app.telegram.approve_without_comment'), $callback);
    }

    public function approvePromptText(Contract $contract): string
    {
        return $this->headline('✅ '.__('app.telegram.approve_prompt_title'))
            .__('app.telegram.approve_prompt_body', ['number' => $contract->number]);
    }

    /** @return array<int, array<int, array<string, string>>> */
    public function approvePromptKeyboard(Contract $contract): array
    {
        return [
            [$this->cbBtn('✅ '.__('app.telegram.approve_without_comment'), "apnc:{$contract->id}")],
            [$this->cbBtn('✖ '.__('app.action.cancel'), 'cancel')],
        ];
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
        return $this->renderList(
            query: $query,
            page: $page,
            title: $title,
            emptyMessage: $emptyMessage,
            callbackPrefix: $callbackPrefix,
            openPrefix: 'view',
            eagerLoad: ['currency', 'contact', 'responsible'],
            entry: fn (Contract $contract, int $position): string => $this->contractListEntry($contract, $position),
        );
    }

    /**
     * One paginated register, whatever it holds: a numbered block per record,
     * a row of index buttons that open the card, and page navigation. Every
     * list in the bot goes through here so a second register is a query and a
     * formatter, not another copy of this.
     *
     * @param  array<int, string>  $eagerLoad
     * @param  callable(Model, int): string  $entry
     * @return array{text: string, keyboard: array<int, array<int, array<string, string>>>}
     */
    private function renderList(
        $query,
        int $page,
        string $title,
        string $emptyMessage,
        string $callbackPrefix,
        string $openPrefix,
        array $eagerLoad,
        callable $entry,
    ): array {
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
            ->with($eagerLoad)
            ->forPage($page, self::PAGE_SIZE)
            ->get()
            ->values();

        $entries = [];
        $indexButtons = [];

        foreach ($rows as $i => $record) {
            $position = $i + 1;
            $entries[] = $entry($record, $position);
            $indexButtons[] = $this->cbBtn($this->indexEmoji($position), "{$openPrefix}:{$record->getKey()}");
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
        return TelegramText::escape($value);
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
