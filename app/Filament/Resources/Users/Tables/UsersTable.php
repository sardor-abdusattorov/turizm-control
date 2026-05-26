<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('app.label.image'))
                    ->disk('public')
                    ->square()
                    ->imageHeight(75)
                    ->disk('public')
                    ->defaultImageUrl(asset('images/no_image.png')),

                TextColumn::make('name')
                    ->label(__('app.label.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('app.label.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label(__('app.label.department'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('position.name')
                    ->label(__('app.label.position'))
                    ->searchable()
                    ->sortable(),

                ToggleColumn::make('status')
                    ->label(__('app.label.status'))
                    ->sortable()
                    ->onIcon('heroicon-m-check-circle')
                    ->offIcon('heroicon-m-x-circle')
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('app.label.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label(__('app.label.department'))
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('position_id')
                    ->label(__('app.label.position'))
                    ->relationship('position', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('status')
                    ->label(__('app.label.status'))
                    ->trueLabel(__('app.label.active'))
                    ->falseLabel(__('app.label.inactive'))
                    ->placeholder(__('app.label.all')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
