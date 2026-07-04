<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\ImageUpload;
use App\Models\ProjectParticipant;
use App\Models\ProjectPayment;
use App\Rules\ProjectPaymentWithinPledge;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.view-project';

    public function getHeading(): string
    {
        return (string) $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordPayment')
                ->label(__('app.action.record_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn (): bool => (auth()->user()?->can('record_project_payment') ?? false)
                    && $this->record->participants()->exists())
                ->schema([
                    Select::make('project_participant_id')
                        ->label(__('app.label.participant_name'))
                        ->options(fn (): array => $this->participantOptions())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                            'amount',
                            $state ? ProjectParticipant::find($state)?->remainingAmount() : null,
                        )),

                    TextInput::make('amount')
                        ->label(__('app.label.payment_amount'))
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->required()
                        ->prefix(fn (Get $get): ?string => ProjectParticipant::find($get('project_participant_id'))?->currency?->short_name)
                        ->helperText(fn (Get $get): ?string => ($p = ProjectParticipant::find($get('project_participant_id')))
                            ? __('app.label.remaining').': '.number_format($p->remainingAmount(), 2, ',', ' ').' '.($p->currency?->short_name ?? '')
                            : null)
                        ->rule(fn (Get $get) => new ProjectPaymentWithinPledge(ProjectParticipant::find($get('project_participant_id')))),

                    DatePicker::make('paid_at')
                        ->label(__('app.label.paid_at'))
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->maxDate(now())
                        ->default(now())
                        ->required(),

                    ImageUpload::make(ProjectPayment::SCREENSHOT_DIR, 'screenshot')
                        ->label(__('app.label.screenshot'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    ProjectPayment::create([
                        'project_participant_id' => $data['project_participant_id'],
                        'amount' => $data['amount'],
                        'paid_at' => $data['paid_at'],
                        'screenshot' => $data['screenshot'],
                    ]);

                    Notification::make()
                        ->title(__('app.message.payment_recorded'))
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            EditAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function participantOptions(): array
    {
        return $this->record->participants()
            ->with('currency')
            ->get()
            ->mapWithKeys(fn (ProjectParticipant $p): array => [
                $p->id => $p->name.' — '.__('app.label.remaining').' '
                    .number_format($p->remainingAmount(), 0, ',', ' ').' '.($p->currency?->short_name ?? ''),
            ])
            ->all();
    }
}
