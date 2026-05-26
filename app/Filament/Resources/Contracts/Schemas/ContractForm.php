<?php

namespace App\Filament\Resources\Contracts\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->schema(self::basicSchema()),
            ]);
    }

    /**
     * Fields that describe the contract itself (type, parties, terms).
     * Used both by the create wizard's first step and by the edit form.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function basicSchema(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('order_type_id')
                        ->label(__('app.label.order_type_single'))
                        ->options(OrderType::getActive())
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('contact_id')
                        ->label(__('app.label.contact_single'))
                        ->options(Contact::getActive())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema))
                        ->createOptionUsing(fn (array $data) => Contact::create($data)->getKey()),
                ]),

            TranslatableTabs::make()
                ->schema([
                    TextInput::make('title')
                        ->label(__('app.label.title'))
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label(__('app.label.description'))
                        ->rows(3),
                ]),

            Grid::make(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('amount')
                        ->label(__('app.label.amount'))
                        ->numeric()
                        ->inputMode('decimal')
                        ->step(0.01)
                        ->default(0)
                        ->required(),

                    Select::make('currency_id')
                        ->label(__('app.label.currency_single'))
                        ->options(Currency::getActive())
                        ->required()
                        ->searchable()
                        ->preload(),

                    DatePicker::make('deadline_at')
                        ->label(__('app.label.deadline'))
                        ->native(false)
                        ->displayFormat('d.m.Y'),
                ]),
        ];
    }

    /**
     * Approval chain — used only by the create wizard's second step.
     * Pre-fills with the manager's defaultRecipients ordered by the
     * admin-configured approval flow.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function approversSchema(): array
    {
        return [
            Repeater::make('approvers')
                ->label(__('app.label.approval_chain'))
                ->helperText(__('app.helper.approval_chain'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('app.label.approver'))
                        ->options(User::approverOptionsForChain())
                        ->required()
                        ->searchable(),
                ])
                ->itemLabel(function (array $state): ?string {
                    if (empty($state['user_id'])) {
                        return null;
                    }

                    return User::find($state['user_id'])?->name;
                })
                ->reorderable()
                ->reorderableWithDragAndDrop()
                ->addActionLabel(__('app.action.add_approver'))
                ->minItems(1)
                ->default(fn () => Contract::suggestApproverChain())
                ->collapsible(),
        ];
    }
}
