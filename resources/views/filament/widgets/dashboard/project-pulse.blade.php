@php
    use App\Enums\ContractDirection;
    use App\Enums\ParticipantRole;
    use App\Enums\ProjectType;
    use App\Filament\Resources\Projects\BaseProjectResource;
    use App\Models\Contract;

    /** @var \App\Models\Project|null $project */
    $project = $this->project();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $fmt = fn ($n) => \App\Support\Money::format($n);
    $money = fn ($amount, ?string $cur) => $amount === null ? '—' : $fmt($amount).($cur ? ' '.$cur : '');
@endphp

<x-filament-widgets::widget>
    @if (! $project)
        <section class="pj" style="border:1px solid var(--d);border-radius:1rem;background:var(--s);padding:1.5rem;">
            <p class="pj-empty" style="padding:0;text-align:left;">{{ __('app.message.no_projects_yet') }}</p>
        </section>
    @else
        @php
            $project->loadMissing(['participants.currency']);

            $isInternalProject = $project->type === ProjectType::Internal;

            // Same visibility rule as everywhere: only the contracts this
            // viewer may see reach the tiles and the list below.
            $visibleContracts = $project->visibleContracts();

            $sumByDirection = fn (ContractDirection $direction) => $visibleContracts
                ->filter(fn ($c) => $c->contractType?->direction === $direction
                    && $c->status !== Contract::STATUS_REJECTED)
                ->groupBy(fn ($c) => $c->currency?->short_name ?? '')
                ->map(fn ($group) => $group->sum('amount'));
            $expenseTotals = $sumByDirection(ContractDirection::Expense);
            $incomeTotals = $sumByDirection(ContractDirection::Income);
            $moneyLines = fn ($totals) => $totals->map(fn ($v, $c) => $fmt($v).($c ? ' '.$c : ''))->implode(' · ');

            $members = $project->participants->where('role', ParticipantRole::Participant)->values();
            $sponsors = $project->participants->where('role', ParticipantRole::Sponsor)->values();

            $feesTotal = $project->feesTotal();
            $paidTotal = $project->paidTotal();
            $paidPercent = $feesTotal > 0 ? round($paidTotal / $feesTotal * 100) : 0;

            $currencies = $project->participants->map(fn ($p) => $p->currency?->short_name)->filter()->unique()->values();
            $feeCurrency = $currencies->count() === 1 ? $currencies->first() : '';

            $period = $project->starts_on
                ? $project->starts_on->format('d.m.Y').($project->ends_on ? ' — '.$project->ends_on->format('d.m.Y') : '')
                : '—';

            $projectUrl = BaseProjectResource::resourceFor($project)::getUrl('view', ['record' => $project]);
            $topContracts = $visibleContracts->take(5);
            $topParticipants = $project->participants->sortByDesc('amount')->take(5)->values();
        @endphp

        <div class="pj" style="display:flex;flex-direction:column;gap:1rem;">
            {{-- Hero: what & when, money on the right --}}
            <section class="pj-hero pj-hero--{{ $project->status ? 'success' : 'gray' }}">
                <div class="pj-hero__l">
                    <div class="pj-hero__meta">
                        <span class="pj-chip">{!! $ic($project->type === ProjectType::International ? 'heroicon-o-globe-alt' : 'heroicon-o-building-office-2', 14) !!} {{ $project->type->label() }}</span>
                        <span class="pj-pill pj-pill--{{ $project->status ? 'success' : 'gray' }}">
                            {{ $project->status ? __('app.status.active') : __('app.status.inactive') }}
                        </span>
                    </div>
                    <a href="{{ $projectUrl }}" class="pj-hero__title" style="text-decoration:none;color:inherit;">{{ $project->name }}</a>
                    <div class="pj-hero__dates">
                        <span style="display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;">{!! $ic('heroicon-o-calendar-days', 14) !!} {{ $period }}</span>
                        @if ($project->venue)
                            <span style="display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;">{!! $ic('heroicon-o-map-pin', 14) !!} {{ $project->venue }}</span>
                        @endif
                    </div>
                </div>
                <div class="pj-hero__r">
                    <span class="pj-hero__metric">{{ $fmt($feesTotal) }}</span>
                    <span class="pj-hero__metric-lb">{{ __('app.label.fees_total') }}{{ $feeCurrency ? ', '.$feeCurrency : '' }}</span>
                    @if ($feesTotal > 0)
                        <span class="pj-hero__metric-lb">{{ __('app.label.paid') }}: {{ $fmt($paidTotal) }} · {{ $paidPercent }}%</span>
                    @endif
                </div>
            </section>

            {{-- Tiles --}}
            <section class="pj-stats">
                @if ($isInternalProject)
                    <div class="pj-stat">
                        <span class="pj-stat__lb">{!! $ic('heroicon-o-calculator', 13) !!} {{ __('app.label.estimate_amount') }}</span>
                        <div class="pj-stat__vl">{{ $project->estimate_amount !== null ? $fmt($project->estimate_amount).' UZS' : '—' }}</div>
                        <div class="pj-stat__sub">{{ __('app.label.final_amount') }}: {{ $project->final_amount !== null ? $fmt($project->final_amount) : '—' }}</div>
                    </div>
                @else
                    <div class="pj-stat">
                        <span class="pj-stat__lb">{!! $ic('heroicon-o-square-3-stack-3d', 13) !!} {{ __('app.label.area_sqm') }}</span>
                        <div class="pj-stat__vl">{{ $project->area_sqm !== null ? $fmt($project->area_sqm).' м²' : '—' }}</div>
                        <div class="pj-stat__sub">{{ __('app.label.stand_cost') }}: {{ $money($project->stand_cost, $project->standCurrency?->short_name) }}</div>
                    </div>
                @endif
                <div class="pj-stat">
                    <span class="pj-stat__lb">{!! $ic('heroicon-o-arrow-trending-down', 13) !!} {{ __('app.contract.direction.expense') }} · {{ __('app.label.contracts') }}</span>
                    <div class="pj-stat__vl" @if ($expenseTotals->count() > 1) style="font-size:1rem;" @endif>{{ $expenseTotals->isNotEmpty() ? $moneyLines($expenseTotals) : '—' }}</div>
                    @if ($incomeTotals->isNotEmpty())
                        <div class="pj-stat__sub">{{ __('app.contract.direction.income') }}: {{ $moneyLines($incomeTotals) }}</div>
                    @endif
                </div>
                <div class="pj-stat">
                    <span class="pj-stat__lb">{!! $ic('heroicon-o-document-text', 13) !!} {{ __('app.label.contracts') }}</span>
                    <div class="pj-stat__vl">{{ $visibleContracts->count() }}</div>
                </div>
                <div class="pj-stat">
                    <span class="pj-stat__lb">{!! $ic('heroicon-o-user-group', 13) !!} {{ __('app.label.participants') }}</span>
                    <div class="pj-stat__vl">{{ $isInternalProject && $project->attendees_count !== null ? $project->attendees_count : $members->count() }}</div>
                    <div class="pj-stat__sub">{{ __('app.label.sponsors') }}: {{ $sponsors->count() }}</div>
                </div>
            </section>

            {{-- Freshest contracts + top participants, side by side --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(20rem, 1fr));gap:1rem;">
                <section class="ow-card">
                    <header class="ow-hd">
                        <span class="ow-hd__ic">{!! $ic('heroicon-o-document-text', 18) !!}</span>
                        <h2 class="ow-hd__t">{{ __('app.label.contracts') }}</h2>
                        <span class="pj-count">{{ $visibleContracts->count() }}</span>
                    </header>
                    @if ($topContracts->isEmpty())
                        <p class="pj-empty">{{ __('app.message.no_contracts') }}</p>
                    @else
                        <div class="pj-table-wrap">
                            <table class="pj-table">
                                <tbody>
                                @foreach ($topContracts as $contract)
                                    <tr>
                                        <td style="white-space:nowrap;font-weight:600;">
                                            <a class="pj-link" href="{{ \App\Filament\Resources\Contracts\ContractResource::getUrl('view', ['record' => $contract]) }}">{{ $contract->number }}</a>
                                        </td>
                                        <td>
                                            @if ($contract->contractType)
                                                <span class="pj-pill pj-pill--{{ $contract->contractType->direction?->color() ?? 'gray' }}">{{ $contract->contractType->title }}</span>
                                            @endif
                                        </td>
                                        <td class="pj-table__num">{{ $fmt($contract->amount) }} {{ $contract->currency?->short_name }}</td>
                                        <td style="width:1%;"><span class="pj-pill pj-pill--{{ $contract->status->color() }}">{{ $contract->status->label() }}</span></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="ow-card">
                    <header class="ow-hd">
                        <span class="ow-hd__ic">{!! $ic('heroicon-o-user-group', 18) !!}</span>
                        <h2 class="ow-hd__t">{{ __('app.label.participants') }}</h2>
                        <span class="pj-count">{{ $project->participants->count() }}</span>
                    </header>
                    @if ($topParticipants->isEmpty())
                        <p class="pj-empty">{{ __('app.message.no_participants') }}</p>
                    @else
                        <div class="pj-table-wrap">
                            <table class="pj-table">
                                <tbody>
                                @foreach ($topParticipants as $participant)
                                    @php $payStatus = $participant->paymentStatus(); @endphp
                                    <tr>
                                        <td>{{ $participant->name }}</td>
                                        <td class="pj-table__num">{{ $fmt($participant->amount) }} {{ $participant->currency?->short_name }}</td>
                                        <td style="width:1%;">
                                            @if ((float) $participant->amount > 0)
                                                <span class="pj-pill pj-pill--{{ $payStatus->color() }}">{{ $payStatus->label() }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div>
                <x-filament::button tag="a" href="{{ $projectUrl }}" icon="heroicon-o-arrow-top-right-on-square">
                    {{ __('app.action.open_project') }}
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
