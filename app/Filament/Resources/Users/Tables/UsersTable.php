<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\StatusToggleColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label(__('app.label.profile_image'))
                    ->disk('local')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('name')
                    ->label(__('app.label.name'))
                    ->searchable()
                    ->wrap()
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

                StatusToggleColumn::make()
                    ->sortable(),

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

                SelectFilter::make('roles')
                    ->label(__('app.label.role'))
                    ->relationship('roles', 'name')
                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id'))
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
