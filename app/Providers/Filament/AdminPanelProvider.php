<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ProfileSettings;
use App\Filament\Widgets\ContractStatsWidget;
use App\Filament\Widgets\ContractsTrendChartWidget;
use App\Filament\Widgets\Dashboard\ApprovalHealthWidget;
use App\Filament\Widgets\Dashboard\DashboardHeaderWidget;
use App\Filament\Widgets\Dashboard\MyApprovalQueueWidget;
use App\Filament\Widgets\Dashboard\MyContractsInReviewWidget;
use App\Filament\Widgets\Dashboard\OutstandingPaymentsWidget;
use App\Filament\Widgets\Dashboard\RecentActivityWidget;
use App\Filament\Widgets\LatestPaymentsWidget;
use App\Filament\Widgets\PaymentStatsWidget;
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
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
            ->brandName(fn (): string => settings('organization.name.'.app()->getLocale()) ?: 'PR-Kontrol')
            ->brandLogo(fn (): string => asset('images/logo.png'))
            ->brandLogoHeight('3rem')
            ->login()
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::Blue,
                'success' => Color::Emerald,
            ])
            ->spa()
            ->spaUrlExceptions([
                '*/editor',
                '*/editor?*',
            ])
            ->font('Inter')
            ->navigationItems([

            ])
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label(fn () => __('app.label.profile_settings'))
                    ->url(fn (): string => ProfileSettings::getUrl())
                    ->visible(fn (): bool => ProfileSettings::canAccess())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->navigationGroups([

                NavigationGroup::make()
                    ->label(fn () => __('app.label.documents')),

                NavigationGroup::make()
                    ->label(fn () => __('app.label.projects')),

                // Daily-work groups above stay open; the reference registry
                // and admin sections start collapsed to keep the sidebar short.
                NavigationGroup::make()
                    ->label(fn () => __('app.label.resources'))
                    ->collapsed(),

                NavigationGroup::make()
                    ->label(fn () => __('app.label.administration'))
                    ->collapsed(),
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
                DashboardHeaderWidget::class,
                MyApprovalQueueWidget::class,
                MyContractsInReviewWidget::class,
                ContractStatsWidget::class,
                PaymentStatsWidget::class,
                OutstandingPaymentsWidget::class,
                LatestPaymentsWidget::class,
                ApprovalHealthWidget::class,
                ContractsTrendChartWidget::class,
                RecentActivityWidget::class,
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
