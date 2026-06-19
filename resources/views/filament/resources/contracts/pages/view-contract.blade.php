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

    // Core info visible by default; everything else lives behind "Show more".
    $details = [
        ['heroicon-o-hashtag', __('app.label.contract_number'), $record->number, null, false],
        ['heroicon-o-building-office-2', __('app.label.contact_single'), $record->contact?->name, $record->contact ? 'contact' : null, false],
        ['heroicon-o-document-duplicate', __('app.label.contract_template_single'), $record->template?->name, null, false],
        ['heroicon-o-tag', __('app.label.order_type_single'), $record->orderType?->title, null, false],
        ['heroicon-o-user', __('app.label.responsible'), $record->responsible?->name, null, false],
        ['heroicon-o-banknotes', __('app.label.amount'), number_format((float) $record->amount, 2, '.', ' ').' '.($record->currency?->short_name ?? ''), null, false],

        // Extra rows — collapsed by default.
        ['heroicon-o-language', __('app.label.language'), strtoupper((string) $record->language) ?: null, null, true],
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
    <style>
        [x-cloak]{ display:none !important; }
        .cw{ display:flex; flex-direction:column; gap:1.5rem;
            --s:#fff; --r:rgba(15,20,25,.08); --t:#0f1419; --m:#57606a; --m2:#8b949e; --d:rgba(15,20,25,.07);
            --soft:#f8fafc; --track:#e6ebf1; --accent:#6366f1; }
        .dark .cw{ --s:#18181b; --r:rgba(255,255,255,.08); --t:#f0f6fc; --m:#9aa4b2; --m2:#6e7681; --d:rgba(255,255,255,.07);
            --soft:rgba(255,255,255,.03); --track:rgba(255,255,255,.10); --accent:#818cf8; }

        .cw-card{ background:var(--s); border-radius:1rem; box-shadow:0 0 0 1px var(--r), 0 1px 3px rgba(15,20,25,.06), 0 1px 2px rgba(15,20,25,.04); overflow:hidden; }
        .cw-hd{ display:flex; align-items:center; gap:.6rem; padding:1.15rem 1.5rem; border-bottom:1px solid var(--d); }
        .cw-hd__ic{ color:var(--m2); display:inline-flex; }
        .cw-hd__t{ font-size:0.88rem; font-weight:650; color:var(--t); margin:0; flex:1; letter-spacing:-.01em; }
        .cw-hd__c{ font-size:0.724rem; font-weight:600; color:var(--m); background:var(--soft); padding:.18rem .6rem; border-radius:999px; }
        .cw-bd{ padding:1.25rem; }

        /* layout */
        .cw-cols{ display:flex; flex-direction:column; gap:1.5rem; align-items:stretch; }
        .cw-main,.cw-side{ display:flex; flex-direction:column; gap:1.5rem; min-width:0; }
        .cw-panel{ display:flex; flex-direction:column; gap:1.5rem; }

        /* meta strip — clean, no gradient */
        .cw-meta{ display:flex; align-items:center; justify-content:space-between; gap:1.5rem; flex-wrap:wrap;
            padding:1.1rem 1.5rem; background:var(--s); border-radius:.95rem; box-shadow:0 0 0 1px var(--r); }
        .cw-meta__l{ display:flex; align-items:center; gap:.85rem; flex-wrap:wrap; min-width:0; }
        .cw-meta__facts{ display:flex; align-items:center; gap:1.1rem; flex-wrap:wrap; }
        .cw-meta__fact{ display:inline-flex; align-items:center; gap:.35rem; font-size:.815rem; color:var(--m);
            white-space:nowrap; }
        .cw-meta__fact--danger{ color:#dc2626; font-weight:600; }
        .cw-meta__current{ display:flex; align-items:center; gap:.7rem; padding:.45rem .7rem .45rem .55rem;
            border-radius:.7rem; background:rgba(99,102,241,.06); box-shadow:inset 0 0 0 1px rgba(99,102,241,.18); }
        .cw-meta__cur-lb{ font-size:.64rem; color:var(--m2); text-transform:uppercase; letter-spacing:.05em; font-weight:650; }
        .cw-meta__cur-row{ display:flex; align-items:center; gap:.45rem; margin-top:.15rem; }
        .cw-meta__cur-row img{ width:1.55rem; height:1.55rem; border-radius:50%; object-fit:cover;
            box-shadow:0 0 0 1.5px var(--accent); }
        .cw-meta__cur-nm{ font-size:.85rem; font-weight:650; color:var(--t); }
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
        .cw-pill--primary{ background:#e0e7ff; color:#4338ca;} .dark .cw-pill--primary{ background:rgba(99,102,241,.18); color:#a5b4fc;}
        .cw-pill--gray   { background:#f1f5f9; color:#64748b;} .dark .cw-pill--gray   { background:rgba(255,255,255,.07); color:#cbd5e1;}

        /* approval chain — vertical timeline */
        .cw-chain{ padding:.9rem 1.25rem 1.1rem; }
        .cw-step{ position:relative; display:flex; align-items:flex-start; gap:.9rem; padding:.6rem .65rem; border-radius:.85rem; transition:background .15s; }
        .cw-step:not(:last-child){ margin-bottom:.15rem; }
        .cw-step:not(:last-child)::before{ content:''; position:absolute; left:2rem; top:2.95rem; bottom:-.45rem; width:2px; background:var(--track); border-radius:2px; }
        .cw-step--approved:not(:last-child)::before{ background:linear-gradient(#34d399, var(--track)); }
        .cw-step--current{ background:linear-gradient(90deg, rgba(99,102,241,.08), transparent 70%); }
        @keyframes cwPulse { 0%{ box-shadow:0 0 0 0 rgba(99,102,241,.55);} 70%{ box-shadow:0 0 0 8px rgba(99,102,241,0);} 100%{ box-shadow:0 0 0 0 rgba(99,102,241,0);} }
        @media (prefers-reduced-motion: no-preference){ .cw-step--current .cw-badge--current{ animation:cwPulse 1.8s ease-out infinite; } }
        .cw-step:hover{ background:var(--soft); }
        .cw-node{ position:relative; flex-shrink:0; }
        .cw-node img{ width:2.75rem; height:2.75rem; border-radius:999px; object-fit:cover; display:block; box-shadow:0 0 0 2px var(--s), 0 0 0 3px var(--track); }
        .cw-step--current .cw-node img{ box-shadow:0 0 0 2px var(--s), 0 0 0 3px #6366f1, 0 0 0 7px rgba(99,102,241,.16); }
        .cw-step--approved .cw-node img{ box-shadow:0 0 0 2px var(--s), 0 0 0 3px #34d399, 0 0 0 7px rgba(52,211,153,.15); }
        .cw-step--rejected .cw-node img{ box-shadow:0 0 0 2px var(--s), 0 0 0 3px #f87171, 0 0 0 7px rgba(248,113,113,.15); }
        .cw-badge{ position:absolute; bottom:-.25rem; right:-.25rem; width:1.4rem; height:1.4rem; border-radius:999px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 0 2px var(--s); color:#fff; }
        .cw-badge--approved{ background:#22c55e; } .cw-badge--rejected{ background:#ef4444; } .cw-badge--returned{ background:#3b82f6; }
        .cw-badge--current{ background:#6366f1; } .cw-badge--queued{ background:#cbd5e1; color:#64748b; } .dark .cw-badge--queued{ background:#3f3f46; color:#a1a1aa; }
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

        .cw-history-btn{ display:flex; align-items:center; justify-content:center; gap:.4rem;
            margin:.6rem 1.25rem 1.1rem; padding:.55rem .9rem;
            background:transparent; border:1px solid var(--d); border-radius:.55rem;
            color:var(--m); font-size:.78rem; font-weight:600; cursor:pointer;
            transition:background .12s ease, color .12s ease, border-color .12s ease; }
        .cw-history-btn:hover{ background:var(--soft); color:var(--t); border-color:var(--m2); }

        .cw-hist{ display:flex; gap:.85rem; padding:.85rem 0; }
        .cw-hist + .cw-hist{ border-top:1px solid var(--d); }
        .cw-hist__av{ width:2.4rem; height:2.4rem; border-radius:50%; object-fit:cover; flex-shrink:0; opacity:.85; }
        .cw-hist__bd{ flex:1; min-width:0; }
        .cw-hist__top{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
        .cw-hist__nm{ font-size:.9rem; font-weight:650; color:var(--t); }
        .cw-hist__dt{ font-size:.74rem; color:var(--m2); }
        .cw-hist__dp{ font-size:.78rem; color:var(--m); margin-top:.1rem; }
        .cw-hist__meta{ margin-top:.45rem; }

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
        .cw-dets{ display:flex; flex-direction:column; padding:.4rem 0; }
        .cw-row{ display:flex; align-items:center; gap:.7rem; padding:.95rem 1.5rem; border-bottom:1px solid var(--d); transition:background .12s ease; }
        .cw-row:last-child{ border-bottom:0; }
        .cw-row:hover{ background:rgba(99,102,241,.04); }
        .cw-row__ic{ color:var(--m2); display:inline-flex; flex-shrink:0; }
        .cw-row__lb{ font-size:0.784rem; color:var(--m); flex:1; }
        .cw-row__vl{ font-size:0.854rem; font-weight:600; color:var(--t); min-width:0; max-width:55%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:right; }
        .cw-row__vl--muted{ color:var(--m2); font-weight:500; font-style:italic; }
        button.cw-row{ width:100%; background:transparent; border:0; cursor:pointer; text-align:left; }
        button.cw-row:hover{ background:rgba(99,102,241,.07); }
        .cw-row--link .cw-row__vl{ color:var(--accent); display:inline-flex; align-items:center; gap:.35rem; justify-content:flex-end; }
        .cw-show-more{ display:flex; align-items:center; justify-content:center; gap:.4rem; width:100%;
            padding:.85rem 1.5rem; background:transparent; border:0; border-top:1px solid var(--d);
            color:var(--accent); font-size:.815rem; font-weight:600; cursor:pointer;
            transition:background .12s ease; }
        .cw-show-more:hover{ background:rgba(99,102,241,.05); }
        .cw-show-more > span{ display:inline-flex; align-items:center; gap:.4rem; }

        /* combined card — file block */
        .cw-file{ display:flex; align-items:flex-start; gap:1.1rem; padding:1.25rem 1.5rem .5rem; }
        .cw-file__thumb{ position:relative; width:5rem; height:6rem; flex-shrink:0;
            background:rgba(99,102,241,.05); border-radius:.7rem; display:flex; align-items:center; justify-content:center;
            box-shadow:inset 0 0 0 1px rgba(99,102,241,.10); }
        .cw-file__thumb svg{ width:60%; height:auto; }
        .cw-file__ext{ position:absolute; left:.45rem; bottom:.55rem; padding:.16rem .42rem;
            border-radius:.3rem; background:var(--accent); color:#fff;
            font-size:.6rem; font-weight:700; letter-spacing:.04em; line-height:1; }
        .cw-file__body{ display:flex; flex-direction:column; gap:.6rem; min-width:0; flex:1; padding-top:.15rem; }
        .cw-file__field{ display:flex; flex-direction:column; gap:.15rem; min-width:0; }
        .cw-file__lb{ font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--m2); }
        .cw-file__vl{ font-size:.86rem; font-weight:650; color:var(--t);
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cw-file__act{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; padding:.75rem 1.5rem 1.2rem; }
        .cw-btn{ display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .85rem; border-radius:.5rem;
            font-size:.81rem; font-weight:600; text-decoration:none; transition:opacity .12s ease, background .12s ease, box-shadow .12s ease; }
        .cw-btn--primary{ background:var(--accent); color:#fff; }
        .cw-btn--primary:hover{ opacity:.88; }
        .cw-btn--ghost{ background:transparent; color:var(--t); box-shadow:inset 0 0 0 1px var(--d); }
        .cw-btn--ghost:hover{ background:var(--soft); }
        .cw-divider{ height:1px; background:var(--d); margin:0 1.5rem; }
        .cw-contact-group{ display:flex; flex-direction:column; gap:.1rem; }
        .cw-contact-group + .cw-contact-group{ margin-top:1.1rem; padding-top:.9rem; border-top:1px solid var(--d); }
        .cw-contact-group__t{ font-size:.7rem; font-weight:700; color:var(--m2); text-transform:uppercase; letter-spacing:.06em; padding:0 .25rem .35rem; }
        .cw-contact-rows{ display:flex; flex-direction:column; }
        .cw-contact-rows .cw-row{ padding:.45rem .25rem; border-bottom:0; }
        .cw-contact-rows .cw-row + .cw-row{ border-top:1px dashed var(--d); }

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
        .cw-sub{ display:flex; align-items:center; justify-content:space-between; font-size:0.724rem; font-weight:650; color:var(--m2); text-transform:uppercase; letter-spacing:.04em; margin:1.1rem 0 .5rem; }
        .cw-sub__c{ font-size:0.684rem; font-weight:700; padding:.05rem .4rem; border-radius:999px; background:var(--soft); color:var(--m); text-transform:none; letter-spacing:0; }
        .cw-act{ border:1px solid var(--d); border-radius:.7rem; overflow:hidden; }
        .cw-act__row{ display:grid; grid-template-columns:2.4rem 1.75rem 1fr auto; align-items:center; gap:.65rem; padding:.55rem .75rem; }
        .cw-act__row + .cw-act__row{ border-top:1px solid var(--d); }
        .cw-act__row:nth-child(odd){ background:var(--soft); }
        .cw-act__time{ font-size:0.7rem; color:var(--m2); font-variant-numeric:tabular-nums; }
        .cw-act__ic{ width:1.7rem; height:1.7rem; border-radius:999px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .cw-act__ic--success{ background:#dcfce7; color:#16a34a;} .dark .cw-act__ic--success{ background:rgba(34,197,94,.18);}
        .cw-act__ic--danger { background:#fee2e2; color:#dc2626;} .dark .cw-act__ic--danger { background:rgba(239,68,68,.18);}
        .cw-act__ic--warning{ background:#ffedd5; color:#c2410c;} .dark .cw-act__ic--warning{ background:rgba(251,146,60,.18);}
        .cw-act__ic--info   { background:#dbeafe; color:#2563eb;} .dark .cw-act__ic--info   { background:rgba(59,130,246,.18);}
        .cw-act__ic--gray   { background:#f1f5f9; color:#64748b;} .dark .cw-act__ic--gray   { background:rgba(255,255,255,.07);}
        .cw-act__ds{ font-size:0.805rem; font-weight:600; color:var(--t); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .cw-act__rel{ font-size:0.7rem; color:var(--m2); white-space:nowrap; }
        .cw-act__toggle{ width:100%; padding:.55rem; font-size:0.74rem; font-weight:600; color:var(--accent); background:var(--soft); border:0; border-top:1px solid var(--d); cursor:pointer; transition:background .15s; }
        .cw-act__toggle:hover{ background:rgba(99,102,241,.06); }
        .cw-modal__empty{ font-size:0.825rem; color:var(--m2); padding:.4rem 0; }

        /* Per-record cards inside the eye-modal (all rows of this user). */
        .cw-recs{ display:flex; flex-direction:column; gap:.6rem; }
        .cw-rec{ border:1px solid var(--d); border-radius:.7rem; padding:.7rem .85rem; }
        .cw-rec--focus{ box-shadow:inset 0 0 0 1.5px var(--accent); }
        .cw-rec__top{ display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; }
        .cw-rec__ord{ font-size:.74rem; font-weight:600; color:var(--m); font-variant-numeric:tabular-nums; }
        .cw-rec__when{ display:inline-flex; align-items:center; gap:.3rem; font-size:.74rem; color:var(--m); margin-left:auto; }
        .cw-rec__cmt{ margin-top:.5rem; font-size:.82rem; color:var(--t); line-height:1.45; }
        .cw-rec__sys{ margin-top:.45rem; padding:.45rem .6rem; border-radius:.45rem;
            background:rgba(251,146,60,.10); border:1px solid rgba(251,146,60,.32); font-size:.78rem; color:#c2410c; line-height:1.4; }
        .cw-rec__sys-lb{ font-weight:700; margin-right:.35rem; }
    </style>

    <div class="cw"
        x-data="{ approver: null, contactOpen: false, historyOpen: false, basicExpanded: false, tab: 'overview', historyShown: 8, historyFilter: 'all' }"
        @keydown.escape.window="approver = null; contactOpen = false; historyOpen = false">
        @php
            $submittedAt = $this->submittedAt();
            $remaining = $this->timeRemaining();
        @endphp

        {{-- Meta strip — replaces the gradient hero. Inline status pill, one
             line of contract context, plus a compact "Awaiting X" tile on the
             right while in-review. --}}
        <div class="cw-meta">
            <div class="cw-meta__l">
                <span class="cw-pill cw-pill--{{ $statusColor }}">{{ $statusLabel }}</span>
                <div class="cw-meta__facts">
                    @if ($submittedAt)
                        <span class="cw-meta__fact" title="{{ $submittedAt->translatedFormat('d M Y H:i') }}">
                            {!! $ic('heroicon-m-paper-airplane', 13) !!}
                            {{ __('app.label.submitted') }} {{ $submittedAt->diffForHumans() }}
                        </span>
                    @endif
                    @if ($current?->due_at && $record->status === Contract::STATUS_IN_REVIEW)
                        @php $dueIso = $current->due_at->toIso8601String(); @endphp
                        <span class="cw-meta__fact"
                            x-data="contractCountdown('{{ $dueIso }}')"
                            x-init="start()"
                            :class="overdue ? 'cw-meta__fact--danger' : ''"
                            :title="absolute">
                            <template x-if="overdue">{!! $ic('heroicon-m-exclamation-triangle', 13) !!}</template>
                            <template x-if="! overdue">{!! $ic('heroicon-m-clock', 13) !!}</template>
                            <span x-text="label"></span>
                        </span>
                    @endif
                    @if ($totalCount > 0)
                        <span class="cw-meta__fact">
                            {!! $ic('heroicon-m-check-badge', 13) !!}
                            {{ $approvedCount }}/{{ $totalCount }} {{ __('app.label.approved_lower') }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($current && $record->status === Contract::STATUS_IN_REVIEW)
                <div class="cw-meta__current">
                    <div class="cw-meta__cur-lb">{{ __('app.label.awaiting') }}</div>
                    <div class="cw-meta__cur-row">
                        <img src="{{ $this->approverAvatar($current) }}" alt="">
                        <span class="cw-meta__cur-nm">{{ $current->user?->name }}</span>
                    </div>
                </div>
            @endif
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
                                                @php $stepDueIso = $ap->due_at->toIso8601String(); @endphp
                                                <span class="cw-when"
                                                    x-data="contractCountdown('{{ $stepDueIso }}')"
                                                    x-init="start()"
                                                    :class="overdue ? 'cw-when--over' : ''"
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
                                    <button type="button" class="cw-eye" title="{{ __('app.label.approver_details') }}" @click="approver = {{ $ap->id }}">{!! $ic('heroicon-o-eye', 16) !!}</button>
                                </div>
                            @endforeach

                        </div>
                    @endif
                    @if ($historical->isNotEmpty())
                        <button type="button" class="cw-history-btn" @click="historyOpen = true">
                            {!! $ic('heroicon-o-eye', 14) !!}
                            <span>{{ __('app.label.view_history') }} ({{ $historical->count() }})</span>
                        </button>
                    @endif
                </section>
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
                        <div class="cw-file__act">
                            <a href="{{ $editUrl }}" class="cw-btn cw-btn--primary">
                                {!! $ic('heroicon-o-pencil-square', 16) !!}
                                <span>{{ __('app.action.open_editor') }}</span>
                            </a>
                            @if ($previewUrl)
                                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="cw-btn cw-btn--ghost">
                                    {!! $ic('heroicon-o-arrow-top-right-on-square', 16) !!}
                                    <span>{{ __('app.action.open_in_new_tab') }}</span>
                                </a>
                            @endif
                            @if ($record->status === Contract::STATUS_APPROVED)
                                <a href="{{ route('contracts.pdf.download', ['contract' => $record]) }}" class="cw-btn cw-btn--ghost">
                                    {!! $ic('heroicon-o-document-arrow-down', 16) !!}
                                    <span>PDF</span>
                                </a>
                            @endif
                        </div>
                        <div class="cw-divider"></div>
                    @endif

                    <div class="cw-dets">
                        @foreach ($details as [$icon, $label, $value, $type, $extra])
                            @php $hasValue = ! empty($value); @endphp
                            @if ($type === 'contact' && $hasValue)
                                <button type="button" class="cw-row cw-row--link" @click="contactOpen = true" @if ($extra) x-show="basicExpanded" x-cloak @endif>
                                    <span class="cw-row__ic">{!! $ic($icon, 16) !!}</span>
                                    <span class="cw-row__lb">{{ $label }}</span>
                                    <span class="cw-row__vl">
                                        {{ $value }}
                                        {!! $ic('heroicon-m-arrow-top-right-on-square', 13) !!}
                                    </span>
                                </button>
                            @else
                                <div class="cw-row" @if ($extra) x-show="basicExpanded" x-cloak @endif>
                                    <span class="cw-row__ic">{!! $ic($icon, 16) !!}</span>
                                    <span class="cw-row__lb">{{ $label }}</span>
                                    @if ($hasValue)
                                        <span class="cw-row__vl">{{ $value }}</span>
                                    @else
                                        <span class="cw-row__vl cw-row__vl--muted">{{ __('app.label.not_set') }}</span>
                                    @endif
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

        {{-- Contact detail modal --}}
        @if ($contact)
            <div class="cw-modal" x-show="contactOpen" x-cloak style="display:none;">
                <div class="cw-modal__bg" @click="contactOpen = false"></div>
                <div class="cw-modal__card" style="max-width:34rem;"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="cw-modal__hd">
                        <span class="cw-row__ic" style="background:rgba(99,102,241,.10);width:2.6rem;height:2.6rem;border-radius:.7rem;display:inline-flex;align-items:center;justify-content:center;color:var(--accent);">
                            {!! $ic('heroicon-o-building-office-2', 22) !!}
                        </span>
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ $contact->name }}</div>
                            @if ($contact->legal_form)
                                <div class="cw-modal__dp">{{ $contact->legal_form }}</div>
                            @endif
                        </div>
                        <button type="button" class="cw-modal__x" @click="contactOpen = false">{!! $ic('heroicon-m-x-mark', 16) !!}</button>
                    </div>
                    <div class="cw-modal__bd">
                        @foreach ($contactGroups as [$groupLabel, $rows])
                            <div class="cw-contact-group">
                                <div class="cw-contact-group__t">{{ $groupLabel }}</div>
                                <div class="cw-contact-rows">
                                    @foreach ($rows as [$ic_, $lb, $vl])
                                        <div class="cw-row">
                                            <span class="cw-row__ic">{!! $ic($ic_, 16) !!}</span>
                                            <span class="cw-row__lb">{{ $lb }}</span>
                                            <span class="cw-row__vl" style="max-width:62%;white-space:normal;text-align:right;">{{ $vl }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- History modal: every invalidated / skipped attempt in one place --}}
        @if ($historical->isNotEmpty())
            <div class="cw-modal" x-show="historyOpen" x-cloak style="display:none;">
                <div class="cw-modal__bg" @click="historyOpen = false"></div>
                <div class="cw-modal__card" style="max-width:36rem;" @click.stop
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="cw-modal__hd">
                        <span class="cw-row__ic" style="background:rgba(99,102,241,.10);width:2.6rem;height:2.6rem;border-radius:.7rem;display:inline-flex;align-items:center;justify-content:center;color:var(--accent);">
                            {!! $ic('heroicon-o-clock', 22) !!}
                        </span>
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ __('app.label.view_history') }}</div>
                            <div class="cw-modal__dp">{{ trans_choice('app.label.attempts_count', $historical->count(), ['count' => $historical->count()]) }}</div>
                        </div>
                        <button type="button" class="cw-modal__x" @click="historyOpen = false">{!! $ic('heroicon-m-x-mark', 16) !!}</button>
                    </div>
                    <div class="cw-modal__bd">
                        @foreach ($historical as $ap)
                            <div class="cw-hist">
                                <img class="cw-hist__av" src="{{ $this->approverAvatar($ap) }}" alt="">
                                <div class="cw-hist__bd">
                                    <div class="cw-hist__top">
                                        <span class="cw-hist__nm">{{ $ap->user?->name }} <span class="cw-ord">#{{ $ap->order }}</span></span>
                                        @if ($ap->acted_at)
                                            <span class="cw-hist__dt">{{ $ap->acted_at->format('d.m.Y H:i') }}</span>
                                        @endif
                                    </div>
                                    <div class="cw-hist__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                                    <div class="cw-hist__meta">
                                        <span class="cw-pill cw-pill--gray">{{ $statusName($ap->status) }}</span>
                                    </div>
                                    @if ($ap->comment)<div class="cw-cmt" style="margin-top:.45rem;">{{ $ap->comment }}</div>@endif
                                    @if ($ap->system_comment)<div class="cw-cmt" style="margin-top:.4rem;background:rgba(251,146,60,.10);border-color:rgba(251,146,60,.32);color:#c2410c;font-weight:550;">{{ $ap->system_comment }}</div>@endif
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
                // All ContractApprover rows for this user on this contract.
                $allRecords = $record->approvers->where('user_id', $ap->user_id)->sortByDesc('id')->values();
            @endphp
            <div class="cw-modal" x-show="approver === {{ $ap->id }}" x-cloak style="display:none;">
                <div class="cw-modal__bg" @click="approver = null"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
                <div class="cw-modal__card" style="max-width:36rem;" @click.stop
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                    <div class="cw-modal__hd">
                        <img src="{{ $this->approverAvatar($ap) }}" alt="">
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ $ap->user?->name }}</div>
                            <div class="cw-modal__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                        </div>
                        @if ($allRecords->count() > 1)
                            <span style="font-size:.72rem;font-weight:600;color:var(--m);background:var(--soft);padding:.25rem .55rem;border-radius:.35rem;white-space:nowrap;">
                                {{ trans_choice('app.label.attempts_count', $allRecords->count(), ['count' => $allRecords->count()]) }}
                            </span>
                        @endif
                        <button type="button" class="cw-modal__x" @click="approver = null">{!! $ic('heroicon-o-x-mark', 16) !!}</button>
                    </div>
                    <div class="cw-modal__bd">
                        <div class="cw-sub" style="margin-top:0;">
                            <span>{{ __('app.label.approval_records') }}</span>
                        </div>

                        {{-- Each ContractApprover record this user has on the contract --}}
                        <div class="cw-recs">
                            @foreach ($allRecords as $rec)
                                <div class="cw-rec {{ $rec->id === $ap->id ? 'cw-rec--focus' : '' }}">
                                    <div class="cw-rec__top">
                                        <span class="cw-pill cw-pill--{{ $pillFor($rec->status) }}">{{ $statusName($rec->status) }}</span>
                                        <span class="cw-rec__ord">#{{ $rec->order }}</span>
                                        <span class="cw-rec__when">
                                            @if ($rec->acted_at)
                                                {!! $ic('heroicon-m-check', 12) !!} {{ $rec->acted_at->format('d.m.Y H:i') }}
                                            @elseif ($rec->due_at)
                                                {!! $ic('heroicon-m-clock', 12) !!} {{ __('app.label.due') }} {{ $rec->due_at->format('d.m.Y H:i') }}
                                            @else
                                                {!! $ic('heroicon-m-clock', 12) !!} {{ $rec->created_at?->format('d.m.Y H:i') }}
                                            @endif
                                        </span>
                                    </div>
                                    @if ($rec->comment)
                                        <div class="cw-rec__cmt">{{ $rec->comment }}</div>
                                    @endif
                                    @if ($rec->system_comment)
                                        <div class="cw-rec__sys">
                                            <span class="cw-rec__sys-lb">{{ __('app.label.system_note') }}</span>
                                            <span>{{ $rec->system_comment }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="cw-sub">
                            <span>{{ __('app.label.approver_activity') }}</span>
                            @if ($apActs->isNotEmpty())<span class="cw-sub__c">{{ $apActs->count() }}</span>@endif
                        </div>
                        @if ($apActs->isEmpty())
                            <div class="cw-modal__empty">{{ __('app.label.no_actions_yet') }}</div>
                        @else
                            <div class="cw-act" x-data="{ all: false }">
                                @foreach ($apActs as $a)
                                    @php $v = $this->activityVisual($a->event ?? ''); @endphp
                                    <div class="cw-act__row" @if ($loop->index >= 4) x-show="all" x-cloak @endif>
                                        <span class="cw-act__time">{{ $a->created_at?->format('H:i') }}</span>
                                        <span class="cw-act__ic cw-act__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 13) !!}</span>
                                        <span class="cw-act__ds" title="{{ $a->description ?: $a->event }}">{{ $a->description ?: $a->event }}</span>
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
