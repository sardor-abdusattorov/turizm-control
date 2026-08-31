<?php

namespace App\Filament\Resources\Requisitions;

use App\Filament\Resources\Requisitions\Pages\CreateRequisition;
use App\Filament\Resources\Requisitions\Pages\EditRequisition;
use App\Filament\Resources\Requisitions\Pages\ListRequisitions;
use App\Filament\Resources\Requisitions\Pages\ViewRequisition;
use App\Filament\Resources\Requisitions\Schemas\RequisitionForm;
use App\Filament\Resources\Requisitions\Tables\RequisitionsTable;
use App\Models\Requisition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequisitionResource extends Resource
{
    protected static ?string $model = Requisition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('app.label.documents');
    }

    public static function getModelLabel(): string
    {
        return __('app.label.requisition_single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.label.requisition_plural');
    }

    public static function getNavigationSort(): int
    {
        return 4;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->awaiting()->exists() ? 'warning' : null;
    }

    public static function form(Schema $schema): Schema
    {
        return RequisitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequisitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequisitions::route('/'),
            'create' => CreateRequisition::route('/create'),
            'view' => ViewRequisition::route('/{record}'),
            'edit' => EditRequisition::route('/{record}/edit'),
        ];
    }
}
