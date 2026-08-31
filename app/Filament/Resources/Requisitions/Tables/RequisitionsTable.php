<?php

namespace App\Filament\Resources\Requisitions\Tables;

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Filament\Support\ApprovalActions;
use App\Filament\Widgets\ApprovalsTimelineWidget;
use App\Models\Requisition;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'author',
                'project',
                'approvals.user',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.label.requisition_number'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                ViewColumn::make('status')
                    ->label(__('app.label.status'))
                    ->view('filament.tables.columns.document-status')
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('app.label.requisition_title'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(3)
                    ->tooltip(fn (Requisition $record): string => (string) $record->title)
                    ->description(fn (Requisition $record): ?string => $record->project?->name)
                    ->extraHeaderAttributes(['class' => 'fi-col-title'])
                    ->extraCellAttributes(['class' => 'fi-col-title']),

                static::approversColumn(),

                ViewColumn::make('sla')
                    ->label(__('app.label.due'))
                    ->view('filament.components.sla-countdown')
                    ->state(fn (Requisition $record) => $record->currentApproval()?->due_at)
                    ->extraHeaderAttributes(['class' => 'fi-col-sla'])
                    ->extraCellAttributes(['class' => 'fi-col-sla'])
                    ->disabledClick(),

                ViewColumn::make('author')
                    ->label(__('app.label.author'))
                    ->state(fn (Requisition $record): array => [
                        'avatar' => $record->author?->avatarUrl(),
                        'name' => $record->author?->name ?? __('app.label.not_set'),
                        'sub' => null,
                    ])
                    ->view('filament.tables.columns.person')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('app.label.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(static::filters())
            ->filtersFormColumns(2)
            ->recordUrl(fn (Requisition $record): string => RequisitionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),

                    EditAction::make()
                        ->color('gray')
                        ->visible(fn (Requisition $record): bool => $record->canBeEditedBy()),

                    ApprovalActions::submit(),
                    ApprovalActions::approve(),
                    ApprovalActions::reject(),
                    ApprovalActions::recall(),

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

    /**
     * The chain at a glance — progress, the people on it and their verdicts —
     * opening the full audit trail on click.
     */
    protected static function approversColumn(): ViewColumn
    {
        return ViewColumn::make('approvers')
            ->label(__('app.approval.column.chain'))
            ->view('filament.tables.columns.approvers')
            ->extraHeaderAttributes(['class' => 'fi-col-approvers'])
            ->extraCellAttributes(['class' => 'fi-col-approvers'])
            ->disabledClick(fn (Requisition $record): bool => $record->activeApprovals()->isEmpty())
            ->action(
                Action::make('approvalsTimeline')
                    ->modalHeading(fn (Requisition $record): string => $record->number.' · '.$record->title)
                    ->modalIcon('heroicon-o-user-group')
                    ->modalWidth(Width::SixExtraLarge)
                    ->modalContent(fn (Requisition $record) => view('filament.partials.embedded-table', [
                        'widget' => ApprovalsTimelineWidget::class,
                        'params' => ['requisitionId' => $record->getKey()],
                        'key' => 'approvals-timeline-'.$record->getKey(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
            );
    }

    /**
     * @return array<int, mixed>
     */
    protected static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('app.label.status'))
                ->options(RequisitionStatus::options())
                ->multiple(),

            SelectFilter::make('author_id')
                ->label(__('app.label.author'))
                ->relationship('author', 'name')
                ->searchable()
                ->preload(),

            SelectFilter::make('project_id')
                ->label(__('app.label.project_single'))
                ->relationship('project', 'name')
                ->searchable()
                ->preload(),

            SelectFilter::make('approver')
                ->label(__('app.approval.column.approver'))
                ->options(fn (): array => User::query()
                    ->where('status', User::STATUS_ACTIVE)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $q, $userId) => $q->whereHas(
                        'approvals',
                        fn (Builder $approvals) => $approvals
                            ->where('user_id', $userId)
                            ->where('status', '!=', ApprovalStatus::Invalidated),
                    ),
                )),

            SelectFilter::make('my_approval')
                ->label(__('app.approval.filter.my_approval'))
                ->options([
                    'approved_by_me' => __('app.approval.filter.approved_by_me'),
                    'not_approved_by_me' => __('app.approval.filter.not_approved_by_me'),
                ])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'approved_by_me' => $query->whereHas('approvals', fn (Builder $approvals) => $approvals
                        ->where('user_id', auth()->id())
                        ->where('status', ApprovalStatus::Approved)),
                    'not_approved_by_me' => $query->whereHas('approvals', fn (Builder $approvals) => $approvals
                        ->where('user_id', auth()->id())
                        ->whereIn('status', [ApprovalStatus::Queued, ApprovalStatus::Pending, ApprovalStatus::Rejected])),
                    default => $query,
                }),

            Filter::make('overdue')
                ->label(__('app.label.overdue'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->whereHas('approvals', fn (Builder $approvals) => $approvals
                    ->where('status', ApprovalStatus::Pending)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now()))),

            Filter::make('created_at')
                ->schema([
                    DatePicker::make('from')->label(__('app.label.from')),
                    DatePicker::make('until')->label(__('app.label.until')),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
        ];
    }
}
