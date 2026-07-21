<div>
    @if ($this->hasFiles() || ! $hideWhenEmpty)
        <section class="ow-card">
            <header class="ow-hd">
                <span class="ow-hd__ic">{!! svg($this->headerIcon(), '', ['width' => 18, 'height' => 18])->toHtml() !!}</span>
                <h2 class="ow-hd__t">{{ $this->headerTitle() }}</h2>
            </header>
            <div class="ml-body">
                @if ($this->hasFiles())
                    {{ $this->form }}
                @else
                    <p class="ml-empty">{{ __('app.message.no_files') }}</p>
                @endif
            </div>
        </section>
    @endif
</div>
