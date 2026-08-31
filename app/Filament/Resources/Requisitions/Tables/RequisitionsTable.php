<?php

namespace App\Filament\Resources\Requisitions\Tables;

use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Requisitions\RequisitionWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['author', 'reviewer', 'project']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.requisition_number'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('app.label.requisition_title'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(3)
                    ->extraCellAttributes(['style' => 'min-width: 20rem'])
                    ->description(fn (Requisition $record): ?string => $record->project?->name),

                TextColumn::make('status')
                    ->label(__('app.label.status'))
                    ->badge()
                    ->color(fn (RequisitionStatus $state): string => $state->color())
                    ->icon(fn (RequisitionStatus $state): string => $state->icon())
                    ->formatStateUsing(fn (RequisitionStatus $state): string => $state->label())
                    ->sortable(),

                ViewColumn::make('author')
                    ->label(__('app.label.author'))
                    ->state(fn (Requisition $record): array => [
                        'avatar' => $record->author?->avatarUrl(),
                        'name' => $record->author?->name ?? __('app.label.not_set'),
                        'sub' => null,
                    ])
                    ->view('filament.tables.columns.person')
                    ->toggleable(),

                ViewColumn::make('reviewer')
                    ->label(__('app.label.requisition_reviewer'))
                    ->state(fn (Requisition $record): array => [
                        'avatar' => $record->reviewer?->avatarUrl(),
                        'name' => $record->reviewer?->name ?? __('app.label.not_set'),
                        'sub' => null,
                    ])
                    ->view('filament.tables.columns.person'),

                ViewColumn::make('due_at')
                    ->label(__('app.label.due'))
                    ->view('filament.components.sla-countdown')
                    ->state(fn (Requisition $record) => $record->status === RequisitionStatus::InReview
                        ? $record->due_at
                        : null)
                    ->disabledClick(),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.label.status'))
                    ->options(RequisitionStatus::options()),

                SelectFilter::make('reviewer_id')
                    ->label(__('app.label.requisition_reviewer'))
                    ->options(fn (): array => User::query()
                        ->where('status', User::STATUS_ACTIVE)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),

                SelectFilter::make('author_id')
                    ->label(__('app.label.author'))
                    ->options(fn (): array => User::query()
                        ->where('status', User::STATUS_ACTIVE)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),

                Filter::make('overdue')
                    ->label(__('app.label.overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', RequisitionStatus::InReview)
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('app.label.from')),
                        DatePicker::make('until')->label(__('app.label.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->filtersFormColumns(2)
            ->recordUrl(fn (Requisition $record): string => RequisitionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),

                    EditAction::make()
                        ->color('gray')
                        ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),

                    Action::make('submitForReview')
                        ->label(__('app.action.send_for_review'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription(__('app.message.send_for_review_confirm'))
                        ->visible(fn (Requisition $record): bool => $record->canBeSubmittedBy())
                        ->action(function (Requisition $record, RequisitionWorkflow $workflow): void {
                            $workflow->submit($record)
                                ? Notification::make()->title(__('app.message.sent_for_review'))->success()->send()
                                : Notification::make()->title(__('app.message.action_not_allowed'))->danger()->send();
                        }),

                    DeleteAction::make()
                        ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),
                ])
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete_requisition') ?? false),
                ]),
            ])
            ->emptyStateHeading(__('app.message.no_requisitions'))
            ->emptyStateIcon('heroicon-o-inbox-arrow-down');
    }
}
