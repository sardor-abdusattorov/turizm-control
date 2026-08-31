<x-filament-panels::page>
    <form wire:submit="updateProfile">
        {{ $this->profileForm }}
    </form>

    <form wire:submit="updatePassword" class="mt-8">
        {{ $this->passwordForm }}
    </form>

    <x-filament::section class="mt-8" aside>
        <x-slot name="heading">
            {{ __('app.label.browser_sessions') }}
        </x-slot>

        <x-slot name="description">
            {{ __('app.label.browser_sessions_description') }}
        </x-slot>

        @php
            $sessions = $this->getSessions();
            $sessionsCount = count($sessions);
        @endphp

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('app.label.browser_sessions_info') }}
            </p>

            @if ($sessionsCount === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('app.label.no_other_sessions') }}
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($sessions as $session)
                        @php
                            $platform = strtolower($session['platform']);
                            $isDesktop = str_contains($platform, 'windows')
                                || str_contains($platform, 'mac')
                                || str_contains($platform, 'linux');
                            $sessionIcon = $isDesktop
                                ? 'heroicon-o-computer-desktop'
                                : 'heroicon-o-device-phone-mobile';
                            $isCurrent = $session['is_current_device'];
                        @endphp

                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <x-filament::icon
                                    :icon="$sessionIcon"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400"
                                />
                            </div>

                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $session['platform'] }} — {{ $session['browser'] }}

                                    @if ($isCurrent)
                                        <span class="ml-2 text-success-600 dark:text-success-400 font-semibold">
                                            {{ __('app.label.this_device') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $session['ip_address'] }} ·
                                    {{ __('app.label.last_active') }}: {{ $session['last_active'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($sessionsCount > 1)
                    <div class="mt-4 flex justify-end">
                        {{ $this->logoutOtherSessionsAction }}
                    </div>
                @endif
            @endif
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
