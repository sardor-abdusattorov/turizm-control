<?php

namespace App\Providers;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Contract;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ContractAccessPolicy;
use App\Services\Dashboard\DashboardContext;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->configureUrl();

        Gate::policy(Activity::class, ActivityPolicy::class);

        Gate::policy(Contract::class, ContractAccessPolicy::class);

        $this->app->scoped(DashboardContext::class);

        $this->configureRenderHooks();
    }

    private function configureRenderHooks(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.start',
            fn (): string => $this->renderSeoHead(),
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

    private function renderSeoHead(): string
    {
        $locale = app()->getLocale();

        $localized = fn (string $key): string => (string) (
            settings("seo.{$key}.{$locale}") ?: settings("seo.{$key}.ru", '')
        );

        $title = $localized('title');
        $description = $localized('description');
        $keywords = $localized('keywords');

        $ogImage = settings('seo.og_image');
        $ogImageUrl = is_string($ogImage) && $ogImage !== ''
            ? Storage::disk('public')->url($ogImage)
            : asset('images/favicon/android-chrome-512x512.png');

        $tags = [
            '<meta name="robots" content="'.((bool) settings('seo.indexing_enabled', false) ? 'index,follow' : 'noindex,nofollow').'">',
            '<meta property="og:type" content="website">',
            '<meta property="og:url" content="'.e(url()->current()).'">',
        ];

        if ($description !== '') {
            $tags[] = '<meta name="description" content="'.e($description).'">';
            $tags[] = '<meta property="og:description" content="'.e($description).'">';
        }

        if ($keywords !== '') {
            $tags[] = '<meta name="keywords" content="'.e($keywords).'">';
        }

        if ($title !== '') {
            $tags[] = '<meta property="og:title" content="'.e($title).'">';
        }

        $tags[] = '<meta property="og:image" content="'.e($ogImageUrl).'">';
        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = '<meta name="twitter:image" content="'.e($ogImageUrl).'">';

        return implode("\n", $tags);
    }

    public function boot(): void
    {
        $this->configureDB();
        $this->configureModels();
        $this->configureShieldPermissionKeys();
        $this->configureFilament();
        $this->configureLimit();
        $this->configureLanguageSwitch();
        $this->configureTranslatableTabs();

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

    private function configureShieldPermissionKeys(): void
    {
        FilamentShield::buildPermissionKeyUsing(
            fn (?string $affix, string $subject): string => $affix === null
                ? $subject
                : Str::snake($affix).'_'.Str::snake($subject),
        );
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

                ->userPreferredLocale(fn () => auth()->user()?->locale)
                ->visible(outsidePanels: true);
        });

        Event::listen(LocaleChanged::class, function (LocaleChanged $event): void {
            $user = auth()->user();

            if ($user instanceof User && $user->locale !== $event->locale) {
                $user->forceFill(['locale' => $event->locale])->saveQuietly();
            }
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
