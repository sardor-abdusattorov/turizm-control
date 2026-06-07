<?php

namespace App\Filament\Resources\ContractTemplates\Schemas;

use App\Filament\Support\DocumentUpload;
use App\Models\ContractTemplate;
use App\Models\OrderType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ContractTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->collapsible()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.label.contract_template_name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('order_type_id')
                            ->label(__('app.label.order_type_single'))
                            ->options(OrderType::getActive())
                            ->searchable()
                            ->preload()
                            ->placeholder(__('app.label.no_category'))
                            ->columnSpanFull(),

                        Select::make('language')
                            ->label(__('app.label.language'))
                            ->options(ContractTemplate::getLanguages())
                            ->default('ru')
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('sort')
                            ->label(__('app.label.sort'))
                            ->numeric()
                            ->default(0)
                            ->columnSpanFull(),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.contract_template_file'))
                    ->collapsible()
                    ->schema([
                        DocumentUpload::make('contract-templates', 'template_file')
                            ->label(__('app.label.template_file'))
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->required()
                            ->maxSize(10240)
                            ->helperText(__('app.helper.template_file')),

                        Actions::make([
                            Action::make('openTemplateInEditor')
                                ->label(__('app.action.open_template_in_editor'))
                                ->icon('heroicon-o-pencil-square')
                                ->color('primary')
                                ->url(fn (?ContractTemplate $record) => $record
                                    ? route('contract-templates.editor', ['template' => $record])
                                    : null)
                                ->visible(fn (?ContractTemplate $record) => $record?->templateExists() ?? false),
                        ])->columnSpanFull(),

                        Placeholder::make('editor_hint')
                            ->hiddenLabel()
                            ->content(__('app.helper.template_editor_independent')),

                        Placeholder::make('placeholders_hint')
                            ->label(__('app.label.available_placeholders'))
                            ->content(new HtmlString(self::placeholderHint())),
                    ]),
            ]);
    }

    protected static function placeholderHint(): string
    {
        $items = [
            '{{number}}', '{{title}}',
            '{{date.day}}', '{{date.month}}', '{{date.year}}', '{{date.full}}',
            '{{amount}}', '{{currency}}',
            '{{contact.name}}', '{{contact.legal_form}}', '{{contact.director}}',
            '{{contact.inn}}', '{{contact.pinfl}}', '{{contact.oked}}',
            '{{contact.address}}', '{{contact.phone}}', '{{contact.email}}',
            '{{contact.bank_account}}', '{{contact.bank_name}}', '{{contact.mfo}}',
        ];

        $tags = array_map(
            fn (string $item): string => '<code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:12px;">'.e($item).'</code>',
            $items,
        );

        return '<div style="display:flex;flex-wrap:wrap;gap:6px;">'.implode(' ', $tags).'</div>';
    }
}
