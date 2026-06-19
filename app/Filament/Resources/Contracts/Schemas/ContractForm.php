<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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

                // Post-invalidation notice — shown right after the edit hook
                // demoted the contract back to DRAFT and cancelled the prior chain.
                Section::make()
                    ->hiddenLabel()
                    ->visible(fn (?Contract $record): bool => $record !== null
                        && $record->status === Contract::STATUS_DRAFT
                        && $record->approvers()->where('status', ContractApprover::STATUS_INVALIDATED)->exists())
                    ->schema([
                        TextEntry::make('invalidated_notice')
                            ->hiddenLabel()
                            ->state(fn (): string => '↩️ '.__('app.message.approvals_cancelled_after_edit'))
                            ->color('warning')
                            ->columnSpanFull(),
                    ]),

                Tabs::make('contract_tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('app.label.basic_information'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                View::make('filament.resources.contracts.partials.file-card')
                                    ->visible(fn (?Contract $record): bool => $record !== null && $record->documentExists())
                                    ->columnSpanFull(),

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
                                    ->prefix(fn (Get $get): ?string => Currency::find($get('currency_id'))?->short_name)
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('currency_id')
                                    ->label(__('app.label.currency_single'))
                                    ->options(Currency::query()->where('status', true)->pluck('short_name', 'id'))
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('app.label.approval_chain'))
                            ->icon('heroicon-o-users')
                            // Editable on create and on every editable status. On a
                            // submitted contract changing the chain cancels the running
                            // approvals and rebuilds from the new selection (see the
                            // warning banner above) — archived contracts are read-only.
                            ->visible(fn (?Contract $record): bool => $record === null || $record->status !== Contract::STATUS_ARCHIVED)
                            ->badge(fn (Get $get): ?int => ($n = count(array_filter((array) $get('approver_chain')))) ? $n : null)
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
                                    ->required()
                                    ->rules([
                                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                            self::validateRequiredApproverDepartments($value, $fail);
                                        },
                                    ])
                                    ->columnSpanFull(),

                                TextEntry::make('approver_chain_preview')
                                    ->hiddenLabel()
                                    ->state(fn (Get $get): string => self::approvalChainPreview($get('approver_chain')))
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
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
     * Validation rule for the approval-chain picker: the selected approvers
     * must cover every department in Department::REQUIRED_APPROVER_CODES (at
     * least one Legal and one Accounting approver). The empty case is handled
     * by ->required() upstream, so an empty value passes through silently here.
     */
    public static function validateRequiredApproverDepartments(mixed $value, Closure $fail): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) $value)));

        if ($ids === []) {
            return;
        }

        $presentCodes = User::query()
            ->whereIn('id', $ids)
            ->with('department')
            ->get()
            ->map(fn (User $user): ?string => $user->department?->code)
            ->filter()
            ->unique();

        $missing = collect(Department::REQUIRED_APPROVER_CODES)
            ->reject(fn (string $code): bool => $presentCodes->contains($code));

        if ($missing->isNotEmpty()) {
            $fail(__('app.validation.approver_chain_required_departments'));
        }
    }

    /**
     * Pre-filled approver IDs (in order) from the manager's profile default
     * recipients, falling back to the global settings flow when none are set.
     *
     * @return array<int, int>
     */
    public static function defaultApproverIds(): array
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

        // Inline styles only — Filament strips <style> from TextEntry HTML state,
        // so a <style> block won't apply here. Use semi-transparent neutrals that
        // read against both light and dark surfaces.
        $rowStyle = 'display:flex;align-items:center;gap:.7rem;padding:.6rem .8rem;'
            .'border:1px solid rgba(127,127,127,.22);border-radius:.65rem;'
            .'background:rgba(127,127,127,.05);';
        $numStyle = 'flex-shrink:0;width:1.55rem;height:1.55rem;display:flex;align-items:center;'
            .'justify-content:center;border-radius:50%;background:rgba(99,102,241,.18);'
            .'color:#6366f1;font-size:.78rem;font-weight:700;';
        $avStyle = 'width:2rem;height:2rem;border-radius:50%;object-fit:cover;flex-shrink:0;';
        $idStyle = 'min-width:0;display:flex;flex-direction:column;gap:.12rem;';
        $nmStyle = 'font-size:.92rem;font-weight:600;color:currentColor;';
        $mtStyle = 'font-size:.82rem;color:currentColor;opacity:.65;';

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

            $rows .= '<div style="'.$rowStyle.'">'
                .'<span style="'.$numStyle.'">'.$step.'</span>'
                .'<img src="'.e($avatar).'" alt="" style="'.$avStyle.'">'
                .'<span style="'.$idStyle.'">'
                .'<span style="'.$nmStyle.'">'.e($user->name).'</span>'
                .'<span style="'.$mtStyle.'">'.e($meta).'</span>'
                .'</span>'
                .'</div>';

            $step++;
        }

        return '<div style="display:flex;flex-direction:column;gap:.45rem;margin-top:.25rem;">'.$rows.'</div>';
    }
}
