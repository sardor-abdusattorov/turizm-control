<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ProfileSettings;
use App\Filament\Widgets\ContractStatsWidget;
use App\Services\Telegram\TelegramService;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Openplain\FilamentShadcnTheme\Color;
use Spatie\Permission\Models\Role;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->authGuard('web')
            ->passwordReset()
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('3rem')
            ->login()
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::Default,

            ])
            ->spa()
            ->font('Inter')
            ->navigationItems([

            ])
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label(fn () => __('app.label.profile_settings'))
                    ->url(fn (): string => ProfileSettings::getUrl())
                    ->icon('heroicon-o-user-circle'),

                'telegram' => Action::make('telegram')
                    ->label(fn () => __('app.label.connect_telegram'))
                    ->url(fn (): string => route('telegram.connect'))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (): bool => app(TelegramService::class)->isConfigured()),
            ])
            ->navigationGroups([

                NavigationGroup::make()
                    ->label(fn () => __('app.label.documents')),

                NavigationGroup::make()
                    ->label(fn () => __('app.label.resources')),

                NavigationGroup::make()
                    ->label(fn () => __('app.label.administration')),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                ContractStatsWidget::class,
            ])
            ->resources([

            ])
            ->plugins([

                FilamentShieldPlugin::make()
                    ->navigationGroup(fn () => __('app.label.administration'))
                    ->navigationSort(5)
                    ->navigationBadge(fn () => (string) Role::count()),

                AuthDesignerPlugin::make()
                    ->defaults(fn ($config) => $config
                        ->media(asset('/images/background.jpeg'))
                        ->mediaPosition(MediaPosition::Right)
                        ->mediaSize('60%')
                    )
                    ->login()
                    ->passwordReset()
                    ->emailVerification()
                    ->themeToggle(bottom: '1rem', left: '50%'),

            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
