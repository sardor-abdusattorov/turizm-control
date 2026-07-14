<?php

namespace App\Filament\Resources\ContractTypes;

use App\Filament\Resources\ContractTypes\Pages\CreateContractType;
use App\Filament\Resources\ContractTypes\Pages\EditContractType;
use App\Filament\Resources\ContractTypes\Pages\ListContractTypes;
use App\Filament\Resources\ContractTypes\Pages\ViewContractType;
use App\Filament\Resources\ContractTypes\Schemas\ContractTypeForm;
use App\Filament\Resources\ContractTypes\Tables\ContractTypesTable;
use App\Models\ContractType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractTypeResource extends Resource
{
    protected static ?string $model = ContractType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.resources');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.contract_type_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.contract_type_plural');
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ContractTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractTypes::route('/'),
            'create' => CreateContractType::route('/create'),
            'view' => ViewContractType::route('/{record}'),
            'edit' => EditContractType::route('/{record}/edit'),
        ];
    }
}
