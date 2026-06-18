<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->hiddenLabel()
                    ->visible(fn (?Contract $record): bool => $record !== null
                        && in_array($record->status, [Contract::STATUS_IN_REVIEW, Contract::STATUS_APPROVED, Contract::STATUS_REJECTED], true))
                    ->schema([
                        TextEntry::make('edit_warning')
                            ->hiddenLabel()
                            ->state(fn (): string => '⚠️ '.__('app.message.edit_invalidates_approvals'))
                            ->color('warning')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.attached_document'))
                    ->visible(fn (?Contract $record): bool => $record !== null && $record->documentExists())
                    ->schema([
                        TextEntry::make('document_file')
                            ->hiddenLabel()
                            ->icon('heroicon-o-document-text')
                            ->iconColor('primary')
                            ->weight('bold')
                            ->state(fn (Contract $record): string => $record->number.'.docx')
                            ->columnSpanFull(),

                        TextEntry::make('document_size')
                            ->label(__('app.label.size'))
                            ->state(function (Contract $record): string {
                                $bytes = Storage::disk('local')->size($record->documentPath());

                                return number_format($bytes / 1024, 1).' KB';
                            }),

                        TextEntry::make('created_at')
                            ->label(__('app.label.created_at'))
                            ->dateTime('d.m.Y H:i'),

                        Actions::make([
                            Action::make('openInEditor')
                                ->label(__('app.action.open_editor'))
                                ->icon('heroicon-o-pencil-square')
                                ->color('primary')
                                ->url(fn (Contract $record) => route('contracts.editor', [
                                    'contract' => $record,
                                    'mode' => 'edit',
                                ])),
                        ])->columnSpanFull(),
                    ]),

                Section::make(__('app.label.basic_information'))
                    ->collapsible()
                    ->schema([
                        TextInput::make('number')
                            ->label(__('app.label.contract_number'))
                            ->required()
                            ->maxLength(50)
                            ->unique('contracts', 'number', ignoreRecord: true)
                            ->columnSpanFull(),

                        Select::make('order_type_id')
                            ->label(__('app.label.order_type_single'))
                            ->options(OrderType::getActive())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('contract_template_id', null))
                            ->helperText(__('app.helper.contract_order_type'))
                            ->columnSpanFull(),

                        Select::make('contract_template_id')
                            ->label(__('app.label.contract_template_single'))
                            ->options(fn (Get $get): array => self::templateOptions($get('order_type_id')))
                            ->disabled(fn (Get $get): bool => blank($get('order_type_id')))
                            ->placeholder(fn (Get $get): string => blank($get('order_type_id'))
                                ? __('app.label.select_order_type_first')
                                : __('app.label.select_option'))
                            ->required()
                            ->searchable()
                            ->helperText(__('app.helper.contract_template_choice'))
                            ->columnSpanFull(),

                        Select::make('contact_id')
                            ->label(__('app.label.contact_single'))
                            ->options(Contact::getActive())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema))
                            ->createOptionUsing(fn (array $data) => Contact::create($data)->getKey())
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label(__('app.label.contract_title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('amount')
                            ->label(__('app.label.amount'))
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->columnSpanFull(),

                        Select::make('currency_id')
                            ->label(__('app.label.currency_single'))
                            ->options(Currency::query()->where('status', true)->pluck('short_name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.label.approval_chain'))
                    ->description(__('app.helper.approval_chain_form'))
                    ->collapsible()
                    ->hiddenOn('edit')
                    ->schema([
                        Select::make('approver_chain')
                            ->hiddenLabel()
                            ->multiple()
                            ->options(fn (): array => User::approverOptionsGroupedByDepartment(Auth::id()))
                            ->default(fn (): array => self::defaultApproverIds())
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText(__('app.helper.approval_chain_pick')),

                        TextEntry::make('approver_chain_preview')
                            ->hiddenLabel()
                            ->state(fn (Get $get): string => self::approvalChainPreview($get('approver_chain')))
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Active templates for the chosen order type, plus untyped "general"
     * templates that apply to any type. Empty until a type is selected so the
     * dropdown stays gated behind the order type.
     *
     * @return array<int, string>
     */
    protected static function templateOptions(?int $orderTypeId): array
    {
        if (blank($orderTypeId)) {
            return [];
        }

        return ContractTemplate::query()
            ->active()
            ->where(fn ($query) => $query
                ->where('order_type_id', $orderTypeId)
                ->orWhereNull('order_type_id'))
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (ContractTemplate $t): array => [
                $t->id => $t->name.' ('.strtoupper($t->language).')',
            ])
            ->toArray();
    }

    /**
     * Pre-filled approver IDs (in order) from the manager's profile default
     * recipients, falling back to the global settings flow when none are set.
     *
     * @return array<int, int>
     */
    protected static function defaultApproverIds(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $ids = $user->defaultRecipients()
            ->where('users.status', User::STATUS_ACTIVE)
            ->pluck('users.id')
            ->all();

        if (empty($ids)) {
            $ids = collect(Department::approvalFlow())
                ->map(fn (string $code) => Department::findByCode($code)?->approverUser()?->id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return array_map('intval', $ids);
    }

    /**
     * Numbered preview of the selected approval chain — avatar, name and
     * department per step, in the order they will approve. Re-rendered live as
     * the picker above changes (the Select is ->live()).
     */
    protected static function approvalChainPreview(mixed $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));

        if ($ids === []) {
            return '<p style="margin:0;color:#9ca3af;font-size:.875rem;">'.e(__('app.helper.approval_chain_empty')).'</p>';
        }

        $users = User::with(['department', 'position'])->whereIn('id', $ids)->get()->keyBy('id');

        $rows = '';
        $step = 1;

        foreach ($ids as $id) {
            $user = $users->get($id);

            if (! $user) {
                continue;
            }

            $avatar = $user->getFilamentAvatarUrl()
                ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&color=7F9CF5&background=EBF4FF';

            $meta = trim(($user->department?->name ?? '').($user->position?->name ? ' · '.$user->position->name : ''), ' ·');

            $rows .= '<div style="display:flex;align-items:center;gap:.65rem;padding:.55rem .75rem;border:1px solid #e5e7eb;border-radius:.65rem;background:#fff;">'
                .'<span style="flex-shrink:0;width:1.4rem;height:1.4rem;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#eef2ff;color:#4338ca;font-size:.75rem;font-weight:700;">'.$step.'</span>'
                .'<img src="'.e($avatar).'" alt="" style="width:1.9rem;height:1.9rem;border-radius:50%;object-fit:cover;flex-shrink:0;">'
                .'<span style="min-width:0;"><span style="display:block;font-weight:600;color:#111827;">'.e($user->name).'</span>'
                .'<span style="display:block;font-size:.75rem;color:#6b7280;">'.e($meta).'</span></span>'
                .'</div>';

            $step++;
        }

        return '<div style="display:flex;flex-direction:column;gap:.4rem;margin-top:.25rem;">'.$rows.'</div>';
    }
}
