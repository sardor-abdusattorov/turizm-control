@php
    $s = $this->summary();
    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
@endphp

<x-filament-panels::page>
    <style>
        [x-cloak]{ display:none !important; }
        .cc{ display:grid; grid-template-columns:1fr; gap:1.25rem; align-items:start;
            --s:#fff; --r:rgba(15,20,25,.08); --t:#0f1419; --m:#57606a; --m2:#8b949e; --d:rgba(15,20,25,.07);
            --soft:#f8fafc; --track:#e6ebf1; --accent:#6366f1; }
        .dark .cc{ --s:#18181b; --r:rgba(255,255,255,.08); --t:#f0f6fc; --m:#9aa4b2; --m2:#6e7681; --d:rgba(255,255,255,.07);
            --soft:rgba(255,255,255,.03); --track:rgba(255,255,255,.10); --accent:#818cf8; }
        @media(min-width:1280px){ .cc{ grid-template-columns:minmax(0,1.7fr) minmax(0,1fr); } }

        .cc-side{ position:sticky; top:5rem; display:flex; flex-direction:column; gap:1rem; }
        @media(max-width:1279px){ .cc-side{ position:static; } }

        .cc-card{ background:var(--s); border-radius:1rem; box-shadow:0 0 0 1px var(--r), 0 1px 3px rgba(15,20,25,.06); overflow:hidden; }
        .cc-hd{ display:flex; align-items:center; gap:.55rem; padding:.85rem 1.15rem; border-bottom:1px solid var(--d); }
        .cc-hd__ic{ color:var(--m2); display:inline-flex; }
        .cc-hd__t{ font-size:0.825rem; font-weight:650; color:var(--t); margin:0; letter-spacing:-.01em; }
        .cc-bd{ padding:1.15rem; display:flex; flex-direction:column; gap:.85rem; }

        .cc-doc{ display:flex; align-items:center; gap:.85rem; }
        .cc-doc__ic{ width:2.6rem; height:2.6rem; border-radius:.7rem; background:linear-gradient(135deg, rgba(99,102,241,.18), rgba(79,70,229,.08)); color:#4f46e5; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dark .cc-doc__ic{ color:#a5b4fc; }
        .cc-doc__nm{ font-weight:650; color:var(--t); font-size:0.9rem; }
        .cc-doc__mt{ font-size:0.74rem; color:var(--m); margin-top:.1rem; }

        .cc-amount{ font-size:1.45rem; font-weight:700; color:var(--t); letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
        .cc-amount__cur{ font-size:0.85rem; font-weight:600; color:var(--m); margin-left:.3rem; }
        .cc-amount__none{ font-size:0.9rem; color:var(--m2); font-style:italic; }

        .cc-row{ display:flex; align-items:flex-start; gap:.55rem; font-size:0.8rem; }
        .cc-row__ic{ color:var(--m2); flex-shrink:0; margin-top:.1rem; }
        .cc-row__lb{ color:var(--m); flex-shrink:0; width:5.5rem; }
        .cc-row__vl{ color:var(--t); font-weight:550; min-width:0; word-break:break-word; }
        .cc-row__vl--muted{ color:var(--m2); font-style:italic; font-weight:400; }

        .cc-tag{ display:inline-flex; align-items:center; padding:.16rem .55rem; border-radius:999px; font-size:0.7rem; font-weight:650; background:rgba(99,102,241,.10); color:var(--accent); }

        .cc-chain{ display:flex; flex-direction:column; gap:.45rem; }
        .cc-step{ display:flex; align-items:center; gap:.65rem; padding:.5rem .6rem; border-radius:.6rem; background:var(--soft); }
        .cc-step__n{ flex-shrink:0; width:1.45rem; height:1.45rem; border-radius:50%; background:var(--accent); color:#fff; font-size:0.68rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .cc-step__av{ width:1.85rem; height:1.85rem; border-radius:50%; object-fit:cover; flex-shrink:0; box-shadow:0 0 0 2px var(--s); }
        .cc-step__nm{ font-size:0.8rem; font-weight:600; color:var(--t); }
        .cc-step__dp{ font-size:0.7rem; color:var(--m); margin-top:.05rem; }

        .cc-empty{ font-size:0.78rem; color:var(--m2); text-align:center; padding:.85rem 0; }
        .cc-empty__hint{ display:block; margin-top:.25rem; font-size:0.72rem; color:var(--m2); }
    </style>

    <div class="cc">
        <div>{{ $this->content }}</div>

        <aside class="cc-side">
            <section class="cc-card">
                <div class="cc-hd">
                    <span class="cc-hd__ic">{!! $ic('heroicon-o-eye', 16) !!}</span>
                    <h3 class="cc-hd__t">{{ __('app.label.contract_preview') }}</h3>
                </div>
                <div class="cc-bd">
                    {{-- Document head --}}
                    <div class="cc-doc">
                        <div class="cc-doc__ic">{!! $ic('heroicon-o-document-text', 22) !!}</div>
                        <div style="min-width:0;">
                            <div class="cc-doc__nm">{{ $s['number'] ?: __('app.label.preview_no_number') }}</div>
                            <div class="cc-doc__mt">{{ $s['title'] ?: __('app.label.preview_no_title') }}</div>
                        </div>
                    </div>

                    {{-- Amount, large --}}
                    @if ($s['amount'] !== null)
                        <div>
                            <span class="cc-amount">{{ number_format($s['amount'], 2, '.', ' ') }}</span>
                            @if ($s['currency'])<span class="cc-amount__cur">{{ $s['currency'] }}</span>@endif
                        </div>
                    @else
                        <div class="cc-amount__none">{{ __('app.label.preview_no_amount') }}</div>
                    @endif

                    {{-- Key facts --}}
                    <div style="display:flex; flex-direction:column; gap:.5rem; border-top:1px solid var(--d); padding-top:.85rem;">
                        <div class="cc-row">
                            <span class="cc-row__ic">{!! $ic('heroicon-o-building-office-2', 14) !!}</span>
                            <span class="cc-row__lb">{{ __('app.label.contact_single') }}</span>
                            <span class="cc-row__vl {{ $s['contact'] ? '' : 'cc-row__vl--muted' }}">
                                {{ $s['contact']?->name ?: __('app.label.preview_not_set') }}
                            </span>
                        </div>

                        <div class="cc-row">
                            <span class="cc-row__ic">{!! $ic('heroicon-o-tag', 14) !!}</span>
                            <span class="cc-row__lb">{{ __('app.label.order_type_single') }}</span>
                            <span class="cc-row__vl {{ $s['order_type'] ? '' : 'cc-row__vl--muted' }}">
                                @if ($s['order_type'])
                                    <span class="cc-tag">{{ $s['order_type']->title }}</span>
                                @else
                                    {{ __('app.label.preview_not_set') }}
                                @endif
                            </span>
                        </div>

                        <div class="cc-row">
                            <span class="cc-row__ic">{!! $ic('heroicon-o-document-duplicate', 14) !!}</span>
                            <span class="cc-row__lb">{{ __('app.label.contract_template_single') }}</span>
                            <span class="cc-row__vl {{ $s['template'] ? '' : 'cc-row__vl--muted' }}">
                                {{ $s['template']?->name ?: __('app.label.preview_not_set') }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Approval chain preview --}}
            <section class="cc-card">
                <div class="cc-hd">
                    <span class="cc-hd__ic">{!! $ic('heroicon-o-users', 16) !!}</span>
                    <h3 class="cc-hd__t">{{ __('app.label.approval_chain') }}</h3>
                </div>
                <div class="cc-bd">
                    @if ($s['chain']->isEmpty())
                        <div class="cc-empty">
                            {{ __('app.label.preview_no_chain') }}
                            <span class="cc-empty__hint">{{ __('app.helper.approval_chain_empty') }}</span>
                        </div>
                    @else
                        <div class="cc-chain">
                            @foreach ($s['chain'] as $i => $user)
                                @php
                                    $av = $user->getFilamentAvatarUrl()
                                        ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=E0E7FF&color=4338CA&size=64';
                                    $meta = trim(($user->department?->name ?? '').($user->position?->name ? ' · '.$user->position->name : ''), ' ·');
                                @endphp
                                <div class="cc-step">
                                    <span class="cc-step__n">{{ $i + 1 }}</span>
                                    <img class="cc-step__av" src="{{ $av }}" alt="">
                                    <div style="min-width:0;">
                                        <div class="cc-step__nm">{{ $user->name }}</div>
                                        @if ($meta)<div class="cc-step__dp">{{ $meta }}</div>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-filament-panels::page>
