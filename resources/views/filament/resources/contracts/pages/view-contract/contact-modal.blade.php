        {{-- Contact detail modal --}}
        @if ($contact)
            <div class="cw-modal" x-show="contactOpen" x-cloak style="display:none;" role="dialog" aria-modal="true" @keydown.escape.window="contactOpen = false">
                <div class="cw-modal__bg" @click="contactOpen = false"></div>
                <div class="cw-modal__card" style="max-width:34rem;"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100">
                    <div class="cw-modal__hd">
                        <span style="background:var(--soft);box-shadow:inset 0 0 0 1px var(--d);width:2.6rem;height:2.6rem;border-radius:.65rem;display:inline-flex;align-items:center;justify-content:center;color:var(--m);flex-shrink:0;">
                            {!! $ic('heroicon-o-building-office-2', 22) !!}
                        </span>
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ $contact->name }}</div>
                            @if ($contact->legal_form)
                                <div class="cw-modal__dp">{{ $contact->legal_form }}</div>
                            @endif
                        </div>
                        <button type="button" class="cw-modal__x" @click="contactOpen = false" aria-label="{{ __('app.action.cancel') }}">{!! $ic('heroicon-m-x-mark', 16) !!}</button>
                    </div>
                    <div class="cw-modal__bd">
                        @foreach ($contactGroups as [$groupLabel, $rows])
                            <div class="cw-contact-group">
                                <div class="cw-contact-group__t">{{ $groupLabel }}</div>
                                <div class="cw-contact-rows">
                                    @foreach ($rows as [$ic_, $lb, $vl])
                                        <div class="cw-crow">
                                            <span class="cw-crow__ic">{!! $ic($ic_, 16) !!}</span>
                                            <span class="cw-crow__lb">{{ $lb }}</span>
                                            <span class="cw-crow__vl">{{ $vl }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
