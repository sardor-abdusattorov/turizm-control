<?php

namespace App\Filament\Resources\ContractTemplates\Tables;

use App\Filament\Resources\ContractTemplates\ContractTemplateResource;
use App\Models\ContractTemplate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
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

                TextColumn::make('orderType.title')
                    ->label(__('app.label.order_type_single'))
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('language')
                    ->label(__('app.label.language'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContractTemplate::getLanguages()[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('sort')
                    ->label(__('app.label.sort'))
                    ->sortable(),

                ToggleColumn::make('status')
                    ->label(__('app.label.status'))
                    ->onIcon('heroicon-m-check-circle')
                    ->offIcon('heroicon-m-x-circle')
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('language')
                    ->label(__('app.label.language'))
                    ->options(ContractTemplate::getLanguages()),

                SelectFilter::make('order_type_id')
                    ->label(__('app.label.order_type_single'))
                    ->relationship('orderType', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(ContractTemplate::getStatuses()),
            ])
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
