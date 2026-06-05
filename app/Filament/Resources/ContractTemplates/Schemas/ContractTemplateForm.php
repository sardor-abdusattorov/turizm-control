<?php

namespace App\Filament\Resources\ContractTemplates\Schemas;

use App\Models\ContractTemplate;
use App\Models\OrderType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
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
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.label.contract_template_name'))
                                    ->required()
                                    ->maxLength(255),

                                Select::make('order_type_id')
                                    ->label(__('app.label.order_type_single'))
                                    ->options(OrderType::getActive())
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(__('app.label.no_category')),

                                Select::make('language')
                                    ->label(__('app.label.language'))
                                    ->options(ContractTemplate::getLanguages())
                                    ->default('ru')
                                    ->required()
                                    ->native(false),

                                TextInput::make('sort')
                                    ->label(__('app.label.sort'))
                                    ->numeric()
                                    ->default(0),
                            ]),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),

                Section::make(__('app.label.contract_template_file'))
                    ->schema([
                        FileUpload::make('template_file')
                            ->label(__('app.label.template_file'))
                            ->disk('local')
                            ->directory('contract-templates')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->required()
                            ->downloadable()
                            ->maxSize(10240)
                            ->helperText(__('app.helper.template_file')),

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
