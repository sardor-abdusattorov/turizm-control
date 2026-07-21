@php
    /** @var array{avatar: ?string, name: ?string, sub: ?string} $person */
    $person = $getState() ?? [];
@endphp

<div class="tbl-person">
    @if (! empty($person['avatar']))
        <img class="tbl-person__ava" src="{{ $person['avatar'] }}" alt="" loading="lazy">
    @endif
    <span class="tbl-person__meta">
        <span class="tbl-person__name">{{ $person['name'] ?? '' }}</span>
        @if (! empty($person['sub']))
            <span class="tbl-person__sub">{{ $person['sub'] }}</span>
        @endif
    </span>
</div>
