@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    $statusColor = Contract::getStatusColors()[$record->status] ?? 'gray';
    $statusLabel = Contract::getStatuses()[$record->status] ?? $record->status;
    $current = $record->currentApprover();

    $active = $record->activeApprovers;
    $historical = $record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]);
    $approvedCount = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    $totalCount = $active->count();
    $progress = $totalCount ? round($approvedCount / $totalCount * 100) : 0;

    $pillFor = fn (string $status): string => ContractApprover::getStatusColors()[$status] ?? 'gray';

    $ic = fn (string $name, int $size = 18) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $details = [
        ['heroicon-o-hashtag', __('app.label.contract_number'), $record->number],
        ['heroicon-o-building-office-2', __('app.label.contact_single'), $record->contact?->name],
        ['heroicon-o-document-duplicate', __('app.label.contract_template_single'), $record->template?->name],
        ['heroicon-o-tag', __('app.label.order_type_single'), $record->orderType?->title ?: '—'],
        ['heroicon-o-user', __('app.label.responsible'), $record->responsible?->name],
        ['heroicon-o-banknotes', __('app.label.amount'), number_format((float) $record->amount, 2, '.', ' ').' '.($record->currency?->short_name ?? '')],
        ['heroicon-o-calendar-days', __('app.label.signing_date'), $record->signed_at?->format('d.m.Y') ?: '—'],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
    ];
@endphp

<x-filament-panels::page>
    <style>
        .cw{ display:flex; flex-direction:column; gap:1.25rem;
            --s:#fff; --r:rgba(17,24,39,.07); --t:#0f172a; --m:#64748b; --m2:#94a3b8; --d:rgba(17,24,39,.06);
            --soft:#f8fafc; --track:#eef2f6; --accent:#3b82f6; }
        .dark .cw{ --s:#18181b; --r:rgba(255,255,255,.08); --t:#f4f4f5; --m:#a1a1aa; --m2:#71717a; --d:rgba(255,255,255,.07);
            --soft:rgba(255,255,255,.03); --track:rgba(255,255,255,.09); --accent:#60a5fa; }

        .cw-card{ background:var(--s); border-radius:1rem; box-shadow:0 0 0 1px var(--r), 0 1px 2px rgba(0,0,0,.04), 0 4px 12px -8px rgba(0,0,0,.12); overflow:hidden; }
        .cw-hd{ display:flex; align-items:center; gap:.6rem; padding:.95rem 1.25rem; border-bottom:1px solid var(--d); }
        .cw-hd__ic{ color:var(--m); display:inline-flex; }
        .cw-hd__t{ font-size:.9rem; font-weight:600; color:var(--t); margin:0; flex:1; }
        .cw-hd__c{ font-size:.72rem; font-weight:600; color:var(--m); background:var(--soft); padding:.15rem .55rem; border-radius:999px; }
        .cw-bd{ padding:1.25rem; }

        /* hero strip */
        .cw-hero{ display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap; background:var(--s);
            border-radius:1rem; padding:1rem 1.25rem; box-shadow:0 0 0 1px var(--r), 0 1px 2px rgba(0,0,0,.04); }
        .cw-hero__cur{ display:flex; align-items:center; gap:.6rem; }
        .cw-hero__cur img{ width:2rem; height:2rem; border-radius:999px; object-fit:cover; box-shadow:0 0 0 2px var(--r); }
        .cw-hero__lbl{ font-size:.72rem; color:var(--m2); text-transform:uppercase; letter-spacing:.03em; }
        .cw-hero__nm{ font-size:.85rem; font-weight:600; color:var(--t); }
        .cw-hero__prog{ display:flex; align-items:center; gap:.7rem; margin-left:auto; }
        .cw-hero__num{ font-size:.8rem; font-weight:600; color:var(--m); white-space:nowrap; }
        .cw-bar{ width:8.5rem; height:.45rem; border-radius:999px; background:var(--track); overflow:hidden; }
        .cw-bar>span{ display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,#3b82f6,#2563eb); transition:width .4s ease; }

        /* pills */
        .cw-pill{ display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .6rem; border-radius:999px; font-size:.72rem; font-weight:600; line-height:1; white-space:nowrap; }
        .cw-pill--lg{ padding:.32rem .8rem; font-size:.78rem; }
        .cw-pill--success{ background:#dcfce7; color:#15803d;} .dark .cw-pill--success{ background:rgba(34,197,94,.16); color:#86efac;}
        .cw-pill--warning{ background:#fef3c7; color:#b45309;} .dark .cw-pill--warning{ background:rgba(245,158,11,.16); color:#fcd34d;}
        .cw-pill--danger { background:#fee2e2; color:#b91c1c;} .dark .cw-pill--danger { background:rgba(239,68,68,.16); color:#fca5a5;}
        .cw-pill--info   { background:#dbeafe; color:#1d4ed8;} .dark .cw-pill--info   { background:rgba(59,130,246,.16); color:#93c5fd;}
        .cw-pill--gray   { background:#f1f5f9; color:#475569;} .dark .cw-pill--gray   { background:rgba(255,255,255,.07); color:#cbd5e1;}

        /* approver rows */
        .cw-ap{ display:flex; align-items:center; gap:1rem; padding:1rem 1.25rem; border-bottom:1px solid var(--d); transition:background .15s; }
        .cw-ap:last-child{ border-bottom:0; }
        .cw-ap--cur{ background:linear-gradient(90deg, rgba(245,158,11,.06), transparent 60%); box-shadow:inset 3px 0 0 #f59e0b; }
        .cw-ava{ position:relative; flex-shrink:0; }
        .cw-ava img{ width:3rem; height:3rem; border-radius:999px; object-fit:cover; box-shadow:0 0 0 2px var(--r); display:block; }
        .cw-ord{ position:absolute; bottom:-.2rem; right:-.2rem; min-width:1.25rem; height:1.25rem; padding:0 .25rem; border-radius:999px;
            background:var(--accent); color:#fff; font-size:.68rem; font-weight:700; display:flex; align-items:center; justify-content:center; box-shadow:0 0 0 2px var(--s); }
        .cw-ap__id{ min-width:0; flex:1; }
        .cw-ap__nm{ font-size:.92rem; font-weight:600; color:var(--t); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cw-ap__dp{ font-size:.78rem; color:var(--m); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:.05rem; }
        .cw-ap__rt{ display:flex; flex-direction:column; align-items:flex-end; gap:.35rem; text-align:right; flex-shrink:0; }
        .cw-when{ font-size:.72rem; color:var(--m2); display:inline-flex; align-items:center; gap:.25rem; }
        .cw-when--over{ color:#dc2626; font-weight:600; }
        .cw-cmt{ margin-top:.45rem; padding:.4rem .65rem; border-radius:.5rem; background:var(--soft); font-size:.78rem; color:var(--m); }

        .cw-more{ border-top:1px solid var(--d); }
        .cw-more>summary{ list-style:none; padding:.7rem 1.25rem; cursor:pointer; font-size:.78rem; color:var(--m); display:flex; align-items:center; gap:.4rem; user-select:none; }
        .cw-more>summary::-webkit-details-marker{ display:none; }
        .cw-more[open]>summary{ color:var(--t); }
        .cw-more .cw-ap{ opacity:.65; }

        /* document */
        .cw-doc{ display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
        .cw-doc__ic{ width:3.25rem; height:3.25rem; border-radius:.85rem; background:linear-gradient(135deg, rgba(59,130,246,.14), rgba(37,99,235,.08)); color:#2563eb; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dark .cw-doc__ic{ color:#60a5fa; }
        .cw-doc__nm{ font-weight:600; color:var(--t); font-size:.92rem; }
        .cw-doc__mt{ font-size:.78rem; color:var(--m); margin-top:.15rem; }
        .cw-doc__act{ margin-left:auto; display:flex; gap:.5rem; }
        .cw-pdf{ margin-top:1.1rem; border:1px solid var(--d); border-radius:.85rem; overflow:hidden; }
        .cw-pdf iframe{ width:100%; height:70vh; background:#fff; display:block; border:0; }
        .cw-empty{ display:flex; flex-direction:column; align-items:center; gap:.6rem; padding:2.75rem 0; color:var(--m2); }
        .cw-empty span{ font-size:.82rem; }

        /* details grid */
        .cw-grid{ display:grid; grid-template-columns:1fr; }
        @media(min-width:640px){ .cw-grid{ grid-template-columns:1fr 1fr; } }
        .cw-row{ display:flex; align-items:center; gap:.7rem; padding:.85rem 1.25rem; border-bottom:1px solid var(--d); }
        .cw-row__ic{ color:var(--m2); display:inline-flex; flex-shrink:0; }
        .cw-row__lb{ font-size:.78rem; color:var(--m); width:42%; flex-shrink:0; }
        .cw-row__vl{ font-size:.85rem; font-weight:500; color:var(--t); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        /* history */
        .cw-hist{ display:flex; flex-direction:column; gap:1.1rem; }
        .cw-hi{ display:flex; gap:.8rem; }
        .cw-hi__ic{ width:2.1rem; height:2.1rem; border-radius:999px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .cw-hi__ic--success{ background:#dcfce7; color:#16a34a;} .dark .cw-hi__ic--success{ background:rgba(34,197,94,.16);}
        .cw-hi__ic--danger { background:#fee2e2; color:#dc2626;} .dark .cw-hi__ic--danger { background:rgba(239,68,68,.16);}
        .cw-hi__ic--warning{ background:#fef3c7; color:#d97706;} .dark .cw-hi__ic--warning{ background:rgba(245,158,11,.16);}
        .cw-hi__ic--info   { background:#dbeafe; color:#2563eb;} .dark .cw-hi__ic--info   { background:rgba(59,130,246,.16);}
        .cw-hi__ic--gray   { background:#f1f5f9; color:#64748b;} .dark .cw-hi__ic--gray   { background:rgba(255,255,255,.07);}
        .cw-hi__ds{ font-size:.85rem; font-weight:500; color:var(--t); }
        .cw-hi__mt{ font-size:.74rem; color:var(--m2); margin-top:.12rem; }
    </style>

    <div class="cw">
        {{-- Hero --}}
        <div class="cw-hero">
            <span class="cw-pill cw-pill--lg cw-pill--{{ $statusColor }}">{{ $statusLabel }}</span>

            @if ($current && $record->status === Contract::STATUS_IN_REVIEW)
                <div class="cw-hero__cur">
                    <img src="{{ $this->approverAvatar($current) }}" alt="">
                    <div>
                        <div class="cw-hero__lbl">{{ __('app.label.current_step') }}</div>
                        <div class="cw-hero__nm">{{ $current->user?->name }}</div>
                    </div>
                </div>
            @endif

            @if ($totalCount > 0)
                <div class="cw-hero__prog">
                    <span class="cw-hero__num">{{ $approvedCount }} / {{ $totalCount }}</span>
                    <span class="cw-bar"><span style="width: {{ $progress }}%"></span></span>
                </div>
            @endif
        </div>

        {{-- Document --}}
        <section class="cw-card">
            <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-document-text') !!}</span><h2 class="cw-hd__t">{{ __('app.label.attached_document') }}</h2></div>
            <div class="cw-bd">
                @if ($record->documentExists())
                    <div class="cw-doc">
                        <div class="cw-doc__ic">{!! $ic('heroicon-o-document-text', 28) !!}</div>
                        <div>
                            <div class="cw-doc__nm">{{ $record->number }}.docx</div>
                            <div class="cw-doc__mt">{{ $this->documentSizeLabel() }} · {{ $record->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="cw-doc__act">
                            <x-filament::button tag="a" :href="$this->editorUrl($record->canBeEditedBy() ? 'edit' : 'view')" icon="heroicon-o-pencil-square" color="primary" size="sm">
                                {{ __('app.action.open_editor') }}
                            </x-filament::button>
                            @if ($record->status === Contract::STATUS_APPROVED)
                                <x-filament::button tag="a" :href="route('contracts.pdf.download', ['contract' => $record])" icon="heroicon-o-document-arrow-down" color="gray" size="sm">PDF</x-filament::button>
                            @endif
                        </div>
                    </div>
                    @if ($url = $this->pdfPreviewUrl())
                        <div class="cw-pdf"><iframe src="{{ $url }}" loading="lazy"></iframe></div>
                    @endif
                @else
                    <div class="cw-empty">{!! $ic('heroicon-o-document', 36) !!}<span>{{ __('app.label.document_not_ready') }}</span></div>
                @endif
            </div>
        </section>

        {{-- Approval chain --}}
        <section class="cw-card">
            <div class="cw-hd">
                <span class="cw-hd__ic">{!! $ic('heroicon-o-users') !!}</span>
                <h2 class="cw-hd__t">{{ __('app.label.approval_chain') }}</h2>
                @if ($totalCount > 0)<span class="cw-hd__c">{{ $approvedCount }}/{{ $totalCount }}</span>@endif
            </div>

            @if ($active->isEmpty() && $historical->isEmpty())
                <div class="cw-bd"><p style="font-size:.85rem;color:var(--m)">{{ __('app.label.no_approvers') }}</p></div>
            @else
                @foreach ($active as $ap)
                    <div class="cw-ap {{ $this->isCurrentApprover($ap) ? 'cw-ap--cur' : '' }}">
                        <div class="cw-ava">
                            <img src="{{ $this->approverAvatar($ap) }}" alt="">
                            <span class="cw-ord">{{ $ap->order }}</span>
                        </div>
                        <div class="cw-ap__id">
                            <div class="cw-ap__nm">{{ $ap->user?->name }}</div>
                            <div class="cw-ap__dp">{{ $ap->user?->department?->name }}</div>
                            @if ($ap->comment)<div class="cw-cmt">{{ $ap->comment }}</div>@endif
                        </div>
                        <div class="cw-ap__rt">
                            <span class="cw-pill cw-pill--{{ $pillFor($ap->status) }}">{{ ContractApprover::getStatuses()[$ap->status] ?? $ap->status }}</span>
                            @if ($ap->acted_at)
                                <span class="cw-when">{{ $ap->acted_at->format('d.m.Y H:i') }}</span>
                            @elseif ($ap->isPending() && $ap->due_at)
                                <span class="cw-when {{ $ap->isOverdue() ? 'cw-when--over' : '' }}">{!! $ic('heroicon-m-clock', 13) !!} {{ $ap->due_at->format('d.m.Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($historical->isNotEmpty())
                    <details class="cw-more">
                        <summary>{!! $ic('heroicon-m-chevron-down', 14) !!} {{ __('app.label.previous_attempts') }} ({{ $historical->count() }})</summary>
                        @foreach ($historical as $ap)
                            <div class="cw-ap">
                                <div class="cw-ava">
                                    <img src="{{ $this->approverAvatar($ap) }}" alt="">
                                    <span class="cw-ord">{{ $ap->order }}</span>
                                </div>
                                <div class="cw-ap__id">
                                    <div class="cw-ap__nm">{{ $ap->user?->name }}</div>
                                    <div class="cw-ap__dp">{{ $ap->user?->department?->name }}</div>
                                </div>
                                <div class="cw-ap__rt">
                                    <span class="cw-pill cw-pill--gray">{{ ContractApprover::getStatuses()[$ap->status] ?? $ap->status }}</span>
                                    @if ($ap->acted_at)<span class="cw-when">{{ $ap->acted_at->format('d.m.Y H:i') }}</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </details>
                @endif
            @endif
        </section>

        {{-- Details --}}
        <section class="cw-card">
            <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><h2 class="cw-hd__t">{{ __('app.label.basic_information') }}</h2></div>
            <div class="cw-grid">
                @foreach ($details as [$icon, $label, $value])
                    <div class="cw-row">
                        <span class="cw-row__ic">{!! $ic($icon, 16) !!}</span>
                        <span class="cw-row__lb">{{ $label }}</span>
                        <span class="cw-row__vl">{{ $value ?: '—' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- History --}}
        <section class="cw-card">
            <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clock') !!}</span><h2 class="cw-hd__t">{{ __('app.label.execution_history') }}</h2></div>
            <div class="cw-bd">
                @php $activities = $this->getActivities(); $prev = null; @endphp
                @if ($activities->isEmpty())
                    <p style="font-size:.85rem;color:var(--m)">{{ __('app.label.no_history') }}</p>
                @else
                    <div class="cw-hist">
                        @foreach ($activities as $a)
                            @php $k = ($a->description ?? '').'|'.$a->created_at?->format('YmdHi'); @endphp
                            @continue($k === $prev)
                            @php $prev = $k; $v = $this->activityVisual($a->event ?? ''); @endphp
                            <div class="cw-hi">
                                <span class="cw-hi__ic cw-hi__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 16) !!}</span>
                                <div>
                                    <div class="cw-hi__ds">{{ $a->description ?: $a->event }}</div>
                                    <div class="cw-hi__mt">{{ $a->causer?->name ?? __('app.label.system') }} · {{ $a->created_at?->format('d.m.Y H:i') }}</div>
                                    @if ($cmt = data_get($a->properties, 'comment'))<div class="cw-cmt" style="margin-top:.4rem">{{ $cmt }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
