{{-- Embeds a Filament table widget inside a modal (breakdown / audit modals).
     One place so every count-badge modal is a stock Filament table, no custom
     markup. Expects: $widget (class-string), $params (mount props), $key. --}}
@livewire($widget, $params, key($key))
