<?php

namespace App\Filament\Resources\ContractTemplates;

use App\Filament\Resources\ContractTemplates\Pages\CreateContractTemplate;
use App\Filament\Resources\ContractTemplates\Pages\EditContractTemplate;
use App\Filament\Resources\ContractTemplates\Pages\ListContractTemplates;
use App\Filament\Resources\ContractTemplates\Pages\ViewContractTemplate;
use App\Filament\Resources\ContractTemplates\Schemas\ContractTemplateForm;
use App\Filament\Resources\ContractTemplates\Tables\ContractTemplatesTable;
use App\Models\ContractTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.resources');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.contract_template_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.contract_template_plural');
    }

    public static function getNavigationSort(): int
    {
        return 6;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ContractTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractTemplates::route('/'),
            'create' => CreateContractTemplate::route('/create'),
            'view' => ViewContractTemplate::route('/{record}'),
            'edit' => EditContractTemplate::route('/{record}/edit'),
        ];
    }
}
