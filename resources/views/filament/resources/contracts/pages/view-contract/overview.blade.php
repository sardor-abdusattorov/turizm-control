@php
    use App\Models\Contract;
    use App\Models\ContractApprover;
@endphp

        <div x-show="tab === 'overview'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="cw-panel">
            @php
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

                    @livewire(\App\Filament\Resources\Contracts\Widgets\ContractApprovalChainTableWidget::class, ['contractId' => $record->id], key('contract-chain-'.$record->id))
                </section>
                @endif

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

                    @livewire(\App\Filament\Resources\Contracts\Widgets\ContractPaymentsTableWidget::class, ['contractId' => $record->id], key('contract-payments-'.$record->id))
                </section>
                @endif
            </div>

            <div class="cw-side">
                <section class="cw-card">
                    <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><h2 class="cw-hd__t">{{ __('app.label.basic_information') }}</h2></div>

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
