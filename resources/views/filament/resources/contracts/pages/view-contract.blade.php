@php
    use App\Enums\ContractApproverStatus;
    use App\Models\Contract;
    use App\Models\ContractApprover;
    use Illuminate\Support\Carbon;

    $statusColor = $record->status->color();
    $statusLabel = $record->status->label();
    $current = $record->currentApprover();
    $directorUserId = $record->directorUser()?->id;
    $hero = $this->heroContext();

    $active = $record->activeApprovers;
    $historical = $record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]);
    $approvedCount = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    // Use the highest `order` in the chain so a "Step 3 / X" tile reads
    // correctly even when an earlier slot was invalidated and removed from
    // the active rows.
    $totalCount = (int) max($active->max('order') ?? 0, $active->count());
    $progress = $totalCount ? round($approvedCount / $totalCount * 100) : 0;

    // People who only appear in cancelled/skipped rows — e.g. an approver who
    // was dropped from the chain. They get a muted row at the foot of the
    // chain so their attempts stay reachable now that the standalone history
    // button is gone. (Normally every historical user is mirrored into the
    // active chain, so this stays empty.)
    $activeUserIds = $active->pluck('user_id')->all();
    $historicalOnly = $historical->whereNotIn('user_id', $activeUserIds)->unique('user_id')->values();

    // One detail-modal per person (active first), keyed by user_id — each
    // modal shows every record that user has on the contract.
    $allApprovers = $active->concat($historicalOnly)->values();

    $pillFor = fn (ContractApproverStatus $status): string => $status->color();
    $statusName = fn (ContractApproverStatus $status): string => $status->label();

    // Before a contract is submitted the approvers are technically "queued",
    // but the review hasn't started — so show "Not submitted" instead of
    // "In queue" while the contract is still a draft.
    $isDraft = $record->status === Contract::STATUS_DRAFT;
    $approverLabel = fn (ContractApproverStatus $status): string => $isDraft && $status === ContractApproverStatus::Queued
        ? __('app.label.not_submitted')
        : $status->label();

    $ic = fn (string $name, int $size = 18) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    // SLA window (days an approver gets once it's their turn) and the saturated
    // dot colour per Filament status token — used to tint the per-approver modal.
    $slaDays = (int) settings('approval.sla_days', 2);
    $ringFor = [
        'success' => '#10b981',
        'danger' => '#ef4444',
        'info' => '#3b82f6',
        'warning' => '#fb923c',
        'primary' => '#818cf8',
        'gray' => '#cbd5e1',
    ];

    $activities = $this->getActivities()
        ->unique(fn ($a) => ($a->description ?? '').'|'.$a->created_at?->format('YmdHi'))
        ->values();
    $activityDays = $activities->groupBy(fn ($a) => $a->created_at?->format('Y-m-d'));

    $dayLabel = function (?string $date): string {
        if (! $date) {
            return '';
        }
        $c = Carbon::parse($date);
        if ($c->isToday()) {
            return __('app.label.today');
        }
        if ($c->isYesterday()) {
            return __('app.label.yesterday');
        }

        return $c->translatedFormat('d F Y');
    };

    // Core info visible by default; everything else lives behind "Show more".
    $details = [
        ['heroicon-o-bolt', __('app.label.status'), $statusLabel, 'status', false],
        ['heroicon-o-hashtag', __('app.label.contract_number'), $record->number, null, false],
        ['heroicon-o-building-office-2', __('app.label.contact_single'), $record->contact?->name, $record->contact ? 'contact' : null, false],
        ['heroicon-o-document-duplicate', __('app.label.contract_template_single'), $record->template?->name, null, false],
        ['heroicon-o-tag', __('app.label.order_type_single'), $record->orderType?->title, null, false],
        ['heroicon-o-user', __('app.label.responsible'), $record->responsible?->name, null, false],
        ['heroicon-o-banknotes', __('app.label.amount'), number_format((float) $record->amount, 2, '.', ' ').' '.($record->currency?->short_name ?? ''), null, false],

        // Extra rows — collapsed by default.
        ['heroicon-o-paper-airplane', __('app.label.submitted'), $this->submittedAt()?->format('d.m.Y H:i'), null, true],
        ['heroicon-o-calendar-days', __('app.label.signing_date'), $record->signed_at?->format('d.m.Y'), null, true],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i'), null, true],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i'), null, true],
    ];

    $contact = $record->contact;
    $contactType = $contact?->type === \App\Models\Contact::TYPE_INDIVIDUAL
        ? __('app.contact.type.individual')
        : __('app.contact.type.legal');

    // Group fields exactly the way Filament's ContactForm splits them:
    // identity + tax/legal + contacts + bank requisites.
    $contactGroups = $contact ? [
        [__('app.label.basic_information'), array_values(array_filter([
            ['heroicon-o-building-office-2', __('app.label.name'), $contact->name],
            ['heroicon-o-identification', __('app.label.contact_type'), $contactType],
            ['heroicon-o-tag', __('app.label.legal_form'), $contact->legal_form],
            ['heroicon-o-map-pin', __('app.label.address'), $contact->address],
        ], fn ($r) => ! empty($r[2])))],

        [__('app.label.legal_details'), array_values(array_filter([
            ['heroicon-o-finger-print', __('app.label.inn'), $contact->inn],
            ['heroicon-o-finger-print', 'PINFL', $contact->pinfl],
            ['heroicon-o-bookmark', 'OKED', $contact->oked],
            ['heroicon-o-user', __('app.label.director_name'), $contact->director_name],
            ['heroicon-o-user-circle', __('app.label.contact_person'), $contact->contact_person],
        ], fn ($r) => ! empty($r[2])))],

        [__('app.label.contacts'), array_values(array_filter([
            ['heroicon-o-phone', __('app.label.phone'), $contact->phone],
            ['heroicon-o-envelope', __('app.label.email'), $contact->email],
        ], fn ($r) => ! empty($r[2])))],

        [__('app.label.bank_requisites'), array_values(array_filter([
            ['heroicon-o-building-library', __('app.label.bank_name'), $contact->bank_name],
            ['heroicon-o-banknotes', __('app.label.bank_account'), $contact->bank_account],
            ['heroicon-o-hashtag', 'MFO', $contact->mfo],
        ], fn ($r) => ! empty($r[2])))],
    ] : [];
    // Drop any group that ended up empty after filtering.
    $contactGroups = array_values(array_filter($contactGroups, fn ($g) => count($g[1]) > 0));
@endphp

<x-filament-panels::page>
    @include('filament.resources.contracts.pages.view-contract.styles')

    <div class="cw"
        x-data="{ approver: null, contactOpen: false, basicExpanded: false, tab: 'overview', historyShown: 8, historyFilter: 'all' }"
        @keydown.escape.window="approver = null; contactOpen = false">
        @php
            $submittedAt = $this->submittedAt();
        @endphp

        {{-- Tabs — overall status pill rides on the right so it stays visible
             on both tabs without a redundant full-width strip up top. --}}
        <div class="cw-tabs">
            <button type="button" class="cw-tab" :class="tab === 'overview' ? 'cw-tab--active' : ''" @click="tab = 'overview'">{!! $ic('heroicon-o-rectangle-group', 16) !!} {{ __('app.label.overview') }}</button>
            <button type="button" class="cw-tab" :class="tab === 'history' ? 'cw-tab--active' : ''" @click="tab = 'history'">{!! $ic('heroicon-o-clock', 16) !!} {{ __('app.label.history') }}@if ($activities->isNotEmpty())<span class="cw-tab__c">{{ $activities->count() }}</span>@endif</button>
            <span class="cw-pill cw-pill--{{ $statusColor }} cw-pill--lg cw-tabs__status">{{ $statusLabel }}</span>
        </div>

        {{-- Overview --}}
        <div x-show="tab === 'overview'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="cw-panel">
            <div class="cw-cols">
                <div class="cw-main">
                <section class="cw-card">
                    <div class="cw-hd">
                        <span class="cw-hd__ic">{!! $ic('heroicon-o-users') !!}</span>
                        <h2 class="cw-hd__t">{{ __('app.label.approval_chain') }}</h2>
                    </div>

                    {{-- Progress band — one clean continuous fill (green =
                         approved fraction), a plain "N of M approved" counter
                         plus a status breakdown, and the "Awaiting X" tile while
                         in review. The per-step detail lives in the timeline
                         below, so the band stays a single legible bar. --}}
                    @if ($totalCount > 0)
                        @php
                            $rejectedCount = $active->where('status', ContractApprover::STATUS_REJECTED)->count();
                            $reviewingCount = $active->where('status', ContractApprover::STATUS_PENDING)->count();
                            $queuedCount = max(0, $totalCount - $approvedCount - $rejectedCount - $reviewingCount);
                            $fillPct = round($approvedCount / $totalCount * 100);
                            $barColor = $rejectedCount > 0 ? '#ef4444' : ($approvedCount === $totalCount ? '#10b981' : '#10b981');
                        @endphp
                        <div class="cw-prog">
                            <div class="cw-prog__top">
                                <div class="cw-prog__l">
                                    <span class="cw-prog__count"><b>{{ $approvedCount }}</b> {{ __('app.label.of') }} {{ $totalCount }} {{ __('app.label.approved_lower') }}</span>
                                    @if ($submittedAt)
                                        <span class="cw-prog__sub">{!! $ic('heroicon-m-paper-airplane', 12) !!} {{ __('app.label.submitted') }} {{ $submittedAt->diffForHumans() }}</span>
                                    @endif
                                </div>
                                @if ($current && $record->status === Contract::STATUS_IN_REVIEW)
                                    <span class="cw-prog__await">
                                        <span class="cw-prog__await-lb">{{ __('app.label.awaiting') }}</span>
                                        <img src="{{ $this->approverAvatar($current) }}" alt="{{ $current->user?->name }}">
                                        <span class="cw-prog__await-nm">{{ $current->user?->name }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="cw-prog__track">
                                <span class="cw-prog__fill" style="width:{{ $fillPct }}%;background:{{ $barColor }};"></span>
                            </div>
                            <div class="cw-prog__legend">
                                @if ($approvedCount > 0)<span class="cw-lg"><i style="background:#10b981;"></i>{{ $approvedCount }} {{ __('app.contract_approver.status.approved') }}</span>@endif
                                @if ($reviewingCount > 0)<span class="cw-lg"><i style="background:#6366f1;"></i>{{ $reviewingCount }} {{ __('app.contract_approver.status.pending') }}</span>@endif
                                @if ($queuedCount > 0)<span class="cw-lg"><i style="background:#cbd5e1;"></i>{{ $queuedCount }} {{ __('app.contract_approver.status.queued') }}</span>@endif
                                @if ($rejectedCount > 0)<span class="cw-lg"><i style="background:#ef4444;"></i>{{ $rejectedCount }} {{ __('app.contract_approver.status.rejected') }}</span>@endif
                            </div>
                        </div>
                    @endif

                    @if ($active->isEmpty() && $historical->isEmpty())
                        <div class="cw-bd"><p style="font-size:0.854rem;color:var(--m)">{{ __('app.label.no_approvers') }}</p></div>
                    @else
                        <div class="cw-chain">
                            @foreach ($active as $ap)
                                @php
                                    $state = $this->approverState($ap);
                                    $v = $this->approverVisual($ap);
                                    $isDirector = $directorUserId && $ap->user_id === $directorUserId;
                                @endphp
                                <div class="cw-step cw-step--{{ $state }}{{ $isDirector ? ' cw-step--director' : '' }}">
                                    <div class="cw-node">
                                        <img src="{{ $this->approverAvatar($ap) }}" alt="{{ $ap->user?->name }}">
                                        <span class="cw-badge cw-badge--{{ $state }}">{!! $ic($v['icon'], 13) !!}</span>
                                    </div>
                                    <div class="cw-step__bd">
                                        <div class="cw-step__nm">{{ $ap->user?->name }} <span class="cw-ord">#{{ $ap->order }}</span>@if ($isDirector)<span class="cw-director">{!! $ic('heroicon-s-shield-check', 11) !!} {{ __('app.label.final_sign_off') }}</span>@endif</div>
                                        <div class="cw-step__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                                        <div class="cw-step__meta">
                                            <span class="cw-pill cw-pill--{{ $pillFor($ap->status) }}">{{ $approverLabel($ap->status) }}</span>
                                            @if ($ap->acted_at)
                                                <span class="cw-when">{!! $ic('heroicon-m-check', 13) !!} {{ $ap->acted_at->format('d.m.Y H:i') }}</span>
                                            @elseif ($state === 'current' && $ap->due_at)
                                                @php $stepDueIso = $ap->due_at->toIso8601String(); @endphp
                                                <span class="cw-when"
                                                    x-data="contractCountdown('{{ $stepDueIso }}')"
                                                    x-init="start()"
                                                    :class="overdue ? 'cw-when--over' : 'cw-when--soon'"
                                                    :title="absolute">
                                                    <template x-if="overdue">{!! $ic('heroicon-m-exclamation-triangle', 13) !!}</template>
                                                    <template x-if="! overdue">{!! $ic('heroicon-m-clock', 13) !!}</template>
                                                    <span x-text="label"></span>
                                                </span>
                                            @endif
                                        </div>
                                        @if ($ap->comment)<div class="cw-cmt">{{ $ap->comment }}</div>@endif
                                        @if ($ap->system_comment)<div class="cw-cmt" style="background:rgba(251,146,60,.10);border-color:rgba(251,146,60,.32);color:#c2410c;font-weight:550;">{{ $ap->system_comment }}</div>@endif
                                    </div>
                                    <button type="button" class="cw-eye" title="{{ __('app.label.view_history') }}" @click="approver = {{ $ap->user_id }}">{!! $ic('heroicon-o-eye', 16) !!}</button>
                                </div>
                            @endforeach

                            {{-- People dropped from the chain — muted, still
                                 openable so their cancelled attempts aren't lost. --}}
                            @foreach ($historicalOnly as $h)
                                <div class="cw-step cw-step--ghost">
                                    <div class="cw-node">
                                        <img src="{{ $this->approverAvatar($h) }}" alt="{{ $h->user?->name }}">
                                        <span class="cw-badge cw-badge--queued">{!! $ic('heroicon-m-minus', 13) !!}</span>
                                    </div>
                                    <div class="cw-step__bd">
                                        <div class="cw-step__nm">{{ $h->user?->name }}</div>
                                        <div class="cw-step__dp">{{ $h->user?->department?->name }}{{ $h->user?->position?->name ? ' · '.$h->user->position->name : '' }}</div>
                                        <div class="cw-step__meta">
                                            <span class="cw-pill cw-pill--gray">{{ __('app.label.no_longer_in_chain') }}</span>
                                        </div>
                                    </div>
                                    <button type="button" class="cw-eye" title="{{ __('app.label.view_history') }}" @click="approver = {{ $h->user_id }}">{!! $ic('heroicon-o-eye', 16) !!}</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Payments only exist once the contract is fully approved by
                     the director, so the progress block is hidden until then. --}}
                @if ($record->status === Contract::STATUS_APPROVED)
                @php
                    $paymentSummary = $this->paymentSummary();
                    $payments = $paymentSummary['payments'];
                    $paidPercent = $paymentSummary['paid_percent'];
                    $remainingPercent = $paymentSummary['remaining_percent'];
                    $paymentStatus = $paymentSummary['status'];
                    $paymentBar = (int) min(100, max(0, round($paidPercent)));
                @endphp

                <section class="cw-card" style="margin-top:1rem;">
                    <div class="cw-hd">
                        <span class="cw-hd__ic">{!! $ic('heroicon-o-banknotes') !!}</span>
                        <h2 class="cw-hd__t">{{ __('app.label.payment_progress') }}</h2>
                        <span class="cw-pill cw-pill--{{ $paymentStatus->color() }}" style="margin-left:auto;padding:.32rem .7rem;font-size:.78rem;">{{ $paymentStatus->label() }}</span>
                    </div>

                    <div class="cw-prog" style="padding-top:.6rem;">
                        <div class="cw-prog__top">
                            <div class="cw-prog__l">
                                <span class="cw-prog__count"><b>{{ rtrim(rtrim(number_format($paidPercent, 2, '.', ''), '0'), '.') }}%</b> / 100%</span>
                                @if ($remainingPercent > 0)
                                    <span class="cw-prog__sub">{{ __('app.label.remaining_to_pay', ['percent' => rtrim(rtrim(number_format($remainingPercent, 2, '.', ''), '0'), '.')]) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="cw-prog__bar" style="background:rgba(148,163,184,.18);border-radius:9999px;overflow:hidden;height:.55rem;margin-top:.45rem;">
                            <span style="display:block;height:100%;width:{{ $paymentBar }}%;background:linear-gradient(90deg,#22c55e,#16a34a);transition:width .25s ease;"></span>
                        </div>
                    </div>

                    @if ($payments->isEmpty())
                        <div class="cw-bd"><p style="font-size:.854rem;color:var(--m)">{{ __('app.label.no_payments_yet') }}</p></div>
                    @else
                        <div class="cw-chain" style="gap:.55rem;">
                            @foreach ($payments as $payment)
                                @php $screenshotUrl = $this->paymentScreenshotUrl($payment); @endphp
                                <div class="cw-step" style="align-items:center;">
                                    <div class="cw-node" style="background:rgba(34,197,94,.12);">
                                        <span class="cw-badge cw-badge--approved">{!! $ic('heroicon-s-check-circle', 13) !!}</span>
                                    </div>
                                    <div class="cw-step__bd">
                                        <div class="cw-step__nm">
                                            {{ rtrim(rtrim(number_format((float) $payment->percent, 2, '.', ''), '0'), '.') }}%
                                            <span class="cw-ord">{{ $payment->paid_at?->format('d.m.Y') }}</span>
                                        </div>
                                        <div class="cw-step__dp">
                                            {{ __('app.label.created_by') }}: {{ $payment->creator?->name ?? __('app.label.system') }}
                                            · {{ $payment->created_at?->format('d.m.Y H:i') }}
                                        </div>
                                    </div>
                                    @if ($screenshotUrl)
                                        <a href="{{ $screenshotUrl }}" target="_blank" rel="noopener" class="cw-eye" title="{{ __('app.label.open_screenshot') }}">{!! $ic('heroicon-o-photo', 16) !!}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
                @endif
            </div>

            {{-- SIDEBAR: combined Document + Basic Information card --}}
            <div class="cw-side">
                <section class="cw-card">
                    <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><h2 class="cw-hd__t">{{ __('app.label.basic_information') }}</h2></div>

                    {{-- File card (top of merged section) --}}
                    @if ($record->documentExists())
                        @php
                            $ext = 'DOCX';
                            $createdLabel = $record->created_at?->translatedFormat('d M Y H:i');
                            $editUrl = $this->editorUrl($record->canBeEditedBy() ? 'edit' : 'view');
                            $previewUrl = $this->pdfPreviewUrl();
                        @endphp
                        <div class="cw-file">
                            <div class="cw-file__thumb" aria-hidden="true">
                                <svg viewBox="0 0 64 80" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 4 h36 l12 12 v60 H8 Z" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>
                                    <path d="M44 4 v12 h12" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                                    <rect x="14" y="30" width="34" height="2" rx="1" fill="#e2e8f0"/>
                                    <rect x="14" y="36" width="28" height="2" rx="1" fill="#e2e8f0"/>
                                    <rect x="14" y="42" width="34" height="2" rx="1" fill="#e2e8f0"/>
                                    <rect x="14" y="48" width="22" height="2" rx="1" fill="#e2e8f0"/>
                                </svg>
                                <span class="cw-file__ext">{{ $ext }}</span>
                            </div>
                            <div class="cw-file__body">
                                <div class="cw-file__field">
                                    <div class="cw-file__lb">{{ __('app.label.file_name') }}</div>
                                    <div class="cw-file__vl">{{ $record->number }}.docx</div>
                                </div>
                                <div class="cw-file__field">
                                    <div class="cw-file__lb">{{ __('app.label.size') }}</div>
                                    <div class="cw-file__vl">{{ $this->documentSizeLabel() }}</div>
                                </div>
                                @if ($createdLabel)
                                    <div class="cw-file__field">
                                        <div class="cw-file__lb">{{ __('app.label.created_at') }}</div>
                                        <div class="cw-file__vl">{{ $createdLabel }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        {{-- One primary action only. Preview / PDF download
                             already live in the page header actions, so they're
                             not duplicated here. --}}
                        <div class="cw-file__act">
                            <a href="{{ $editUrl }}" class="cw-btn cw-btn--primary">
                                {!! $ic('heroicon-o-pencil-square', 16) !!}
                                <span>{{ $record->canBeEditedBy() ? __('app.action.open_editor') : __('app.action.open_file') }}</span>
                            </a>
                        </div>
                    @endif

                    <div class="cw-dets">
                        @foreach ($details as [$icon, $label, $value, $type, $extra])
                            @php $hasValue = ! empty($value); @endphp
                            @if ($type === 'contact' && $hasValue)
                                <button type="button" class="cw-row cw-row--link" @click="contactOpen = true" @if ($extra) x-show="basicExpanded" x-cloak @endif>
                                    <span class="cw-row__k"><span class="cw-row__ic">{!! $ic($icon, 16) !!}</span><span class="cw-row__lb">{{ $label }}</span></span>
                                    <span class="cw-row__v"><span class="cw-row__vl">{{ $value }} {!! $ic('heroicon-m-arrow-top-right-on-square', 13) !!}</span></span>
                                </button>
                            @elseif ($type === 'status')
                                <div class="cw-row" @if ($extra) x-show="basicExpanded" x-cloak @endif>
                                    <span class="cw-row__k"><span class="cw-row__ic">{!! $ic($icon, 16) !!}</span><span class="cw-row__lb">{{ $label }}</span></span>
                                    <span class="cw-row__v"><span class="cw-pill cw-pill--{{ $statusColor }}" style="padding:.32rem .7rem .32rem .55rem;font-size:.8rem;">{{ $value }}</span></span>
                                </div>
                            @else
                                <div class="cw-row" @if ($extra) x-show="basicExpanded" x-cloak @endif>
                                    <span class="cw-row__k"><span class="cw-row__ic">{!! $ic($icon, 16) !!}</span><span class="cw-row__lb">{{ $label }}</span></span>
                                    <span class="cw-row__v">
                                        @if ($hasValue)
                                            <span class="cw-row__vl">{{ $value }}</span>
                                        @else
                                            <span class="cw-row__vl cw-row__vl--muted">{{ __('app.label.not_set') }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <button type="button" class="cw-show-more" @click="basicExpanded = ! basicExpanded">
                        <span x-show="! basicExpanded">{!! $ic('heroicon-m-chevron-down', 14) !!} {{ __('app.label.show_more') }}</span>
                        <span x-show="basicExpanded" x-cloak>{!! $ic('heroicon-m-chevron-up', 14) !!} {{ __('app.label.show_less') }}</span>
                    </button>
                </section>
            </div>
        </div>
        </div>

        {{-- History --}}
        <div x-show="tab === 'history'" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="cw-panel">
            @php
                $flat = $activities->map(function ($a, $i) {
                    return (object) [
                        'idx' => $i,
                        'event' => $a->event ?? '',
                        'description' => $this->activityLabel($a->event ?? '', $a->description),
                        'causer' => $a->causer?->name ?? __('app.label.system'),
                        'time' => $a->created_at?->format('H:i'),
                        'day' => $a->created_at?->format('Y-m-d'),
                        'group' => $this->activityGroup($a->event ?? ''),
                        'comment' => data_get($a->properties, 'comment'),
                    ];
                })->values();
                $workflowCount = $flat->where('group', 'workflow')->count();
                $editCount = $flat->where('group', 'edit')->count();
                $totalCount = $flat->count();
            @endphp
            <section class="cw-card">
                <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clock') !!}</span><h2 class="cw-hd__t">{{ __('app.label.execution_history') }}</h2></div>

                @if ($flat->isEmpty())
                    <div class="cw-bd"><p style="font-size:0.854rem;color:var(--m)">{{ __('app.label.no_history') }}</p></div>
                @else
                    <div class="cw-filters">
                        <button type="button" class="cw-chip" :class="historyFilter === 'all' ? 'cw-chip--active' : ''" @click="historyFilter = 'all'; historyShown = 8">
                            {{ __('app.label.all') }} <span class="cw-chip__c">{{ $totalCount }}</span>
                        </button>
                        <button type="button" class="cw-chip" :class="historyFilter === 'workflow' ? 'cw-chip--active' : ''" @click="historyFilter = 'workflow'; historyShown = 8">
                            {!! $ic('heroicon-o-arrows-right-left', 13) !!} {{ __('app.label.workflow_events') }} <span class="cw-chip__c">{{ $workflowCount }}</span>
                        </button>
                        <button type="button" class="cw-chip" :class="historyFilter === 'edit' ? 'cw-chip--active' : ''" @click="historyFilter = 'edit'; historyShown = 8">
                            {!! $ic('heroicon-o-pencil-square', 13) !!} {{ __('app.label.edit_events') }} <span class="cw-chip__c">{{ $editCount }}</span>
                        </button>
                    </div>

                    <div class="cw-bd">
                        @php $lastDay = null; @endphp
                        @foreach ($flat as $row)
                            @php $v = $this->activityVisual($row->event); $dayHd = $row->day !== $lastDay ? $dayLabel($row->day) : null; $lastDay = $row->day; @endphp
                            <div x-show="(historyFilter === 'all' || historyFilter === '{{ $row->group }}') && {{ $row->idx }} < historyShown">
                                @if ($dayHd)
                                    <div class="cw-day__hd" style="padding-top: {{ $loop->first ? '0' : '.6rem' }};">{{ $dayHd }}</div>
                                @endif
                                <div class="cw-tl">
                                    <span class="cw-tl__time">{{ $row->time }}</span>
                                    <span class="cw-tl__ic cw-tl__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 15) !!}</span>
                                    <div class="cw-tl__bd">
                                        <div class="cw-tl__ds">{{ $row->description }}</div>
                                        <div class="cw-tl__mt"><span>{{ $row->causer }}</span></div>
                                        @if ($row->comment)<div class="cw-cmt" style="margin-top:.45rem">{{ $row->comment }}</div>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <button type="button" class="cw-loadmore"
                                x-show="historyShown < {{ $totalCount }}"
                                @click="historyShown = historyShown + 8">
                            {{ __('app.label.load_more') }}
                        </button>
                    </div>
                @endif
            </section>
        </div>

        {{-- Contact detail modal --}}
        @if ($contact)
            <div class="cw-modal" x-show="contactOpen" x-cloak style="display:none;" role="dialog" aria-modal="true" @keydown.escape.window="contactOpen = false">
                <div class="cw-modal__bg" @click="contactOpen = false"></div>
                <div class="cw-modal__card" style="max-width:34rem;"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="cw-modal__hd">
                        <span class="cw-row__ic" style="background:var(--accent-soft);width:2.6rem;height:2.6rem;border-radius:.7rem;display:inline-flex;align-items:center;justify-content:center;color:var(--accent);">
                            {!! $ic('heroicon-o-building-office-2', 22) !!}
                        </span>
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ $contact->name }}</div>
                            @if ($contact->legal_form)
                                <div class="cw-modal__dp">{{ $contact->legal_form }}</div>
                            @endif
                        </div>
                        <button type="button" class="cw-modal__x" @click="contactOpen = false" aria-label="{{ __('app.action.cancel') }}">{!! $ic('heroicon-m-x-mark', 16) !!}</button>
                    </div>
                    <div class="cw-modal__bd">
                        @foreach ($contactGroups as [$groupLabel, $rows])
                            <div class="cw-contact-group">
                                <div class="cw-contact-group__t">{{ $groupLabel }}</div>
                                <div class="cw-contact-rows">
                                    @foreach ($rows as [$ic_, $lb, $vl])
                                        <div class="cw-crow">
                                            <span class="cw-crow__ic">{!! $ic($ic_, 16) !!}</span>
                                            <span class="cw-crow__lb">{{ $lb }}</span>
                                            <span class="cw-crow__vl">{{ $vl }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Per-approver detail modals — shows every record this person has
             on the contract (current + invalidated attempts), newest first. --}}
        @foreach ($allApprovers as $ap)
            @php
                $apActs = $activities->where('causer_id', $ap->user_id)->values();
                // Every record this user has on the contract, newest first.
                $allRecords = $record->approvers->where('user_id', $ap->user_id)->sortByDesc('id')->values();
                $isHistorical = fn ($r) => in_array($r->status, [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED], true);

                $apShown = $ap->displayStatus();
                $apTone = $pillFor($apShown);
                $apRing = $ringFor[$apTone] ?? '#cbd5e1';

                // Timing tile, shaped by where this person currently stands.
                $timing = null;
                if ($ap->acted_at && in_array($ap->status, [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED], true)) {
                    $onTime = $ap->due_at ? $ap->acted_at->lessThanOrEqualTo($ap->due_at) : null;
                    $startedAt = $ap->due_at?->copy()->subDays($slaDays);
                    $timing = [
                        'lb' => __('app.label.responded_in'),
                        'vl' => $startedAt ? $startedAt->diffForHumans($ap->acted_at, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : $ap->acted_at->format('d.m.Y'),
                        'sub' => $onTime === null ? null : ($onTime ? __('app.label.on_time') : __('app.label.is_late')),
                        'tone' => $onTime === false ? 'danger' : 'success',
                    ];
                } elseif ($ap->status === ContractApprover::STATUS_PENDING && $ap->due_at) {
                    $overdue = $ap->isOverdue();
                    $timing = [
                        'lb' => __('app.label.due'),
                        'vl' => $ap->due_at->format('d.m.Y'),
                        'sub' => $overdue
                            ? __('app.label.overdue')
                            : __('app.label.time_left', ['time' => $ap->due_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]),
                        'tone' => $overdue ? 'danger' : 'warning',
                    ];
                } elseif ($ap->status === ContractApprover::STATUS_QUEUED) {
                    $timing = [
                        'lb' => __('app.label.sla'),
                        'vl' => trans_choice('app.label.days_count', $slaDays, ['count' => $slaDays]),
                        'sub' => __('app.label.sla_hint'),
                        'tone' => 'primary',
                    ];
                }
            @endphp
            <div class="cw-modal" x-show="approver === {{ $ap->user_id }}" x-cloak style="display:none;" role="dialog" aria-modal="true" @keydown.escape.window="approver = null">
                <div class="cw-modal__bg" @click="approver = null"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
                <div class="cw-modal__card" style="max-width:52rem;" @click.stop
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                    <div class="cw-modal__bar" style="background:{{ $apRing }};"></div>
                    <div class="cw-modal__hd">
                        <img src="{{ $this->approverAvatar($ap) }}" alt="{{ $ap->user?->name }}" style="box-shadow:0 0 0 3px var(--s),0 0 0 5px {{ $apRing }};">
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ $ap->user?->name }}</div>
                            <div class="cw-modal__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                        </div>
                        <div class="cw-modal__hd-pill">
                            <span class="cw-pill cw-pill--lg cw-pill--{{ $apTone }}">{{ $approverLabel($apShown) }}</span>
                            <button type="button" class="cw-modal__x" @click="approver = null" aria-label="{{ __('app.action.cancel') }}">{!! $ic('heroicon-o-x-mark', 16) !!}</button>
                        </div>
                    </div>

                    <div class="cw-stats">
                        <div class="cw-stat">
                            <span class="cw-stat__lb">{!! $ic('heroicon-m-queue-list', 12) !!} {{ __('app.label.step') }}</span>
                            <div class="cw-stat__vl">{{ $ap->order }} / {{ $totalCount }}</div>
                            @if ($allRecords->count() > 1)
                                <div class="cw-stat__sub">{{ trans_choice('app.label.attempts_count', $allRecords->count(), ['count' => $allRecords->count()]) }}</div>
                            @endif
                        </div>
                        @if ($timing)
                            <div class="cw-stat cw-stat--{{ $timing['tone'] }}">
                                <span class="cw-stat__lb">{!! $ic('heroicon-m-clock', 12) !!} {{ $timing['lb'] }}</span>
                                <div class="cw-stat__vl">{{ $timing['vl'] }}</div>
                                @if ($timing['sub'])<div class="cw-stat__sub">{{ $timing['sub'] }}</div>@endif
                            </div>
                        @endif
                        @if ($ap->reminder_sent_at)
                            <div class="cw-stat">
                                <span class="cw-stat__lb">{!! $ic('heroicon-m-bell-alert', 12) !!} {{ __('app.label.reminders') }}</span>
                                <div class="cw-stat__vl">{{ $ap->reminder_sent_at->format('d.m.Y') }}</div>
                                <div class="cw-stat__sub">{{ __('app.label.reminder_sent_ago', ['time' => $ap->reminder_sent_at->diffForHumans(now(), ['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]) }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="cw-modal__bd">
                        {{-- One row per record. The live attempt reads normally;
                             cancelled / skipped rows are dimmed but still show the
                             verdict the approver reached and their own comment. --}}
                        <table class="cw-rt">
                            <thead>
                                <tr>
                                    <th>{{ __('app.label.status') }}</th>
                                    <th>{{ __('app.label.comment') }}</th>
                                    <th>{{ __('app.label.acted_at') }}</th>
                                    <th>{{ __('app.label.updated_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($allRecords as $rec)
                                    @php
                                        $past = $isHistorical($rec);
                                        $shown = $rec->displayStatus();
                                        $rowAccent = $ringFor[$pillFor($shown)] ?? 'transparent';
                                    @endphp
                                    <tr @class(['is-past' => $past]) style="--row-accent:{{ $rowAccent }};">
                                        <td class="cw-rt__st">
                                            <span class="cw-pill cw-pill--{{ $pillFor($shown) }}">{{ $approverLabel($shown) }}</span>
                                            @if ($rec->wasCancelledAfterVerdict())
                                                <span class="cw-rt__tag">{!! $ic('heroicon-m-x-circle', 11) !!} {{ __('app.label.cancelled') }}</span>
                                            @endif
                                            @if ($rec->isOverdue())
                                                <span class="cw-rt__overdue">{!! $ic('heroicon-m-exclamation-triangle', 11) !!} {{ __('app.label.overdue') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($rec->comment)
                                                <div class="cw-rt__cmt">{{ $rec->comment }}</div>
                                            @else
                                                <div class="cw-rt__cmt cw-rt__cmt--muted">—</div>
                                            @endif
                                            @if ($rec->system_comment)
                                                <div class="cw-rt__sys"><span class="cw-rt__sys-lb">{{ __('app.label.system_note') }}:</span>{{ $rec->system_comment }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($rec->acted_at)
                                                <div class="cw-rt__date">{{ $rec->acted_at->format('d.m.Y') }}<small>{{ $rec->acted_at->format('H:i') }}</small></div>
                                            @elseif ($rec->due_at && $rec->status === ContractApprover::STATUS_PENDING)
                                                <div class="cw-rt__date"><small>{{ __('app.label.due') }} {{ $rec->due_at->format('d.m.Y') }}</small></div>
                                            @else
                                                <div class="cw-rt__date cw-rt__date--muted">—</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($rec->updated_at)
                                                <div class="cw-rt__date">{{ $rec->updated_at->format('d.m.Y') }}<small>{{ $rec->updated_at->format('H:i') }}</small></div>
                                            @else
                                                <div class="cw-rt__date cw-rt__date--muted">—</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if ($apActs->isNotEmpty())
                            <div class="cw-sub">
                                <span>{{ __('app.label.approver_activity') }}</span>
                                <span class="cw-sub__c">{{ $apActs->count() }}</span>
                            </div>
                            <div class="cw-act" x-data="{ all: false }">
                                @foreach ($apActs as $a)
                                    @php $v = $this->activityVisual($a->event ?? ''); $al = $this->activityLabel($a->event ?? '', $a->description); @endphp
                                    <div class="cw-act__row" @if ($loop->index >= 4) x-show="all" x-cloak @endif>
                                        <span class="cw-act__time">{{ $a->created_at?->format('H:i') }}</span>
                                        <span class="cw-act__ic cw-act__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 13) !!}</span>
                                        <span class="cw-act__ds" title="{{ $al }}">{{ $al }}</span>
                                        <span class="cw-act__rel" title="{{ $a->created_at?->format('d.m.Y H:i') }}">{{ $a->created_at?->diffForHumans() }}</span>
                                    </div>
                                @endforeach
                                @if ($apActs->count() > 4)
                                    <button type="button" class="cw-act__toggle" @click="all = !all">
                                        <span x-show="!all">{{ __('app.label.show_all') }} ({{ $apActs->count() - 4 }})</span>
                                        <span x-show="all" x-cloak>{{ __('app.label.collapse') }}</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        // Live SLA countdown for the current approver's due_at. Re-ticks every
        // second so the meta-strip pill reads "1d 12h 04m" / "3h 22m" / "12m".
        // Switches to a red "Overdue · X ago" once the deadline passes.
        window.contractCountdown = (iso) => ({
            due: new Date(iso),
            overdue: false,
            label: '',
            absolute: '',
            _t: null,

            start() {
                this.absolute = this.due.toLocaleString();
                this.tick();
                this._t = setInterval(() => this.tick(), 1000);
            },

            tick() {
                const now = new Date();
                let diff = Math.floor((this.due - now) / 1000);
                this.overdue = diff < 0;
                if (this.overdue) {
                    diff = -diff;
                }

                const d = Math.floor(diff / 86400);
                const h = Math.floor((diff % 86400) / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;

                let pretty;
                if (d > 0) {
                    pretty = `${d}d ${h}h ${m}m`;
                } else if (h > 0) {
                    pretty = `${h}h ${m}m ${s}s`;
                } else {
                    pretty = `${m}m ${s}s`;
                }

                this.label = this.overdue
                    ? @json(__('app.label.overdue')) + ' · ' + pretty
                    : @json(__('app.label.due_in', ['time' => ''])) + pretty;
            },
        });
    </script>
</x-filament-panels::page>
