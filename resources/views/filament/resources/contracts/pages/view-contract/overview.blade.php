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

                    {{-- The chain itself is a stock Filament table: person,
                         step, status, SLA and comment, with the per-approver
                         history behind the row action. --}}
                    @livewire(\App\Filament\Resources\Contracts\Widgets\ContractApprovalChainTableWidget::class, ['contractId' => $record->id], key('contract-chain-'.$record->id))
                </section>
                @endif

                {{-- Payments only exist once the contract is fully approved by
                     the director, so the progress block is hidden until then. --}}
                @if ($record->status === Contract::STATUS_APPROVED)
                @php
                    $paymentSummary = $this->paymentSummary();
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

                    {{-- Payment ledger as a stock Filament table; filing a new
                         one stays the «Add payment» action in the page header,
                         which owns the remaining-percent validation. --}}
                    @livewire(\App\Filament\Resources\Contracts\Widgets\ContractPaymentsTableWidget::class, ['contractId' => $record->id], key('contract-payments-'.$record->id))
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
                        {{-- The document leaves as its own .docx: with the
                             online editor gone there is nothing to render it
                             in the browser. --}}
                        <div class="cw-file__act">
                            <a href="{{ route('contracts.document.download', ['contract' => $record]) }}" class="cw-btn cw-btn--primary">
                                {!! $ic('heroicon-o-arrow-down-tray', 16) !!}
                                <span>{{ __('app.action.download_document') }}</span>
                            </a>
                        </div>
                    @endif

                    <div class="cw-dets">
                        @foreach ($details as [$icon, $label, $value, $type])
                            @php $hasValue = ! empty($value); @endphp
                            @if ($type === 'contact' && $hasValue)
                                <button type="button" class="cw-row cw-row--link" x-on:click="$wire.mountAction('contactDetails')">
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
