@props(['position'])

@php
    use Filament\Facades\Filament;

    $styles = '';

    if (is_array($position)) {
        $styles = 'style="';
        foreach ($position as $key => $value) {
            $styles .= "--ad-theme-switcher-{$key}: {$value}; ";
        }
        $styles .= '"';
    }

    $hasDarkMode = Filament::hasDarkMode();
    $hasDarkModeForced = Filament::hasDarkModeForced();
@endphp

{{--
    Overrides the package partial solely to neutralise a tab-closing bug.

    Filament's theme-switcher button fires `(theme = '...') && close()`. That
    `close()` is meant to dismiss the dropdown the switcher normally sits in
    (the topbar user menu). Here the switcher is rendered standalone, so Alpine
    finds no `close` in scope and falls through to the global one —
    `window.close()` — which closes the browser tab outright whenever the tab
    was script-opened.

    Declaring a no-op `close()` in an ancestor Alpine scope gives the lookup
    something local to resolve to, so the click only switches the theme.
--}}
@if ($hasDarkMode && ! $hasDarkModeForced)
    <div class="fi-auth-theme-switcher-wrapper" x-data="{ close() {} }" {!! $styles !!}>
        <x-filament-panels::theme-switcher />
    </div>
@endif
