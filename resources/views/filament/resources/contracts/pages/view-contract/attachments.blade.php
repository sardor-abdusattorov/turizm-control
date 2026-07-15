{{-- Attachments — the contract dossier: signed scan, buyruq copy, proposals,
     sketches, invoice, SWIFT slip, act, bank fees. Uploading stays open here
     through the whole life of the contract — the signed scan, SWIFT and act
     arrive AFTER approval, and filing them is not an edit of the terms. --}}
<div x-show="tab === 'attachments'" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="cw-panel">
    <section class="cw-card">
        <div class="cw-hd">
            <span class="cw-hd__ic">{!! $ic('heroicon-o-paper-clip') !!}</span>
            <h2 class="cw-hd__t">{{ __('app.label.attachments') }}</h2>
            @if ($this->canManageAttachments())
                <button type="button" class="cw-btn cw-btn--primary" style="margin-left:auto"
                    x-on:click="$wire.mountAction('uploadAttachments')">
                    {!! $ic('heroicon-m-plus', 15) !!} {{ __('app.action.upload_files') }}
                </button>
            @endif
        </div>

        @php $attachments = $this->attachments(); @endphp

        @if ($attachments->isEmpty())
            <div class="cw-empty">{{ __('app.message.no_attachments') }}</div>
        @else
            <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.75rem">
                @foreach ($attachments as $attachment)
                    <div class="cw-doc">
                        <span class="cw-doc__ic">{!! $ic('heroicon-o-document-text', 18) !!}</span>
                        <div style="flex:1;min-width:0">
                            <div class="cw-doc__nm">{{ $attachment->original_name }}</div>
                            <div class="cw-doc__mt">
                                {{ $attachment->sizeLabel() }} · {{ $attachment->created_at?->format('d.m.Y H:i') }}@if ($attachment->uploader) · {{ $attachment->uploader->name }}@endif
                            </div>
                        </div>
                        @if ($attachment->type)
                            <span class="cw-pill cw-pill--gray">{{ $attachment->type->label() }}</span>
                        @endif
                        <div class="cw-doc__act">
                            @if ($url = $this->attachmentUrl($attachment))
                                <a class="cw-btn cw-btn--ghost" href="{{ $url }}" target="_blank" rel="noopener"
                                    title="{{ __('app.action.download') }}">
                                    {!! $ic('heroicon-o-arrow-down-tray', 15) !!}
                                </a>
                            @endif
                            @if ($this->canManageAttachments())
                                <button type="button" class="cw-btn cw-btn--ghost"
                                    title="{{ __('app.action.delete') }}"
                                    x-on:click="$wire.mountAction('deleteAttachment', { attachment: {{ $attachment->id }} })">
                                    {!! $ic('heroicon-o-trash', 15) !!}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
