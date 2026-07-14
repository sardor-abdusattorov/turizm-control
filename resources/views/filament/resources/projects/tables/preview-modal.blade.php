@php
    use App\Enums\ParticipantRole;
    use App\Enums\PaymentStatus;

    /** @var \App\Models\Project $project */
    $project->loadMissing(['participants.currency', 'participants.contact', 'participants.sponsor', 'areaCurrency', 'standCurrency']);

    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

    $members = $project->participants->where('role', ParticipantRole::Participant)->values();
    $sponsors = $project->participants->where('role', ParticipantRole::Sponsor)->values();

    $feesTotal = $project->feesTotal();
    $paidTotal = $project->paidTotal();
    $paidStatus = PaymentStatus::fromPercent($feesTotal > 0 ? $paidTotal / $feesTotal * 100 : 0);
    $paidTint = match ($paidStatus) {
        PaymentStatus::FullyPaid => 'success',
        PaymentStatus::PartiallyPaid => 'warning',
        default => '',
    };

    $period = $project->starts_on
        ? $project->starts_on->format('d.m.Y').($project->ends_on ? ' — '.$project->ends_on->format('d.m.Y') : '')
        : '—';

    $galleryUrls = $project->galleryUrls();

    // A single fee currency is shown only when every participant shares it;
    // mixed-currency projects drop the suffix rather than mislead.
    $feeCodes = $project->participants->map(fn ($p) => $p->currency?->short_name)->filter()->unique()->values();
    $feeCurrency = $feeCodes->count() === 1 ? ' '.$feeCodes->first() : '';

    $blocks = [
        ['title' => __('app.label.participants'), 'rows' => $members, 'empty' => __('app.message.no_participants')],
        ['title' => __('app.label.sponsors'), 'rows' => $sponsors, 'empty' => null],
    ];
@endphp

<div class="pj" style="gap:1rem;">
    {{-- meta line --}}
    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <span class="pj-pill pj-pill--{{ $project->type->color() }}">{{ $project->type->label() }}</span>
        <span class="pj-pill pj-pill--{{ $project->status ? 'success' : 'gray' }}">
            {{ $project->status ? __('app.status.active') : __('app.status.inactive') }}
        </span>
        <span class="pj-hero__dates">{{ $period }}</span>
        @if ($project->venue)
            <span class="pj-hero__dates">· {{ $project->venue }}</span>
        @endif
        @foreach ($project->ordersViaContracts()->take(2) as $basisOrder)
            <span class="pj-chip">{{ trim(($basisOrder->number ? $basisOrder->number.' · ' : '').$basisOrder->title) }}</span>
        @endforeach
    </div>

    {{-- mini stats --}}
    <div class="pj-stats" style="grid-template-columns:repeat(auto-fit, minmax(8rem, 1fr));">
        <div class="pj-stat">
            <span class="pj-stat__lb">{{ __('app.label.fees_total') }}</span>
            <div class="pj-stat__vl">{{ $fmt($feesTotal) }}{{ $feeCurrency }}</div>
        </div>
        <div class="pj-stat {{ $paidTint ? 'pj-stat--'.$paidTint : '' }}">
            <span class="pj-stat__lb">{{ __('app.label.paid') }}</span>
            <div class="pj-stat__vl">{{ $fmt($paidTotal) }}{{ $feeCurrency }}</div>
        </div>
        <div class="pj-stat">
            <span class="pj-stat__lb">{{ __('app.label.participants') }}</span>
            <div class="pj-stat__vl">{{ $members->count() }}</div>
            <div class="pj-stat__sub">{{ __('app.label.sponsors') }}: {{ $sponsors->count() }}</div>
        </div>
        <div class="pj-stat">
            <span class="pj-stat__lb">{{ __('app.label.area_sqm') }}</span>
            <div class="pj-stat__vl">{{ $project->area_sqm !== null ? $fmt($project->area_sqm).' м²' : '—' }}</div>
        </div>
    </div>

    {{-- participants / sponsors --}}
    @foreach ($blocks as $block)
        @if ($block['rows']->isNotEmpty())
            <div>
                <div class="pj-hero__metric-lb" style="margin-bottom:.4rem;">{{ $block['title'] }} · {{ $block['rows']->count() }}</div>
                <div class="pj-table-wrap pj-scroll" style="border:1px solid var(--d);border-radius:.6rem;">
                    <table class="pj-table">
                        <tbody>
                        @foreach ($block['rows'] as $p)
                            @php $status = $p->paymentStatus(); @endphp
                            <tr>
                                <td>{{ $p->name }}</td>
                                <td class="pj-table__num">{{ $fmt($p->amount) }} {{ $p->currency?->short_name }}</td>
                                <td style="width:1%;">
                                    @if ((float) $p->amount > 0)
                                        <span class="pj-pill pj-pill--{{ $status->color() }}">{{ $status->label() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($block['empty'])
            <p class="pj-empty" style="padding:.5rem 0;text-align:left;">{{ $block['empty'] }}</p>
        @endif
    @endforeach

    {{-- gallery --}}
    @if ($galleryUrls !== [])
        <div>
            <div class="pj-hero__metric-lb" style="margin-bottom:.2rem;">{{ __('app.label.gallery') }} · {{ count($galleryUrls) }}</div>
            <x-image-gallery::image-gallery
                :images="$galleryUrls"
                :thumb-width="96"
                :thumb-height="72"
                rounded="rounded-lg"
            />
        </div>
    @endif
</div>
