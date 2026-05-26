<x-filament-panels::page>
    <form wire:submit="updateProfile">
        {{ $this->profileForm }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit">
                {{ __('app.action.update') }}
            </x-filament::button>
        </div>
    </form>

    <form wire:submit="updatePassword" class="mt-8">
        {{ $this->passwordForm }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit">
                {{ __('app.action.update') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Browser Sessions Section --}}
    <x-filament::section class="mt-8" aside>
        <x-slot name="heading">
            {{ __('app.label.browser_sessions') }}
        </x-slot>

        <x-slot name="description">
            {{ __('app.label.browser_sessions_description') }}
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('app.label.browser_sessions_info') }}
            </p>

            @if(count($this->getSessions()) > 0)
                <div class="space-y-4">
                    @foreach($this->getSessions() as $session)
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0">
                                @php
                                    $platform = strtolower($session['platform']);
                                    $isDesktop = str_contains($platform, 'windows')
                                        || str_contains($platform, 'mac')
                                        || str_contains($platform, 'linux');
                                @endphp

                                <x-filament::icon
                                    :icon="$isDesktop ? 'heroicon-o-computer-desktop' : 'heroicon-o-device-phone-mobile'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400"
                                />
                            </div>

                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $session['platform'] }} - {{ $session['browser'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $session['ip_address'] }}
                                    @if($session['is_current_device'])
                                        <span class="text-success-600 dark:text-success-400 font-semibold">
                                            {{ __('app.label.this_device') }}
                                        </span>
                                    @else
                                        <span>{{ __('app.label.last_active') }}: {{ $session['last_active'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex justify-end">
                    {{ $this->logoutOtherSessionsAction }}
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('app.label.no_other_sessions') }}
                </p>
            @endif
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
