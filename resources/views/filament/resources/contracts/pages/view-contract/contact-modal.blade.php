@php
    /**
     * Body of the native Filament counterparty modal — the heading, icon and
     * close button ride on the Action itself.
     *
     * @var list<array{0: string, 1: list<array{0: string, 1: string, 2: string}>}> $groups
     */
    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
@endphp

<div class="cw">
    @foreach ($groups as [$groupLabel, $rows])
        <div class="cw-contact-group">
            <div class="cw-contact-group__t">{{ $groupLabel }}</div>
            <div class="cw-contact-rows">
                @foreach ($rows as [$icon, $label, $value])
                    <div class="cw-crow">
                        <span class="cw-crow__ic">{!! $ic($icon) !!}</span>
                        <span class="cw-crow__lb">{{ $label }}</span>
                        <span class="cw-crow__vl">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
