<?php

namespace App\Providers;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Contract;
use App\Policies\ActivityPolicy;
use App\Policies\ContractAccessPolicy;
use App\Services\Dashboard\DashboardContext;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Override the Shield-generated ContractPolicy with our subclass so the
        // delete status rule survives `project:init` / `shield:generate`, which
        // overwrite the generated policy file.
        Gate::policy(Contract::class, ContractAccessPolicy::class);

        $this->app->scoped(DashboardContext::class);

        $this->configureRenderHooks();
    }

    private function configureRenderHooks(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.start',
            fn (): string => '<meta name="robots" content="noindex,nofollow">'
        );

        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => '
                <link rel="apple-touch-icon" sizes="180x180" href="'.asset('/images/favicon/apple-touch-icon.png').'">
                <link rel="icon" type="image/png" sizes="32x32" href="'.asset('/images/favicon/favicon-32x32.png').'">
                <link rel="icon" type="image/png" sizes="16x16" href="'.asset('/images/favicon/favicon-16x16.png').'">
                <link rel="manifest" href="'.asset('/images/favicon/site.webmanifest').'">
                <link rel="mask-icon" href="'.asset('/images/favicon/safari-pinned-tab.svg').'" color="#5bbad5">
            '
        );

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => '<script>window.addEventListener("pageshow",function(e){if(e.persisted){window.location.reload();}});</script>'
        );
    }

    public function boot(): void
    {
        $this->configureDB();
        $this->configureModels();
        $this->configureFilament();
        $this->configureLimit();
        $this->configureLanguageSwitch();
        $this->configureTranslatableTabs();
        $this->configureUrl();

        Filament::serving(fn () => $this->translateFilamentLoggerConfig());
    }

    private function translateFilamentLoggerConfig(): void
    {
        $tabs = ['all', 'high_risk', 'destructive', 'auth_issues', 'failed_logins', 'destructive_recent', 'auth_anomalies'];

        foreach ($tabs as $key) {
            config(["filament-logger.activity_filters.saved.{$key}.label" => __("filament-logger::filament-logger.tabs.{$key}")]);
        }

        $dates = ['today', 'last_24_hours', 'last_7_days', 'last_30_days', 'this_month'];

        foreach ($dates as $key) {
            config(["filament-logger.activity_filters.date_presets.{$key}" => __("filament-logger::filament-logger.dates.{$key}")]);
        }
    }

    private function configureDB(): void
    {
        DB::prohibitDestructiveCommands($this->app->environment('production'));
    }

    /**
     * Behind the TLS-terminating reverse proxy the app is served over HTTPS, so
     * force generated URLs (assets, links) to https in production — otherwise
     * asset() emits http:// and the browser flags mixed content.
     */
    private function configureUrl(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configureModels(): void
    {
        Model::preventAccessingMissingAttributes();

        Model::unguard();
    }

    private function configureFilament(): void
    {
        FilamentShield::prohibitDestructiveCommands($this->app->isProduction());

        Column::configureUsing(fn (Column $column) => $column->toggleable());

        Table::configureUsing(fn (Table $table) => $table
            ->reorderableColumns()
            ->deferColumnManager(false)
            ->deferFilters(false)
            ->paginationPageOptions([10, 25, 50])
        );

        ActionGroup::configureUsing(
            fn (ActionGroup $group) => $group
                ->label(fn (): string => __('app.label.actions'))
                ->button()
                ->color('gray')
                ->tooltip(fn (): string => __('app.label.actions')),
        );
    }

    private function configureLimit(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }

    private function configureLanguageSwitch(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['ru', 'uz', 'en'])
                ->labels([
                    'ru' => __('app.label.ru'),
                    'uz' => __('app.label.uz'),
                    'en' => __('app.label.en'),
                ])
                ->visible(outsidePanels: true);
        });
    }

    private function configureTranslatableTabs(): void
    {
        TranslatableTabs::configureUsing(function (TranslatableTabs $component) {
            $component
                ->localesLabels([
                    'ru' => __('app.label.ru'),
                    'uz' => __('app.label.uz'),
                    'en' => __('app.label.en'),
                ])
                ->locales(['ru', 'uz', 'en'])
                ->addDirectionByLocale()
                ->addEmptyBadgeWhenAllFieldsAreEmpty(emptyLabel: __('app.label.empty'))
                ->addSetActiveTabThatHasValue();
        });
    }
}
