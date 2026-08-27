<div>
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! svg($this->headerIcon(), '', ['width' => 18, 'height' => 18])->toHtml() !!}</span>
            <h2 class="ow-hd__t">{{ $this->headerTitle() }}</h2>
        </header>

        <div class="ap-body">
            @if ($notice = $this->lockedNotice())
                <p class="ap-notice">
                    {!! svg('heroicon-m-lock-closed', '', ['width' => 14, 'height' => 14])->toHtml() !!}
                    <span>{{ $notice }}</span>
                </p>
            @endif

            {{ $this->form }}

            @if ($this->canManage())
                <div class="ap-actions">
                    <x-filament::button wire:click="save" wire:loading.attr="disabled" icon="heroicon-m-check">
                        {{ __('app.action.save') }}
                    </x-filament::button>
                </div>
            @endif
        </div>
    </section>
</div>
