<?php

namespace App\Filament\Pages;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Department;
use App\Models\Settings as SettingsModel;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
    use HasPageShield;
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
        $data = $this->getSettingsData();

        $flow = $data['approval']['flow'] ?? [];
        $data['approval']['flow'] = array_map(
            fn (string $code) => ['code' => $code],
            is_array($flow) ? array_values($flow) : []
        );

        $this->form->fill($data);
    }

    /** @return array<string, string> */
    protected function getApproverDepartmentOptions(): array
    {
        $names = Department::query()
            ->whereIn('code', Department::FLOW_CODES)
            ->get()
            ->mapWithKeys(fn (Department $d) => [
                $d->code => $d->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();

        $fallbackLabels = [
            'legal' => __('app.label.department_legal'),
            'financial' => __('app.label.department_financial'),
            'accounting' => __('app.label.department_accounting'),
        ];

        $options = [];

        foreach (Department::FLOW_CODES as $code) {
            $options[$code] = $names[$code] ?? $fallbackLabels[$code] ?? $code;
        }

        return $options;
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
                        Tabs\Tab::make(__('app.label.tab_organization'))
                            ->schema([
                                TranslatableTabs::make('organization_translations')
                                    ->schema([
                                        TextInput::make('organization.name')
                                            ->label(__('app.label.organization_name'))
                                            ->maxLength(255),
                                    ]),

                                FileUpload::make('organization.logo_path')
                                    ->label(__('app.label.organization_logo'))
                                    ->image()
                                    ->imageEditor()
                                    ->directory('organization')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->previewable()
                                    ->downloadable()
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml']),
                            ]),

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
                                    ->acceptedFileTypes(['image/png', 'image/jpeg']),
                            ]),

                        Tabs\Tab::make(__('app.label.tab_approval_flow'))
                            ->schema([

                                Toggle::make('approval.enabled')
                                    ->label(__('app.label.approval_enabled'))
                                    ->default(true)
                                    ->live(),

                                Repeater::make('approval.flow')
                                    ->label(__('app.label.approval_flow'))
                                    ->reorderableWithDragAndDrop()
                                    ->reorderable()
                                    ->addActionLabel(__('app.action.add_department'))
                                    ->schema([
                                        Select::make('code')
                                            ->label(__('app.label.department_single'))
                                            ->options(fn () => $this->getApproverDepartmentOptions())
                                            ->required()
                                            ->native(false),
                                    ])
                                    ->itemLabel(
                                        fn (array $state): ?string => $this->getApproverDepartmentOptions()[$state['code'] ?? ''] ?? null
                                    )
                                    ->collapsible(),

                                TextInput::make('approval.sla_days')
                                    ->label(__('app.label.approval_sla_days'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(60)
                                    ->default(2),
                            ]),

                        Tabs\Tab::make(__('app.label.requisition_plural'))
                            ->schema([
                                Select::make('requisition.approver_ids')
                                    ->label(__('app.label.requisition_default_reviewer'))
                                    ->multiple()
                                    ->options(fn (): array => User::requisitionApproverOptionsGroupedByDepartment())
                                    ->allowHtml()
                                    ->searchable()
                                    ->preload()
                                    ->native(false),

                                TextInput::make('requisition.review_days')
                                    ->label(__('app.label.requisition_review_days'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(60)
                                    ->default(3),
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

        if (isset($data['approval']['flow']) && is_array($data['approval']['flow'])) {
            $data['approval']['flow'] = array_values(array_unique(array_filter(
                array_map(fn (array $item) => $item['code'] ?? null, $data['approval']['flow'])
            )));
        }

        $this->saveSettings($data);

        clear_settings_cache();

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
