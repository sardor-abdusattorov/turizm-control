<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Filament\Exports\ContactExporter;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('app.label.contact_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Contact::getTypes()[$state] ?? $state)
                    ->color(fn (string $state) => Contact::getTypeColors()[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('app.label.contact_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('inn')
                    ->label(__('app.label.inn'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('pinfl')
                    ->label(__('app.label.pinfl'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contact_person')
                    ->label(__('app.label.contact_person'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label(__('app.label.phone'))
                    ->toggleable(),

                TextColumn::make('email')
                    ->label(__('app.label.email'))
                    ->toggleable(),

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
                SelectFilter::make('type')
                    ->label(__('app.label.contact_type'))
                    ->options(Contact::getTypes()),

                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Contact::getStatuses()),
            ])
            ->recordUrl(fn (Contact $record) => ContactResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->exporter(ContactExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label(__('app.action.export_xlsx'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->exporter(ContactExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
