<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use App\Models\ContractTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractEditor extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.contract-editor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'contracts/{record}/editor';

    public Contract $record;

    public ?ContractTemplate $template = null;

    /**
     * Form state, shape: { ru: {field_key: value}, uz: {...}, en: {...} }
     *
     * @var array<string, array<string, scalar|null>>
     */
    public array $formData = [];

    public string $previewLocale = 'ru';

    /**
     * @var array<int, string>
     */
    protected array $supportedLocales = ['ru', 'uz', 'en'];

    public function mount(string|int $record): void
    {
        $this->record = Contract::with(['orderType', 'contact', 'currency'])->findOrFail($record);

        abort_unless($this->record->canBeEditedBy(), 403);

        $this->template = ContractTemplate::defaultForOrderType($this->record->order_type_id);

        $stored = $this->record->getTranslations('data') ?: [];

        $initial = [];
        foreach ($this->supportedLocales as $locale) {
            $initial[$locale] = $stored[$locale] ?? [];
        }

        $this->formData = $initial;
        $this->previewLocale = in_array(app()->getLocale(), $this->supportedLocales, true)
            ? app()->getLocale()
            : 'ru';

        $this->form->fill($initial);
    }

    public function getTitle(): string
    {
        // Shield's permission discovery instantiates this page without
        // calling mount(), so $record may not be initialized yet.
        if (! isset($this->record)) {
            return __('app.label.contract_editor');
        }

        return __('app.label.contract_editor').': '.$this->record->number;
    }

    public function getSubheading(): ?string
    {
        if (! isset($this->record)) {
            return null;
        }

        return $this->record->getTranslation('title', app()->getLocale());
    }

    public static function getNavigationLabel(): string
    {
        return __('app.label.contract_editor');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label(__('app.action.download_pdf'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading(__('app.action.download_pdf'))
                ->modalSubmitActionLabel(__('app.action.download_pdf'))
                ->schema([
                    Select::make('locale')
                        ->label(__('app.label.language'))
                        ->options([
                            'ru' => __('app.label.ru'),
                            'uz' => __('app.label.uz'),
                            'en' => __('app.label.en'),
                        ])
                        ->default($this->previewLocale)
                        ->required()
                        ->native(false),
                ])
                ->action(fn (array $data): StreamedResponse => ContractsTable::streamContractPdf(
                    $this->record,
                    $data['locale'] ?? 'ru'
                )),
        ];
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->template) {
            return $schema->components([])->statePath('formData');
        }

        $fields = is_array($this->template->fields) ? $this->template->fields : [];

        $tabs = [];

        foreach ($this->supportedLocales as $locale) {
            $localeFields = [];

            foreach ($fields as $field) {
                $name = $field['name'] ?? null;
                $label = $field['label'] ?? $name;
                $type = $field['type'] ?? 'text';
                $required = (bool) ($field['required'] ?? false);

                if (! $name) {
                    continue;
                }

                $statePath = "{$locale}.{$name}";

                $localeFields[] = match ($type) {
                    'textarea' => Textarea::make($statePath)
                        ->label($label)
                        ->rows(3)
                        ->required($required)
                        ->live(debounce: 500),
                    'number' => TextInput::make($statePath)
                        ->label($label)
                        ->numeric()
                        ->required($required)
                        ->live(debounce: 500),
                    'date' => DatePicker::make($statePath)
                        ->label($label)
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->required($required)
                        ->live(),
                    default => TextInput::make($statePath)
                        ->label($label)
                        ->required($required)
                        ->live(debounce: 500),
                };
            }

            $tabs[] = Tab::make(strtoupper($locale))->schema($localeFields);
        }

        return $schema
            ->components([
                Tabs::make()->tabs($tabs),
            ])
            ->statePath('formData');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $clean = [];
        foreach ($this->supportedLocales as $locale) {
            $clean[$locale] = $state[$locale] ?? [];
        }

        $this->record->setTranslations('data', $clean);
        $this->record->save();

        Notification::make()
            ->title(__('app.message.contract_data_saved'))
            ->success()
            ->send();
    }

    public function setPreviewLocale(string $locale): void
    {
        if (in_array($locale, $this->supportedLocales, true)) {
            $this->previewLocale = $locale;
        }
    }

    /**
     * Render the template body with the current form values substituted
     * into {{placeholder}} tokens, for the currently selected preview
     * locale.
     */
    public function renderPreview(): string
    {
        if (! $this->template) {
            return '<p class="text-gray-500 italic">'
                .e(__('app.message.no_template_for_type'))
                .'</p>';
        }

        // Merge live form values with the contract's system placeholders
        // (number, amount, counterparty, dates) so the preview matches
        // what the PDF will look like.
        $values = $this->record->renderTemplateValues(
            $this->previewLocale,
            $this->formData[$this->previewLocale] ?? []
        );

        return $this->template->render($values, $this->previewLocale);
    }

    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }
}
