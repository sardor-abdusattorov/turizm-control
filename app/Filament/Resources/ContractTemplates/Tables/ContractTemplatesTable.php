<?php

namespace App\Filament\Resources\ContractTemplates\Tables;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\StatusToggleColumn;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.contract_template_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('template_file')
                    ->label(__('app.label.document'))
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-document-text')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '—')
                    ->limit(35)
                    ->url(fn (ContractTemplate $record) => $record->templateExists()
                        ? route('contract-templates.editor', ['template' => $record, 'mode' => 'view'])
                        : null,
                        shouldOpenInNewTab: true,
                    ),

                TextColumn::make('contractType.title')
                    ->sortable()
                    ->label(__('app.label.contract_type_single'))
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('sort')
                    ->label(__('app.label.sort'))
                    ->sortable(),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('contract_type_id')
                    ->label(__('app.label.contract_type_single'))
                    ->options(fn () => ContractType::getActive())
                    ->searchable(),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(ContractTemplate::getStatuses()),
            ])
            ->filtersFormColumns(2)
            ->recordUrl(fn (ContractTemplate $record) => ContractTemplateResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_contract_template') ?? false),
                ]),
            ]);
    }
}
