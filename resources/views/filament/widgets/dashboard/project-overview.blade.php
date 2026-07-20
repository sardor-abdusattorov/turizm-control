<x-filament-widgets::widget>
    <section class="pjo">
        <div class="pjo__l">
            @if ($project)
                <a href="{{ $projectUrl }}" class="pjo__name" wire:navigate>{{ $project->name }}</a>
                <div class="pjo__sub">
                    @if ($project->starts_on)
                        <span>{{ $project->starts_on->format('d.m.Y') }}{{ $project->ends_on ? ' — '.$project->ends_on->format('d.m.Y') : '' }}</span>
                    @endif
                    @if ($project->venue)
                        <span>{{ $project->venue }}</span>
                    @endif
                    @if ($project->area_sqm !== null)
                        <span>
                            {{ __('app.label.area_sqm_short', ['sqm' => rtrim(rtrim(number_format((float) $project->area_sqm, 2, ',', ' '), '0'), ',')]) }}@if ($project->area_is_free) · {{ __('app.label.free') }}@elseif ($project->area_cost !== null) · {{ \App\Support\Money::format($project->area_cost) }} {{ $project->areaCurrency?->short_name }}@endif
                        </span>
                    @endif
                    @foreach ($counts as $label => $count)
                        <span>{{ $label }}: <b>{{ $count }}</b></span>
                    @endforeach
                </div>
            @else
                <span class="pjo__name">{{ __('app.label.project_single') }}</span>
                <div class="pjo__sub"><span>{{ __('app.message.pulse_pick_project') }}</span></div>
            @endif
        </div>

        {{-- Mounts the page's FilterAction — the same slide-over as the docs. --}}
        <button type="button" class="pjo__filters" wire:click="$parent.mountAction('filter')">
            @svg('heroicon-o-funnel', 'pjo__filters-ic')
            {{ __('app.label.filters') }}
            @if ($this->hasActiveFilters())
                <span class="pjo__dot"></span>
            @endif
        </button>
    </section>
</x-filament-widgets::widget>
