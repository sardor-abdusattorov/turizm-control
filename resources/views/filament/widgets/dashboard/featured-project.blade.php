@php
    use App\Enums\ParticipantRole;
    use App\Filament\Resources\Projects\BaseProjectResource;

    /** @var \App\Models\Project|null $project */
    $project = $this->featuredProject();
@endphp

<x-filament-widgets::widget>
    @if ($project)
        @php
            $ic = fn (string $name, int $size = 14) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
            $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

            $members = $project->participants->where('role', ParticipantRole::Participant);
            $sponsors = $project->participants->where('role', ParticipantRole::Sponsor);
            $fees = $project->feesTotal();
            $paid = $project->paidTotal();
            $percent = $fees > 0 ? (int) round($paid / $fees * 100) : 0;

            $today = today();
            if ($project->starts_on->lte($today) && $project->ends_on && $project->ends_on->gte($today)) {
                $timing = __('app.dashboard.featured_running', ['date' => $project->ends_on->format('d.m.Y')]);
                $tone = 'success';
            } elseif ($project->starts_on->gt($today)) {
                $timing = __('app.dashboard.featured_upcoming', [
                    'days' => (int) $today->diffInDays($project->starts_on),
                    'date' => $project->starts_on->format('d.m.Y'),
                ]);
                $tone = 'info';
            } else {
                $timing = __('app.dashboard.featured_past', ['date' => ($project->ends_on ?? $project->starts_on)->format('d.m.Y')]);
                $tone = 'gray';
            }

            $url = BaseProjectResource::resourceFor($project)::getUrl('view', ['record' => $project]);
        @endphp

        <div class="pj">
            <section class="pj-hero pj-hero--{{ $tone === 'gray' ? 'gray' : ($tone === 'info' ? 'info' : 'success') }}" style="gap:1rem 1.5rem;">
                <div class="pj-hero__l">
                    <div class="pj-hero__meta">
                        <span class="pj-chip">{!! $ic('heroicon-o-presentation-chart-bar') !!} {{ __('app.dashboard.featured_project') }}</span>
                        <span class="pj-pill pj-pill--{{ $project->type->color() }}">{{ $project->type->label() }}</span>
                        <span class="pj-pill pj-pill--{{ $tone === 'gray' ? 'gray' : ($tone === 'info' ? 'info' : 'success') }}">{{ $timing }}</span>
                    </div>
                    <a href="{{ $url }}" class="pj-hero__title pj-link" style="font-size:1.25rem;">{{ $project->name }}</a>
                    <div class="pj-hero__dates">
                        {!! $ic('heroicon-o-calendar-days') !!}
                        {{ $project->starts_on->format('d.m.Y') }}{{ $project->ends_on ? ' — '.$project->ends_on->format('d.m.Y') : '' }}
                        <span style="margin-left:.75rem;">{!! $ic('heroicon-o-user-group') !!} {{ $members->count() }}</span>
                        <span>{!! $ic('heroicon-o-star') !!} {{ $sponsors->count() }}</span>
                        <span>{!! $ic('heroicon-o-document-text') !!} {{ (int) ($project->contracts_count ?? 0) }}</span>
                    </div>
                </div>
                <div class="pj-hero__r" style="min-width:14rem;align-items:stretch;gap:.4rem;">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;">
                        <span class="pj-hero__metric-lb">{{ __('app.label.paid') }}</span>
                        <span class="pj-hero__metric" style="font-size:1.2rem;">{{ $fmt($paid) }} <span style="font-weight:500;color:var(--m);">/ {{ $fmt($fees) }}</span></span>
                    </div>
                    <div class="pj-progress">
                        <span class="pj-progress__fill pj-progress__fill--{{ $percent >= 100 ? 'success' : 'warning' }}" style="width:{{ min(100, $percent) }}%;"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--m2);">
                        <span>{{ $percent }}%</span>
                        <span>{{ __('app.label.remaining') }}: {{ $fmt(max(0, $fees - $paid)) }}</span>
                    </div>
                </div>
            </section>
        </div>
    @endif
</x-filament-widgets::widget>
