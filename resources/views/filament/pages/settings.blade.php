<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" style="margin-top: 30px">
                {{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
