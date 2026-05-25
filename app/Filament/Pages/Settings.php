<?php

namespace App\Filament\Pages;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Settings as SettingsModel;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class Settings extends Page implements HasForms
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.settings';

    protected static ?string $slug = 'settings';

    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.main_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.administration');
    }

    public function getTitle(): string
    {
        return __('app.label.main_settings');
    }

    public function mount(): void
    {
        $this->form->fill($this->getSettingsData());
    }

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    protected function getSettingsData(): array
    {
        $data = [];
        $settings = SettingsModel::all();

        foreach ($settings as $setting) {
            data_set($data, $setting->key, $setting->value);
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make(__('app.label.settings'))
                    ->schema([
                        Tabs\Tab::make(__('app.label.tab_seo'))
                            ->schema([
                                TranslatableTabs::make('seo_translations')
                                    ->schema([
                                        TextInput::make('seo.title')
                                            ->label(__('app.label.seo_title'))
                                            ->required(),

                                        Textarea::make('seo.description')
                                            ->label(__('app.label.seo_description'))
                                            ->rows(4)
                                            ->required(),

                                        Textarea::make('seo.keywords')
                                            ->label(__('app.label.seo_keywords'))
                                            ->rows(4),
                                    ]),

                                Toggle::make('seo.indexing_enabled')
                                    ->label(__('app.label.seo_indexing_enabled'))
                                    ->helperText(__('app.helper.seo_indexing_enabled'))
                                    ->default(true),

                                FileUpload::make('seo.og_image')
                                    ->label(__('app.label.seo_og_image'))
                                    ->image()
                                    ->imageEditor()
                                    ->directory('images')
                                    ->disk('public')
                                    ->maxSize(2048)
                                    ->previewable()
                                    ->downloadable()
                                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                                    ->helperText(__('app.helper.seo_og_image')),
                            ]),

                        Tabs\Tab::make(__('app.label.tab_metrics'))
                            ->schema([
                                Textarea::make('metrics.yandex')
                                    ->label(__('app.label.metrics_yandex'))
                                    ->rows(6)
                                    ->helperText(__('app.helper.metrics_yandex')),

                                Textarea::make('metrics.google')
                                    ->label(__('app.label.metrics_google'))
                                    ->rows(6)
                                    ->helperText(__('app.helper.metrics_google')),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->saveSettings($data);

        Notification::make()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->success()
            ->send();
    }

    protected function saveSettings(array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && ! $this->isAssociativeArray($value)) {
                SettingsModel::set($fullKey, $value);
            } elseif (is_array($value)) {
                $this->saveSettings($value, $fullKey);
            } else {
                SettingsModel::set($fullKey, $value);
            }
        }
    }

    protected function isAssociativeArray(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
