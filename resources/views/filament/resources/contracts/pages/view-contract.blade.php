@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    $statusColor = Contract::getStatusColors()[$record->status] ?? 'gray';
    $statusLabel = Contract::getStatuses()[$record->status] ?? $record->status;
    $current = $record->currentApprover();

    $approvedCount = $record->approvers->where('status', ContractApprover::STATUS_APPROVED)->count();
    $totalCount = $record->approvers->count();
    $progress = $totalCount ? round($approvedCount / $totalCount * 100) : 0;

    $pillFor = fn (string $status): string => [
        ContractApprover::STATUS_APPROVED => 'success',
        ContractApprover::STATUS_REJECTED => 'danger',
        ContractApprover::STATUS_RETURNED => 'info',
        ContractApprover::STATUS_PENDING => 'warning',
        ContractApprover::STATUS_SKIPPED => 'gray',
    ][$status] ?? 'gray';

    $details = [
        [__('app.label.contract_number'), $record->number],
        [__('app.label.contact_single'), $record->contact?->name],
        [__('app.label.contract_template_single'), $record->template?->name],
        [__('app.label.order_type_single'), $record->orderType?->title ?: '—'],
        [__('app.label.responsible'), $record->responsible?->name],
        [__('app.label.amount'), number_format((float) $record->amount, 2, '.', ' ').' '.($record->currency?->short_name ?? '')],
        [__('app.label.signing_date'), $record->signed_at?->format('d.m.Y') ?: '—'],
        [__('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
    ];
@endphp

<x-filament-panels::page>
    <style>
        .cv { display: flex; flex-direction: column; gap: 1.25rem;
            --cv-surface:#fff; --cv-ring:rgba(17,24,39,.08); --cv-text:#111827; --cv-muted:#6b7280;
            --cv-track:#e5e7eb; --cv-divider:rgba(17,24,39,.06); --cv-soft:#f9fafb; }
        .dark .cv { --cv-surface:#18181b; --cv-ring:rgba(255,255,255,.08); --cv-text:#f4f4f5; --cv-muted:#9ca3af;
            --cv-track:rgba(255,255,255,.1); --cv-divider:rgba(255,255,255,.07); --cv-soft:rgba(255,255,255,.04); }

        .cv-card { background:var(--cv-surface); border-radius:1rem; box-shadow:0 0 0 1px var(--cv-ring), 0 1px 2px rgba(0,0,0,.04); overflow:hidden; }
        .cv-card__head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:1rem 1.25rem; border-bottom:1px solid var(--cv-divider); }
        .cv-card__body { padding:1.25rem; }
        .cv-h2 { font-size:.95rem; font-weight:600; color:var(--cv-text); margin:0; display:flex; align-items:center; gap:.5rem; }
        .cv-h2 svg { width:1.1rem; height:1.1rem; color:var(--cv-muted); }
        .cv-count { font-size:.75rem; color:var(--cv-muted); }

        /* status strip */
        .cv-strip { display:flex; flex-wrap:wrap; align-items:center; gap:1.25rem; background:var(--cv-surface);
            border-radius:1rem; padding:.85rem 1.25rem; box-shadow:0 0 0 1px var(--cv-ring); }
        .cv-strip__cur { display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--cv-muted); }
        .cv-strip__cur b { color:var(--cv-text); font-weight:600; }
        .cv-strip__cur svg { width:1.1rem; height:1.1rem; color:#f59e0b; }
        .cv-prog { display:flex; align-items:center; gap:.6rem; margin-left:auto; }
        .cv-prog__num { font-size:.8rem; color:var(--cv-muted); }
        .cv-prog__track { width:9rem; height:.5rem; border-radius:999px; background:var(--cv-track); overflow:hidden; }
        .cv-prog__bar { height:100%; border-radius:999px; background:#3b82f6; transition:width .3s; }

        /* pills */
        .cv-pill { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:999px; font-size:.75rem; font-weight:600; white-space:nowrap; }
        .cv-pill--success{ background:#dcfce7; color:#166534; } .dark .cv-pill--success{ background:rgba(34,197,94,.16); color:#86efac; }
        .cv-pill--warning{ background:#fef9c3; color:#854d0e; } .dark .cv-pill--warning{ background:rgba(234,179,8,.16); color:#fde047; }
        .cv-pill--danger { background:#fee2e2; color:#991b1b; } .dark .cv-pill--danger { background:rgba(239,68,68,.16); color:#fca5a5; }
        .cv-pill--info   { background:#dbeafe; color:#1e40af; } .dark .cv-pill--info   { background:rgba(59,130,246,.16); color:#93c5fd; }
        .cv-pill--gray   { background:#f3f4f6; color:#374151; } .dark .cv-pill--gray   { background:rgba(255,255,255,.08); color:#d1d5db; }

        /* approver cards */
        .cv-appr { display:flex; align-items:center; gap:1rem; padding:1rem 1.25rem; border-bottom:1px solid var(--cv-divider); }
        .cv-appr:last-child { border-bottom:0; }
        .cv-appr--current { background:linear-gradient(90deg, rgba(245,158,11,.07), transparent); box-shadow:inset 3px 0 0 #f59e0b; }
        .cv-ava-wrap { position:relative; flex-shrink:0; }
        .cv-ava { width:3rem; height:3rem; border-radius:999px; object-fit:cover; box-shadow:0 0 0 2px var(--cv-ring); display:block; }
        .cv-ord { position:absolute; bottom:-.25rem; right:-.25rem; width:1.3rem; height:1.3rem; border-radius:999px;
            background:#3b82f6; color:#fff; font-size:.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; box-shadow:0 0 0 2px var(--cv-surface); }
        .cv-appr__id { min-width:0; flex:1; }
        .cv-appr__name { font-size:.95rem; font-weight:600; color:var(--cv-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cv-appr__dept { font-size:.8rem; color:var(--cv-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cv-appr__right { display:flex; flex-direction:column; align-items:flex-end; gap:.3rem; text-align:right; }
        .cv-when { font-size:.72rem; color:var(--cv-muted); }
        .cv-when--over { color:#dc2626; font-weight:600; }
        .cv-cmt { margin-top:.5rem; padding:.4rem .7rem; border-radius:.5rem; background:var(--cv-soft); font-size:.78rem; color:var(--cv-muted); }

        /* document */
        .cv-doc { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
        .cv-doc__ic { width:3.25rem; height:3.25rem; border-radius:.75rem; background:rgba(59,130,246,.1); color:#2563eb; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .cv-doc__ic svg { width:1.7rem; height:1.7rem; }
        .cv-doc__name { font-weight:600; color:var(--cv-text); font-size:.95rem; }
        .cv-doc__meta { font-size:.8rem; color:var(--cv-muted); margin-top:.15rem; }
        .cv-doc__act { margin-left:auto; display:flex; gap:.5rem; }
        .cv-pdf { margin-top:1rem; border:1px solid var(--cv-divider); border-radius:.75rem; overflow:hidden; }
        .cv-pdf iframe { width:100%; height:70vh; background:#fff; display:block; border:0; }
        .cv-empty { display:flex; flex-direction:column; align-items:center; gap:.5rem; padding:2.5rem 0; color:var(--cv-muted); }
        .cv-empty svg { width:2.25rem; height:2.25rem; opacity:.4; }

        /* details table */
        .cv-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .cv-table tr:nth-child(odd) td { background:var(--cv-soft); }
        .cv-table td { padding:.7rem 1rem; border-bottom:1px solid var(--cv-divider); color:var(--cv-text); }
        .cv-table td:first-child { width:38%; color:var(--cv-muted); font-weight:500; }
        .cv-table tr:last-child td { border-bottom:0; }

        /* history */
        .cv-hist { display:flex; flex-direction:column; gap:1.1rem; }
        .cv-hi { display:flex; gap:.75rem; }
        .cv-hi__ic { width:2rem; height:2rem; border-radius:999px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .cv-hi__ic svg { width:1rem; height:1rem; }
        .cv-hi__ic--success{ background:#dcfce7; color:#16a34a;} .dark .cv-hi__ic--success{ background:rgba(34,197,94,.16);}
        .cv-hi__ic--danger { background:#fee2e2; color:#dc2626;} .dark .cv-hi__ic--danger { background:rgba(239,68,68,.16);}
        .cv-hi__ic--warning{ background:#fef9c3; color:#ca8a04;} .dark .cv-hi__ic--warning{ background:rgba(234,179,8,.16);}
        .cv-hi__ic--info   { background:#dbeafe; color:#2563eb;} .dark .cv-hi__ic--info   { background:rgba(59,130,246,.16);}
        .cv-hi__ic--gray   { background:#f3f4f6; color:#6b7280;} .dark .cv-hi__ic--gray   { background:rgba(255,255,255,.08);}
        .cv-hi__desc { font-size:.85rem; font-weight:500; color:var(--cv-text); }
        .cv-hi__meta { font-size:.75rem; color:var(--cv-muted); margin-top:.1rem; }
    </style>

    <div class="cv">
        {{-- Status strip --}}
        <div class="cv-strip">
            <span class="cv-pill cv-pill--{{ $statusColor }}">{{ $statusLabel }}</span>

            @if ($current && $record->status === Contract::STATUS_IN_REVIEW)
                <span class="cv-strip__cur">
                    @svg('heroicon-o-clock')
                    {{ __('app.label.current_step') }}: <b>{{ $current->user?->name }}</b>
                </span>
            @endif

            @if ($totalCount > 0)
                <div class="cv-prog">
                    <span class="cv-prog__num">{{ $approvedCount }} / {{ $totalCount }}</span>
                    <span class="cv-prog__track"><span class="cv-prog__bar" style="width: {{ $progress }}%"></span></span>
                </div>
            @endif
        </div>

        {{-- Document --}}
        <section class="cv-card">
            <div class="cv-card__head">
                <h2 class="cv-h2">@svg('heroicon-o-document-text') {{ __('app.label.attached_document') }}</h2>
            </div>
            <div class="cv-card__body">
                @if ($record->documentExists())
                    <div class="cv-doc">
                        <div class="cv-doc__ic">@svg('heroicon-o-document-text')</div>
                        <div>
                            <div class="cv-doc__name">{{ $record->number }}.docx</div>
                            <div class="cv-doc__meta">{{ $this->documentSizeLabel() }} · {{ $record->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="cv-doc__act">
                            <x-filament::button tag="a" :href="$this->editorUrl($record->canBeEditedBy() ? 'edit' : 'view')" icon="heroicon-o-pencil-square" color="primary" size="sm">
                                {{ __('app.action.open_editor') }}
                            </x-filament::button>
                            @if ($record->status === Contract::STATUS_APPROVED)
                                <x-filament::button tag="a" :href="route('contracts.pdf.download', ['contract' => $record])" icon="heroicon-o-document-arrow-down" color="gray" size="sm">PDF</x-filament::button>
                            @endif
                        </div>
                    </div>

                    @if ($url = $this->pdfPreviewUrl())
                        <div class="cv-pdf"><iframe src="{{ $url }}" loading="lazy"></iframe></div>
                    @endif
                @else
                    <div class="cv-empty">@svg('heroicon-o-document') <span>{{ __('app.label.document_not_ready') }}</span></div>
                @endif
            </div>
        </section>

        {{-- Approval chain --}}
        <section class="cv-card">
            <div class="cv-card__head">
                <h2 class="cv-h2">@svg('heroicon-o-users') {{ __('app.label.approval_chain') }}</h2>
                @if ($totalCount > 0)<span class="cv-count">{{ $approvedCount }} / {{ $totalCount }}</span>@endif
            </div>

            @if ($record->approvers->isEmpty())
                <div class="cv-card__body"><p style="font-size:.85rem;color:var(--cv-muted)">{{ __('app.label.no_approvers') }}</p></div>
            @else
                @foreach ($record->approvers as $approver)
                    @php $isCurrent = $this->isCurrentApprover($approver); @endphp
                    <div class="cv-appr {{ $isCurrent ? 'cv-appr--current' : '' }}">
                        <div class="cv-ava-wrap">
                            <img class="cv-ava" src="{{ $this->approverAvatar($approver) }}" alt="">
                            <span class="cv-ord">{{ $approver->order }}</span>
                        </div>
                        <div class="cv-appr__id">
                            <div class="cv-appr__name">{{ $approver->user?->name }}</div>
                            <div class="cv-appr__dept">{{ $approver->user?->department?->name }}</div>
                            @if ($approver->comment)
                                <div class="cv-cmt">{{ $approver->comment }}</div>
                            @endif
                        </div>
                        <div class="cv-appr__right">
                            <span class="cv-pill cv-pill--{{ $pillFor($approver->status) }}">{{ ContractApprover::getStatuses()[$approver->status] ?? $approver->status }}</span>
                            @if ($approver->acted_at)
                                <span class="cv-when">{{ $approver->acted_at->format('d.m.Y H:i') }}</span>
                            @elseif ($approver->isPending() && $approver->due_at)
                                <span class="cv-when {{ $approver->isOverdue() ? 'cv-when--over' : '' }}">⏳ {{ $approver->due_at->format('d.m.Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </section>

        {{-- Details --}}
        <section class="cv-card">
            <div class="cv-card__head">
                <h2 class="cv-h2">@svg('heroicon-o-clipboard-document-list') {{ __('app.label.basic_information') }}</h2>
            </div>
            <table class="cv-table">
                @foreach ($details as [$label, $value])
                    <tr><td>{{ $label }}</td><td>{{ $value ?: '—' }}</td></tr>
                @endforeach
            </table>
        </section>

        {{-- Execution history --}}
        <section class="cv-card">
            <div class="cv-card__head">
                <h2 class="cv-h2">@svg('heroicon-o-clock') {{ __('app.label.execution_history') }}</h2>
            </div>
            <div class="cv-card__body">
                @php $activities = $this->getActivities(); $prevKey = null; @endphp
                @if ($activities->isEmpty())
                    <p style="font-size:.85rem;color:var(--cv-muted)">{{ __('app.label.no_history') }}</p>
                @else
                    <div class="cv-hist">
                        @foreach ($activities as $activity)
                            @php $key = ($activity->description ?? '').'|'.$activity->created_at?->format('YmdHi'); @endphp
                            @continue($key === $prevKey)
                            @php $prevKey = $key; $av = $this->activityVisual($activity->event ?? ''); @endphp
                            <div class="cv-hi">
                                <span class="cv-hi__ic cv-hi__ic--{{ $av['color'] }}">@svg($av['icon'])</span>
                                <div>
                                    <div class="cv-hi__desc">{{ $activity->description ?: $activity->event }}</div>
                                    <div class="cv-hi__meta">{{ $activity->causer?->name ?? __('app.label.system') }} · {{ $activity->created_at?->format('d.m.Y H:i') }}</div>
                                    @if ($comment = data_get($activity->properties, 'comment'))
                                        <div class="cv-cmt" style="margin-top:.4rem">{{ $comment }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
