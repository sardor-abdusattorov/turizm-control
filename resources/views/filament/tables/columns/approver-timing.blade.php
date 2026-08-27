@php
    /**
     * The timing cell of the approval chain: a verdict already has a
     * timestamp, an open slot gets the live SLA countdown instead.
     *
     * @var array{acted: ?string, due: ?\Carbon\CarbonInterface} $state
     */
    $state = $getState() ?? [];
@endphp

@if (! empty($state['acted']))
    <span class="fi-ta-text-item-label text-sm text-gray-950 dark:text-white">{{ $state['acted'] }}</span>
@elseif (! empty($state['due']))
    @include('filament.components.sla-countdown', ['due' => $state['due']])
@else
    <span class="fi-ta-placeholder text-sm text-gray-400 dark:text-gray-500">&mdash;</span>
@endif
