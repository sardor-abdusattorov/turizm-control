@php
    use App\Models\Contract;
    use App\Models\ContractApprover;
    use Illuminate\Support\Carbon;

    $statusColor = Contract::getStatusColors()[$record->status] ?? 'gray';
    $statusLabel = Contract::getStatuses()[$record->status] ?? $record->status;
    $current = $record->currentApprover();
    $hero = $this->heroContext();

    $active = $record->activeApprovers;
    $historical = $record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]);
    $approvedCount = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    $totalCount = $active->count();
    $progress = $totalCount ? round($approvedCount / $totalCount * 100) : 0;

    $allApprovers = $active->concat($historical);

    $pillFor = fn (string $status): string => ContractApprover::getStatusColors()[$status] ?? 'gray';
    $statusName = fn (string $status): string => ContractApprover::getStatuses()[$status] ?? $status;
    $ic = fn (string $name, int $size = 18) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

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
        [x-cloak]{ display:none !important; }
        .cw{ display:flex; flex-direction:column; gap:1.1rem;
            --s:#fff; --r:rgba(15,20,25,.08); --t:#0f1419; --m:#57606a; --m2:#8b949e; --d:rgba(15,20,25,.07);
            --soft:#f8fafc; --track:#e6ebf1; --accent:#6366f1; }
        .dark .cw{ --s:#18181b; --r:rgba(255,255,255,.08); --t:#f0f6fc; --m:#9aa4b2; --m2:#6e7681; --d:rgba(255,255,255,.07);
            --soft:rgba(255,255,255,.03); --track:rgba(255,255,255,.10); --accent:#818cf8; }

        .cw-card{ background:var(--s); border-radius:1rem; box-shadow:0 0 0 1px var(--r), 0 1px 3px rgba(15,20,25,.06), 0 1px 2px rgba(15,20,25,.04); overflow:hidden; }
        .cw-hd{ display:flex; align-items:center; gap:.6rem; padding:.9rem 1.25rem; border-bottom:1px solid var(--d); }
        .cw-hd__ic{ color:var(--m2); display:inline-flex; }
        .cw-hd__t{ font-size:0.88rem; font-weight:650; color:var(--t); margin:0; flex:1; letter-spacing:-.01em; }
        .cw-hd__c{ font-size:0.724rem; font-weight:600; color:var(--m); background:var(--soft); padding:.18rem .6rem; border-radius:999px; }
        .cw-bd{ padding:1.25rem; }

        /* layout */
        .cw-cols{ display:grid; grid-template-columns:1fr; gap:1.1rem; align-items:start; }
        @media(min-width:1024px){ .cw-cols{ grid-template-columns:minmax(0,1.65fr) minmax(0,1fr); } }
        .cw-main,.cw-side{ display:flex; flex-direction:column; gap:1.1rem; min-width:0; }
        .cw-panel{ display:flex; flex-direction:column; gap:1.1rem; }
        .cw-tabs{ display:flex; gap:.15rem; }
        .cw-tab{ display:flex; align-items:center; gap:.4rem; padding:.6rem .95rem; font-size:0.854rem; font-weight:600; color:var(--m); background:transparent; border:0; border-radius:.7rem; cursor:pointer; transition:all .15s; }
        .cw-tab:hover{ color:var(--t); background:var(--soft); }
        .cw-tab--active{ color:var(--accent); background:var(--s); box-shadow:0 0 0 1px var(--r), 0 1px 2px rgba(0,0,0,.05); }
        .cw-tab__c{ font-size:0.684rem; font-weight:700; color:var(--m); background:var(--soft); border-radius:999px; padding:.05rem .4rem; }
        .cw-tab--active .cw-tab__c{ background:rgba(99,102,241,.14); color:var(--accent); }

        /* hero */
        .cw-hero{ position:relative; display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap; border-radius:1.1rem; padding:1.35rem 1.5rem 1.35rem 1.65rem; overflow:hidden; box-shadow:0 0 0 1px var(--r), 0 1px 2px rgba(15,20,25,.05); }
        .cw-hero::before{ content:''; position:absolute; left:0; top:0; bottom:0; width:4px; }
        .cw-hero--gray{ background:linear-gradient(120deg,rgba(148,163,184,.08),var(--s) 52%); } .cw-hero--gray::before{ background:#94a3b8; }
        .cw-hero--warning{ background:linear-gradient(120deg,rgba(251,146,60,.09),var(--s) 52%); } .cw-hero--warning::before{ background:#fb923c; }
        .dark .cw-hero--warning{ background:linear-gradient(120deg,rgba(251,146,60,.10),var(--s) 52%); }
        .cw-hero--success{ background:linear-gradient(120deg,rgba(34,197,94,.08),var(--s) 52%); } .cw-hero--success::before{ background:#22c55e; }
        .dark .cw-hero--success{ background:linear-gradient(120deg,rgba(34,197,94,.10),var(--s) 52%); }
        .cw-hero--danger{ background:linear-gradient(120deg,rgba(239,68,68,.08),var(--s) 52%); } .cw-hero--danger::before{ background:#ef4444; }
        .dark .cw-hero--danger{ background:linear-gradient(120deg,rgba(239,68,68,.10),var(--s) 52%); }
        .cw-hero--info{ background:linear-gradient(120deg,rgba(99,102,241,.08),var(--s) 52%); } .cw-hero--info::before{ background:#6366f1; }
        .cw-hero__l{ display:flex; flex-direction:column; gap:.5rem; min-width:0; }
        .cw-hero__msg{ font-size:1.02rem; font-weight:650; color:var(--t); letter-spacing:-.01em; }
        .cw-hero__sla{ font-size:0.765rem; font-weight:650; color:#dc2626; display:inline-flex; align-items:center; gap:.3rem; }
        .cw-hero__due{ font-size:0.765rem; color:var(--m); display:inline-flex; align-items:center; gap:.3rem; }
        .cw-hero__r{ display:flex; align-items:center; gap:1.4rem; margin-left:auto; }
        .cw-hero__cur{ display:flex; align-items:center; gap:.6rem; }
        .cw-hero__cur img{ width:2.4rem; height:2.4rem; border-radius:999px; object-fit:cover; box-shadow:0 0 0 2px #fb923c; }
        .cw-hero__lbl{ font-size:0.664rem; color:var(--m2); text-transform:uppercase; letter-spacing:.05em; }
        .cw-hero__nm{ font-size:0.854rem; font-weight:650; color:var(--t); }
        .cw-ring{ position:relative; width:3.7rem; height:3.7rem; flex-shrink:0; border-radius:50%; background:conic-gradient(#10b981 calc(var(--p)*1%), var(--track) 0); display:flex; align-items:center; justify-content:center; }
        .cw-ring::before{ content:''; position:absolute; inset:.32rem; border-radius:50%; background:var(--s); }
        .cw-ring span{ position:relative; font-size:0.765rem; font-weight:750; color:var(--t); }

        /* pills */
        .cw-pill{ display:inline-flex; align-items:center; gap:.35rem; padding:.24rem .62rem .24rem .5rem; border-radius:999px; font-size:0.724rem; font-weight:650; line-height:1; white-space:nowrap; }
        .cw-pill::before{ content:''; width:.42rem; height:.42rem; border-radius:50%; background:currentColor; flex-shrink:0; }
        .cw-pill--lg{ padding:.36rem .9rem .36rem .7rem; font-size:0.805rem; }
        .cw-pill--lg::before{ width:.5rem; height:.5rem; }
        .cw-pill--success{ background:#dcfce7; color:#15803d;} .dark .cw-pill--success{ background:rgba(34,197,94,.16); color:#86efac;}
        .cw-pill--warning{ background:#ffedd5; color:#c2410c;} .dark .cw-pill--warning{ background:rgba(251,146,60,.16); color:#fdba74;}
        .cw-pill--danger { background:#fee2e2; color:#b91c1c;} .dark .cw-pill--danger { background:rgba(239,68,68,.16); color:#fca5a5;}
        .cw-pill--info   { background:#dbeafe; color:#1d4ed8;} .dark .cw-pill--info   { background:rgba(59,130,246,.16); color:#93c5fd;}
        .cw-pill--gray   { background:#f1f5f9; color:#64748b;} .dark .cw-pill--gray   { background:rgba(255,255,255,.07); color:#cbd5e1;}

        /* approval chain — vertical timeline */
        .cw-chain{ padding:.9rem 1.25rem 1.1rem; }
        .cw-step{ position:relative; display:flex; align-items:flex-start; gap:.9rem; padding:.6rem .65rem; border-radius:.85rem; transition:background .15s; }
        .cw-step:not(:last-child){ margin-bottom:.15rem; }
        .cw-step:not(:last-child)::before{ content:''; position:absolute; left:2rem; top:2.95rem; bottom:-.45rem; width:2px; background:var(--track); border-radius:2px; }
        .cw-step--approved:not(:last-child)::before{ background:linear-gradient(#34d399, var(--track)); }
        .cw-step--current{ background:linear-gradient(90deg, rgba(251,146,60,.08), transparent 70%); }
        @keyframes cwPulse { 0%{ box-shadow:0 0 0 0 rgba(251,146,60,.55);} 70%{ box-shadow:0 0 0 8px rgba(251,146,60,0);} 100%{ box-shadow:0 0 0 0 rgba(251,146,60,0);} }
        @media (prefers-reduced-motion: no-preference){ .cw-step--current .cw-badge--current{ animation:cwPulse 1.8s ease-out infinite; } }
        .cw-step:hover{ background:var(--soft); }
        .cw-node{ position:relative; flex-shrink:0; }
        .cw-node img{ width:2.75rem; height:2.75rem; border-radius:999px; object-fit:cover; display:block; box-shadow:0 0 0 2px var(--s), 0 0 0 3px var(--track); }
        .cw-step--current .cw-node img{ box-shadow:0 0 0 2px var(--s), 0 0 0 3px #fb923c, 0 0 0 7px rgba(251,146,60,.16); }
        .cw-step--approved .cw-node img{ box-shadow:0 0 0 2px var(--s), 0 0 0 3px #34d399, 0 0 0 7px rgba(52,211,153,.15); }
        .cw-step--rejected .cw-node img{ box-shadow:0 0 0 2px var(--s), 0 0 0 3px #f87171, 0 0 0 7px rgba(248,113,113,.15); }
        .cw-badge{ position:absolute; bottom:-.25rem; right:-.25rem; width:1.4rem; height:1.4rem; border-radius:999px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 0 2px var(--s); color:#fff; }
        .cw-badge--approved{ background:#22c55e; } .cw-badge--rejected{ background:#ef4444; } .cw-badge--returned{ background:#3b82f6; }
        .cw-badge--current{ background:#fb923c; } .cw-badge--queued{ background:#cbd5e1; color:#64748b; } .dark .cw-badge--queued{ background:#3f3f46; color:#a1a1aa; }
        .cw-step__bd{ min-width:0; flex:1; padding-top:.15rem; }
        .cw-step__nm{ font-size:0.908rem; font-weight:650; color:var(--t); display:flex; align-items:center; gap:.4rem; }
        .cw-ord{ font-size:0.664rem; font-weight:700; color:var(--m2); background:var(--soft); border-radius:999px; padding:.05rem .42rem; }
        .cw-step__dp{ font-size:0.784rem; color:var(--m); margin-top:.12rem; }
        .cw-step__meta{ display:flex; align-items:center; gap:.6rem; margin-top:.5rem; flex-wrap:wrap; }
        .cw-when{ font-size:0.724rem; color:var(--m2); display:inline-flex; align-items:center; gap:.25rem; }
        .cw-when--over{ color:#dc2626; font-weight:650; }
        .cw-cmt{ margin-top:.55rem; padding:.45rem .7rem; border-radius:.55rem; background:var(--soft); font-size:0.784rem; color:var(--m); border:1px solid var(--d); }
        .cw-eye{ flex-shrink:0; width:2rem; height:2rem; border-radius:.6rem; display:flex; align-items:center; justify-content:center; color:var(--m2); background:transparent; border:1px solid var(--d); cursor:pointer; transition:all .15s; }
        .cw-eye:hover{ color:var(--accent); border-color:var(--accent); background:var(--soft); }

        .cw-more{ margin-top:.4rem; }
        .cw-more>summary{ list-style:none; padding:.5rem .65rem; cursor:pointer; font-size:0.784rem; color:var(--m); display:flex; align-items:center; gap:.4rem; user-select:none; border-radius:.6rem; }
        .cw-more>summary::-webkit-details-marker{ display:none; }
        .cw-more>summary:hover{ background:var(--soft); color:var(--t); }
        .cw-more .cw-step{ opacity:.7; }

        /* document */
        .cw-doc{ display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
        .cw-doc__ic{ width:3.25rem; height:3.25rem; border-radius:.85rem; background:linear-gradient(135deg, rgba(99,102,241,.18), rgba(79,70,229,.08)); color:#4f46e5; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dark .cw-doc__ic{ color:#a5b4fc; }
        .cw-doc__nm{ font-weight:650; color:var(--t); font-size:0.926rem; }
        .cw-doc__mt{ font-size:0.784rem; color:var(--m); margin-top:.15rem; }
        .cw-doc__act{ margin-left:auto; display:flex; gap:.5rem; flex-wrap:wrap; }
        .cw-pdf{ margin-top:1.1rem; border:1px solid var(--d); border-radius:.85rem; overflow:hidden; }
        .cw-pdf iframe{ width:100%; height:64vh; min-height:30rem; background:#fff; display:block; border:0; }
        .cw-empty{ display:flex; flex-direction:column; align-items:center; gap:.6rem; padding:2.75rem 0; color:var(--m2); }
        .cw-empty span{ font-size:0.825rem; }

        /* details (sidebar, stacked) */
        .cw-dets{ display:flex; flex-direction:column; }
        .cw-row{ display:flex; align-items:center; gap:.7rem; padding:.78rem 1.25rem; border-bottom:1px solid var(--d); }
        .cw-row:last-child{ border-bottom:0; }
        .cw-row__ic{ color:var(--m2); display:inline-flex; flex-shrink:0; }
        .cw-row__lb{ font-size:0.784rem; color:var(--m); flex:1; }
        .cw-row__vl{ font-size:0.854rem; font-weight:600; color:var(--t); min-width:0; max-width:55%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:right; }

        /* execution timeline */
        .cw-filters{ display:flex; gap:.3rem; padding:.95rem 1.25rem; border-bottom:1px solid var(--d); flex-wrap:wrap; }
        .cw-chip{ display:inline-flex; align-items:center; gap:.35rem; padding:.34rem .7rem; font-size:0.744rem; font-weight:600; color:var(--m); background:var(--soft); border:0; border-radius:999px; cursor:pointer; transition:all .15s; }
        .cw-chip:hover{ color:var(--t); }
        .cw-chip--active{ background:rgba(99,102,241,.14); color:var(--accent); }
        .cw-chip__c{ font-size:0.664rem; font-weight:700; padding:.02rem .35rem; border-radius:999px; background:rgba(0,0,0,.07); }
        .dark .cw-chip__c{ background:rgba(255,255,255,.10); }
        .cw-loadmore{ width:100%; padding:.7rem; margin-top:.5rem; font-size:0.805rem; font-weight:600; color:var(--accent); background:var(--soft); border:1px dashed var(--d); border-radius:.7rem; cursor:pointer; transition:all .15s; }
        .cw-loadmore:hover{ background:rgba(99,102,241,.07); border-style:solid; }
        .cw-day__hd{ display:inline-flex; align-items:center; font-size:0.7rem; font-weight:700; color:var(--m); text-transform:uppercase; letter-spacing:.05em; padding:.24rem .6rem; margin:.15rem 0 .55rem; background:var(--soft); border-left:3px solid var(--accent); border-radius:.35rem; }
        .cw-day + .cw-day{ margin-top:.5rem; }
        .cw-tl{ position:relative; display:flex; gap:.85rem; padding-bottom:1.7rem; }
        .cw-tl__time{ width:2.6rem; flex-shrink:0; text-align:right; font-size:0.7rem; color:var(--m2); padding-top:.45rem; font-variant-numeric:tabular-nums; }
        .cw-tl:last-child{ padding-bottom:0; }
        .cw-tl:not(:last-child)::before{ content:''; position:absolute; left:4.5rem; top:2.3rem; bottom:-.2rem; width:2px; background:var(--track); border-radius:2px; }
        .cw-tl__ic{ width:2.1rem; height:2.1rem; border-radius:999px; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 0 0 3px var(--s); }
        .cw-tl__ic--success{ background:#dcfce7; color:#16a34a;} .dark .cw-tl__ic--success{ background:rgba(34,197,94,.18);}
        .cw-tl__ic--danger { background:#fee2e2; color:#dc2626;} .dark .cw-tl__ic--danger { background:rgba(239,68,68,.18);}
        .cw-tl__ic--warning{ background:#ffedd5; color:#c2410c;} .dark .cw-tl__ic--warning{ background:rgba(251,146,60,.18);}
        .cw-tl__ic--info   { background:#dbeafe; color:#2563eb;} .dark .cw-tl__ic--info   { background:rgba(59,130,246,.18);}
        .cw-tl__ic--gray   { background:#f1f5f9; color:#64748b;} .dark .cw-tl__ic--gray   { background:rgba(255,255,255,.07);}
        .cw-tl__bd{ min-width:0; padding-top:.15rem; }
        .cw-tl__ds{ font-size:0.845rem; font-weight:600; color:var(--t); }
        .cw-tl__mt{ font-size:0.734rem; color:var(--m2); margin-top:.12rem; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
        .cw-tl__dot{ width:3px; height:3px; border-radius:999px; background:var(--m2); display:inline-block; }

        /* modal */
        .cw-modal{ position:fixed; inset:0; z-index:60; display:flex; align-items:center; justify-content:center; padding:1rem; }
        .cw-modal__bg{ position:absolute; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); }
        .cw-modal__card{ position:relative; width:100%; max-width:30rem; max-height:86vh; overflow:auto; background:var(--s); border-radius:1.1rem; box-shadow:0 20px 25px rgba(15,20,25,.18), 0 10px 10px rgba(15,20,25,.08); }
        .cw-modal__hd{ display:flex; align-items:center; gap:.85rem; padding:1.25rem 1.25rem 1rem; border-bottom:1px solid var(--d); }
        .cw-modal__hd img{ width:3rem; height:3rem; border-radius:999px; object-fit:cover; box-shadow:0 0 0 2px var(--track); }
        .cw-modal__nm{ font-size:1.01rem; font-weight:700; color:var(--t); }
        .cw-modal__dp{ font-size:0.805rem; color:var(--m); margin-top:.1rem; }
        .cw-modal__x{ margin-left:auto; width:2rem; height:2rem; border-radius:.6rem; border:1px solid var(--d); background:transparent; color:var(--m); cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .cw-modal__x:hover{ background:var(--soft); color:var(--t); }
        .cw-modal__bd{ padding:1.1rem 1.25rem 1.35rem; }
        .cw-kv{ display:flex; align-items:center; gap:.6rem; padding:.5rem 0; }
        .cw-kv__lb{ font-size:0.784rem; color:var(--m); width:35%; flex-shrink:0; }
        .cw-kv__vl{ font-size:0.854rem; font-weight:600; color:var(--t); }
        .cw-sub{ font-size:0.724rem; font-weight:650; color:var(--m2); text-transform:uppercase; letter-spacing:.04em; margin:1rem 0 .6rem; }
        .cw-mini{ display:flex; gap:.65rem; padding-bottom:.85rem; position:relative; }
        .cw-mini:not(:last-child)::before{ content:''; position:absolute; left:.85rem; top:1.9rem; bottom:-.1rem; width:2px; background:var(--track); }
        .cw-mini__ic{ width:1.75rem; height:1.75rem; border-radius:999px; flex-shrink:0; display:flex; align-items:center; justify-content:center; box-shadow:0 0 0 3px var(--s); }
        .cw-mini__ds{ font-size:0.825rem; font-weight:600; color:var(--t); }
        .cw-mini__mt{ font-size:0.724rem; color:var(--m2); margin-top:.1rem; }
        .cw-modal__empty{ font-size:0.825rem; color:var(--m2); padding:.4rem 0; }
    </style>

    <div class="cw"
        x-data="{ approver: null, tab: 'overview', historyShown: 8, historyFilter: 'all' }"
        @keydown.escape.window="approver = null">
        {{-- Hero --}}
        <div class="cw-hero cw-hero--{{ $statusColor }}">
            <div class="cw-hero__l">
                <span class="cw-pill cw-pill--lg cw-pill--{{ $statusColor }}">{{ $statusLabel }}</span>
                <div class="cw-hero__msg">{{ $hero['message'] }}</div>
                @if ($hero['overdue'] && $hero['due'])
                    <span class="cw-hero__sla">{!! $ic('heroicon-m-exclamation-triangle', 14) !!} {{ __('app.label.overdue') }} · {{ $hero['due']->diffForHumans() }}</span>
                @elseif ($hero['due'])
                    <span class="cw-hero__due">{!! $ic('heroicon-m-clock', 13) !!} {{ __('app.label.due') }} {{ $hero['due']->format('d.m.Y H:i') }}</span>
                @endif
            </div>

            <div class="cw-hero__r">
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
                    <div class="cw-ring" style="--p: {{ $progress }}"><span>{{ $approvedCount }}/{{ $totalCount }}</span></div>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="cw-tabs">
            <button type="button" class="cw-tab" :class="tab === 'overview' ? 'cw-tab--active' : ''" @click="tab = 'overview'">{!! $ic('heroicon-o-rectangle-group', 16) !!} {{ __('app.label.overview') }}</button>
            <button type="button" class="cw-tab" :class="tab === 'history' ? 'cw-tab--active' : ''" @click="tab = 'history'">{!! $ic('heroicon-o-clock', 16) !!} {{ __('app.label.history') }}@if ($activities->isNotEmpty())<span class="cw-tab__c">{{ $activities->count() }}</span>@endif</button>
        </div>

        {{-- Overview --}}
        <div x-show="tab === 'overview'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="cw-panel">
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
                                    @if ($url = $this->pdfPreviewUrl())
                                        <x-filament::button tag="a" :href="$url" icon="heroicon-o-eye" color="gray" size="sm" target="_blank">
                                            {{ __('app.label.preview') }}
                                        </x-filament::button>
                                    @endif
                                    @if ($record->status === Contract::STATUS_APPROVED)
                                        <x-filament::button tag="a" :href="route('contracts.pdf.download', ['contract' => $record])" icon="heroicon-o-document-arrow-down" color="gray" size="sm">PDF</x-filament::button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="cw-empty">{!! $ic('heroicon-o-document', 36) !!}<span>{{ __('app.label.document_not_ready') }}</span></div>
                        @endif
                    </div>
                </section>

            <div class="cw-cols">
                <div class="cw-main">
                <section class="cw-card">
                    <div class="cw-hd">
                        <span class="cw-hd__ic">{!! $ic('heroicon-o-users') !!}</span>
                        <h2 class="cw-hd__t">{{ __('app.label.approval_chain') }}</h2>
                        @if ($totalCount > 0)<span class="cw-hd__c">{{ $approvedCount }}/{{ $totalCount }}</span>@endif
                    </div>

                    @if ($active->isEmpty() && $historical->isEmpty())
                        <div class="cw-bd"><p style="font-size:0.854rem;color:var(--m)">{{ __('app.label.no_approvers') }}</p></div>
                    @else
                        <div class="cw-chain">
                            @foreach ($active as $ap)
                                @php $state = $this->approverState($ap); $v = $this->approverVisual($ap); @endphp
                                <div class="cw-step cw-step--{{ $state }}">
                                    <div class="cw-node">
                                        <img src="{{ $this->approverAvatar($ap) }}" alt="">
                                        <span class="cw-badge cw-badge--{{ $state }}">{!! $ic($v['icon'], 13) !!}</span>
                                    </div>
                                    <div class="cw-step__bd">
                                        <div class="cw-step__nm">{{ $ap->user?->name }} <span class="cw-ord">#{{ $ap->order }}</span></div>
                                        <div class="cw-step__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                                        <div class="cw-step__meta">
                                            <span class="cw-pill cw-pill--{{ $pillFor($ap->status) }}">{{ $statusName($ap->status) }}</span>
                                            @if ($ap->acted_at)
                                                <span class="cw-when">{!! $ic('heroicon-m-check', 13) !!} {{ $ap->acted_at->format('d.m.Y H:i') }}</span>
                                            @elseif ($state === 'current' && $ap->due_at)
                                                <span class="cw-when {{ $ap->isOverdue() ? 'cw-when--over' : '' }}">{!! $ic('heroicon-m-clock', 13) !!} {{ __('app.label.due') }} {{ $ap->due_at->format('d.m.Y H:i') }}</span>
                                            @endif
                                        </div>
                                        @if ($ap->comment)<div class="cw-cmt">{{ $ap->comment }}</div>@endif
                                    </div>
                                    <button type="button" class="cw-eye" title="{{ __('app.label.approver_details') }}" @click="approver = {{ $ap->id }}">{!! $ic('heroicon-o-eye', 16) !!}</button>
                                </div>
                            @endforeach

                            @if ($historical->isNotEmpty())
                                <details class="cw-more">
                                    <summary>{!! $ic('heroicon-m-chevron-down', 14) !!} {{ __('app.label.previous_attempts') }} ({{ $historical->count() }})</summary>
                                    @foreach ($historical as $ap)
                                        <div class="cw-step">
                                            <div class="cw-node">
                                                <img src="{{ $this->approverAvatar($ap) }}" alt="">
                                                <span class="cw-badge cw-badge--queued">{!! $ic('heroicon-m-minus', 13) !!}</span>
                                            </div>
                                            <div class="cw-step__bd">
                                                <div class="cw-step__nm">{{ $ap->user?->name }} <span class="cw-ord">#{{ $ap->order }}</span></div>
                                                <div class="cw-step__dp">{{ $ap->user?->department?->name }}</div>
                                                <div class="cw-step__meta">
                                                    <span class="cw-pill cw-pill--gray">{{ $statusName($ap->status) }}</span>
                                                    @if ($ap->acted_at)<span class="cw-when">{{ $ap->acted_at->format('d.m.Y H:i') }}</span>@endif
                                                </div>
                                            </div>
                                            <button type="button" class="cw-eye" title="{{ __('app.label.approver_details') }}" @click="approver = {{ $ap->id }}">{!! $ic('heroicon-o-eye', 16) !!}</button>
                                        </div>
                                    @endforeach
                                </details>
                            @endif
                        </div>
                    @endif
                </section>
            </div>

            {{-- SIDEBAR: details + history --}}
            <div class="cw-side">
                <section class="cw-card">
                    <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><h2 class="cw-hd__t">{{ __('app.label.basic_information') }}</h2></div>
                    <div class="cw-dets">
                        @foreach ($details as [$icon, $label, $value])
                            <div class="cw-row">
                                <span class="cw-row__ic">{!! $ic($icon, 16) !!}</span>
                                <span class="cw-row__lb">{{ $label }}</span>
                                <span class="cw-row__vl">{{ $value ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
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
                        'description' => $a->description ?: $a->event,
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

        {{-- Per-approver detail modals --}}
        @foreach ($allApprovers as $ap)
            @php $apActs = $activities->where('causer_id', $ap->user_id)->values(); @endphp
            <div class="cw-modal" x-show="approver === {{ $ap->id }}" x-cloak style="display:none;">
                <div class="cw-modal__bg" @click="approver = null"></div>
                <div class="cw-modal__card" @click.stop>
                    <div class="cw-modal__hd">
                        <img src="{{ $this->approverAvatar($ap) }}" alt="">
                        <div>
                            <div class="cw-modal__nm">{{ $ap->user?->name }}</div>
                            <div class="cw-modal__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                        </div>
                        <button type="button" class="cw-modal__x" @click="approver = null">{!! $ic('heroicon-o-x-mark', 16) !!}</button>
                    </div>
                    <div class="cw-modal__bd">
                        <div class="cw-kv">
                            <span class="cw-kv__lb">{{ __('app.label.status') }}</span>
                            <span class="cw-kv__vl"><span class="cw-pill cw-pill--{{ $pillFor($ap->status) }}">{{ $statusName($ap->status) }}</span></span>
                        </div>
                        <div class="cw-kv">
                            <span class="cw-kv__lb">{{ __('app.label.order_position') }}</span>
                            <span class="cw-kv__vl">#{{ $ap->order }}</span>
                        </div>
                        @if ($ap->due_at)
                            <div class="cw-kv">
                                <span class="cw-kv__lb">{{ __('app.label.deadline') }}</span>
                                <span class="cw-kv__vl {{ $ap->isOverdue() ? 'cw-when--over' : '' }}">{{ $ap->due_at->format('d.m.Y H:i') }}</span>
                            </div>
                        @endif
                        @if ($ap->acted_at)
                            <div class="cw-kv">
                                <span class="cw-kv__lb">{{ __('app.label.acted_at') }}</span>
                                <span class="cw-kv__vl">{{ $ap->acted_at->format('d.m.Y H:i') }}</span>
                            </div>
                        @endif
                        @if ($ap->comment)
                            <div class="cw-kv" style="align-items:flex-start;">
                                <span class="cw-kv__lb" style="padding-top:.3rem;">{{ __('app.label.comment') }}</span>
                                <span class="cw-kv__vl" style="font-weight:450;">{{ $ap->comment }}</span>
                            </div>
                        @endif

                        <div class="cw-sub">{{ __('app.label.approver_activity') }}</div>
                        @if ($apActs->isEmpty())
                            <div class="cw-modal__empty">{{ __('app.label.no_actions_yet') }}</div>
                        @else
                            @foreach ($apActs as $a)
                                @php $v = $this->activityVisual($a->event ?? ''); @endphp
                                <div class="cw-mini">
                                    <span class="cw-mini__ic cw-tl__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 13) !!}</span>
                                    <div>
                                        <div class="cw-mini__ds">{{ $a->description ?: $a->event }}</div>
                                        <div class="cw-mini__mt">{{ $a->created_at?->format('d.m.Y H:i') }} · {{ $a->created_at?->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
