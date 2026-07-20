@php
    use App\Models\Contract;
    use App\Models\ContractApprover;
@endphp
        {{-- Overview --}}
        <div x-show="tab === 'overview'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="cw-panel">
            @php
                // A legacy (already-signed) contract never had a chain — the
                // empty card would only take the main column hostage. Filed
                // approvals keep their history visible.
                $chainCardVisible = $active->isNotEmpty()
                    || $historical->isNotEmpty()
                    || $record->status !== Contract::STATUS_APPROVED;
            @endphp
            <div class="cw-cols">
                <div class="cw-main">
                @if ($chainCardVisible)
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
                                @if ($reviewingCount > 0)<span class="cw-lg"><i style="background:#2563eb;"></i>{{ $reviewingCount }} {{ __('app.contract_approver.status.pending') }}</span>@endif
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
                                        @if ($ap->system_comment)<div class="cw-cmt" style="background:rgba(251,146,60,.10);border-color:rgba(251,146,60,.32);color:#c2410c;font-weight:550;">{{ $ap->systemNoteLabel() }}</div>@endif
                                    </div>
                                    <button type="button" class="cw-eye" title="{{ __('app.label.view_history') }}" x-on:click="$wire.mountAction('approverDetails', { user: {{ $ap->user_id }} })">{!! $ic('heroicon-o-eye', 16) !!}</button>
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
                                    <button type="button" class="cw-eye" title="{{ __('app.label.view_history') }}" x-on:click="$wire.mountAction('approverDetails', { user: {{ $h->user_id }} })">{!! $ic('heroicon-o-eye', 16) !!}</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
                @endif

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
                                <span class="cw-prog__count"><b>{{ format_percent($paidPercent) }}%</b> / 100%</span>
                                @if ($remainingPercent > 0)
                                    <span class="cw-prog__sub">{{ __('app.label.remaining_to_pay', ['percent' => format_percent($remainingPercent)]) }}</span>
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
                                @php $paymentFiles = $this->paymentScreenshotFiles($payment); @endphp
                                <div class="cw-step" style="align-items:center;">
                                    <div class="cw-node" style="background:rgba(34,197,94,.12);">
                                        <span class="cw-badge cw-badge--approved">{!! $ic('heroicon-s-check-circle', 13) !!}</span>
                                    </div>
                                    <div class="cw-step__bd">
                                        <div class="cw-step__nm">
                                            {{ format_percent((float) $payment->percent) }}%
                                            <span class="cw-ord">{{ $payment->paid_at?->format('d.m.Y') }}</span>
                                        </div>
                                        <div class="cw-step__dp">
                                            {{ __('app.label.created_by') }}: {{ $payment->creator?->name ?? __('app.label.system') }}
                                            · {{ $payment->created_at?->format('d.m.Y H:i') }}
                                        </div>
                                    </div>
                                    @foreach ($paymentFiles as $file)
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="cw-eye" title="{{ $file['pdf'] ? $file['name'] : __('app.label.open_screenshot') }}">{!! $ic($file['pdf'] ? 'heroicon-o-document-text' : 'heroicon-o-photo', 16) !!}</a>
                                    @endforeach
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
                            $editUrl = $this->editorUrl($record->documentEditableBy() ? 'edit' : 'view');
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
                        @foreach ($details as [$icon, $label, $value, $type])
                            @php $hasValue = ! empty($value); @endphp
                            @if ($type === 'contact' && $hasValue)
                                <button type="button" class="cw-row cw-row--link" @click="contactOpen = true">
                                    <span class="cw-row__k"><span class="cw-row__ic">{!! $ic($icon, 16) !!}</span><span class="cw-row__lb">{{ $label }}</span></span>
                                    <span class="cw-row__v"><span class="cw-row__vl">{{ $value }} {!! $ic('heroicon-m-arrow-top-right-on-square', 13) !!}</span></span>
                                </button>
                            @elseif ($type === 'status')
                                <div class="cw-row">
                                    <span class="cw-row__k"><span class="cw-row__ic">{!! $ic($icon, 16) !!}</span><span class="cw-row__lb">{{ $label }}</span></span>
                                    <span class="cw-row__v"><span class="cw-pill cw-pill--{{ $statusColor }}" style="padding:.32rem .7rem .32rem .55rem;font-size:.8rem;">{{ $value }}</span></span>
                                </div>
                            @else
                                <div class="cw-row">
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
                </section>
            </div>
        </div>
        </div>
