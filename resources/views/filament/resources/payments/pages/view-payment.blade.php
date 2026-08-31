@php
    /** @var \App\Models\Payment $record */
    $record = $this->record;

    $isDirect = $record->isDirect();
    $subject = $isDirect ? \App\Enums\PaymentSubject::Project : \App\Enums\PaymentSubject::Contract;
    $contractUrl = $this->contractUrl();
    $projectUrl = $this->projectUrl();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $details = [
        ['heroicon-o-document-text', __('app.label.contract'), trim(($record->contract?->number ?? '').' · '.($record->contract?->title ?? ''), ' ·'), 'contract'],
        ['heroicon-o-presentation-chart-bar', __('app.label.project_single'), $record->project?->name, 'project'],
        ['heroicon-o-chart-pie',     __('app.label.percent'),  $isDirect ? null : format_percent((float) $record->percent).'%'],
        ['heroicon-o-banknotes',     __('app.label.amount'),   $isDirect ? \App\Support\Money::format($record->amount).' '.($record->currency?->short_name ?? '') : null],
        ['heroicon-o-bars-3-bottom-left', __('app.label.payment_purpose'), $record->purpose],
        ['heroicon-o-calendar',      __('app.label.paid_at'),  $record->paid_at?->format('d.m.Y')],
        ['heroicon-o-user',          __('app.label.created_by'), $record->creator?->name],
        ['heroicon-o-clock',         __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
    ];

    // A row that cannot apply to this kind of payment is dropped rather than
    // shown empty: a direct payment has no percent, a contract one no sum.
    $details = array_values(array_filter($details, fn (array $row) => match ($row[1]) {
        __('app.label.contract') => ! $isDirect,
        __('app.label.project_single'), __('app.label.amount'), __('app.label.payment_purpose') => $isDirect,
        __('app.label.percent') => ! $isDirect,
        default => true,
    }));
@endphp

<x-filament-panels::page>
<div class="pv">

    <section class="pv-hero">
        <div class="pv-hero__l">
            <div class="pv-hero__meta">
                <span class="pv-chip">{!! $ic($subject->icon(), 14) !!} {{ $subject->label() }}</span>
                @if ($record->contract?->number)
                    <span class="pv-num">{{ $record->contract->number }}</span>
                @endif
            </div>
            <h2 class="pv-hero__title">{{ $isDirect ? ($record->purpose ?: $record->project?->name) : $record->contract?->title }}</h2>
            @if ($record->paid_at)
                <div class="pv-hero__dates">
                    <span class="pv-hero__date">{!! $ic('heroicon-o-calendar', 14) !!} {{ __('app.label.paid_at') }}: <b>{{ $record->paid_at->translatedFormat('d M Y') }}</b></span>
                </div>
            @endif
        </div>
        <span class="pv-amount">{{ $record->valueLabel() }}</span>
    </section>

    <section class="pv-card">
        <div class="pv-hd">
            <span class="pv-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span>
            <h2 class="pv-hd__t">{{ __('app.label.basic_information') }}</h2>
        </div>
        <div class="pv-dets">
            @foreach ($details as $row)
                @php [$icon, $label, $value, $type] = array_pad($row, 4, null); $has = filled($value); @endphp
                <div class="pv-row">
                    <span class="pv-row__k"><span class="pv-row__ic">{!! $ic($icon, 14) !!}</span><span class="pv-row__lb">{{ $label }}</span></span>
                    <span class="pv-row__v">
                        @if ($type === 'contract' && $has && $contractUrl)
                            <a href="{{ $contractUrl }}" class="pv-row__link">{{ $value }} {!! $ic('heroicon-m-arrow-top-right-on-square', 13) !!}</a>
                        @elseif ($type === 'project' && $has && $projectUrl)
                            <a href="{{ $projectUrl }}" class="pv-row__link">{{ $value }} {!! $ic('heroicon-m-arrow-top-right-on-square', 13) !!}</a>
                        @elseif ($has)
                            <span class="pv-row__vl">{{ $value }}</span>
                        @else
                            <span class="pv-row__vl pv-row__vl--muted">{{ __('app.label.not_set') }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    @livewire(\App\Livewire\MediaLibrary::class, ['variant' => 'payment-screenshots', 'recordId' => $record->id, 'hideWhenEmpty' => true], key('payment-proof-'.$record->id))
</div>

<style>
    .pv {
        font-size: .875rem;
        color: var(--t);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .pv-hero {
        position: relative;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1.2rem 1.4rem;
        background: var(--s);
        border: 1px solid var(--d);
        border-radius: .75rem;
    }
    .pv-hero__l {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .pv-hero__meta {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        flex-wrap: wrap;
    }
    .pv-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .72rem;
        font-weight: 600;
        color: var(--m);
        background: var(--soft);
        padding: .22rem .6rem;
        border-radius: 999px;
    }
    .pv-num {
        font-family: ui-monospace,SFMono-Regular,Menlo,monospace;
        font-size: .78rem;
        font-weight: 600;
        color: #475569;
        background: #f1f5f9;
        padding: .22rem .55rem;
        border-radius: .35rem;
        letter-spacing: .03em;
    }
    .dark .pv-num {
        color: #cbd5e1;
        background: rgba(255,255,255,.06);
    }
    .pv-hero__title {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0;
        color: var(--t);
        letter-spacing: -.005em;
        word-wrap: break-word;
    }
    .pv-hero__dates {
        display: inline-flex;
        align-items: center;
        gap: .55rem 1.25rem;
        flex-wrap: wrap;
        font-size: .8125rem;
        color: var(--m);
    }
    .pv-hero__dates b {
        color: var(--t);
        font-weight: 600;
    }
    .pv-hero__date {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }
    .pv-amount {
        flex-shrink: 0;
        align-self: center;
        font-size: 1.9rem;
        font-weight: 700;
        line-height: 1.05;
        color: #047857;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
    }
    .dark .pv-amount {
        color: #6ee7b7;
    }

    .pv-row__link {
        font-size: .8125rem;
        font-weight: 500;
        color: var(--accent);
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        text-decoration: none;
    }
    .pv-row__link:hover {
        text-decoration: underline;
    }
</style>
</x-filament-panels::page>
