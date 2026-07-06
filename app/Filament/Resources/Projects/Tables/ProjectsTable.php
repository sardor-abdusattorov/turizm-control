<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Exports\ProjectsRegistryExport;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Filament\Support\CreatedAtColumn;
use App\Filament\Support\ExportPermission;
use App\Filament\Support\StatusToggleColumn;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['order', 'creator'])
                ->withCount('participants')
                ->withSum('participants', 'amount')
                ->withSum('participants', 'paid_amount'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.label.project_name'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('starts_on')
                    ->label(__('app.label.starts_on'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('ends_on')
                    ->label(__('app.label.ends_on'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('area_sqm')
                    ->label(__('app.label.area_sqm'))
                    ->formatStateUsing(fn (?string $state): string => number_format((float) $state, 2, ',', ' ').' м²')
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('participants_count')
                    ->label(__('app.label.participants'))
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('participants_sum_amount')
                    ->label(__('app.label.fees_total'))
                    ->formatStateUsing(fn (?string $state): string => number_format((float) $state, 0, ',', ' '))
                    ->placeholder('0')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('participants_sum_paid_amount')
                    ->label(__('app.label.paid'))
                    ->formatStateUsing(fn (?string $state): string => number_format((float) $state, 0, ',', ' '))
                    ->placeholder('0')
                    ->color('success')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('order.number')
                    ->label(__('app.label.order_single'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label(__('app.label.created_by'))
                    ->placeholder('—')
                    ->toggleable(),

                StatusToggleColumn::make()
                    ->sortable(),

                CreatedAtColumn::make()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(Project::getStatuses()),
            ])
            ->recordUrl(fn (Project $record) => BaseProjectResource::resourceFor($record)::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('exportXlsx')
                    ->label(__('app.action.export_xlsx'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => ExportPermission::allows('export_project'))
                    ->action(fn ($livewire) => Excel::download(
                        new ProjectsRegistryExport(
                            $livewire->getFilteredTableQuery(),
                            $livewire::getResource()::projectType(),
                        ),
                        $livewire::getResource()::projectType()->value.'-projects-'.now()->format('Y-m-d').'.xlsx',
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),

                    EditAction::make()->color('gray'),

                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_project') ?? false),
                ]),
            ]);
    }
}
