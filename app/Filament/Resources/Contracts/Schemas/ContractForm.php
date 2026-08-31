<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Filament\Resources\Sponsors\Schemas\SponsorForm;
use App\Filament\Support\ContractDossierUpload;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Project;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractWorkflow;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->visible(fn (?Contract $record, Get $get): bool => $record !== null
                        && in_array($record->status, [Contract::STATUS_IN_REVIEW, Contract::STATUS_APPROVED, Contract::STATUS_REJECTED], true)
                        && ! $get('already_signed'))
                    ->schema([
                        TextEntry::make('edit_warning')
                            ->hiddenLabel()
                            ->state(fn (): string => '⚠️ '.__('app.message.edit_invalidates_approvals'))
                            ->color('warning')
                            ->columnSpanFull(),
                    ]),

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

                                Toggle::make('already_signed')
                                    ->label(__('app.label.already_signed'))
                                    ->helperText(__('app.helper.already_signed'))
                                    ->live()
                                    ->columnSpanFull(),

                                DatePicker::make('signed_at')
                                    ->label(__('app.label.signed_date'))
                                    ->visible(fn (Get $get): bool => (bool) $get('already_signed'))
                                    ->default(now())
                                    ->maxDate(now())
                                    ->required(fn (Get $get): bool => (bool) $get('already_signed'))
                                    ->columnSpanFull(),

                                TextInput::make('number')
                                    ->label(__('app.label.contract_number'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique('contracts', 'number', ignoreRecord: true)
                                    ->columnSpanFull(),

                                Select::make('contract_type_id')
                                    ->label(__('app.label.contract_type_single'))
                                    ->options(ContractType::getActive())
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (self::typeUsesSponsor($state)) {
                                            $set('contact_id', null);
                                        } else {
                                            $set('sponsor_id', null);
                                        }
                                    })
                                    ->columnSpanFull(),

                                Select::make('contact_id')
                                    ->label(__('app.label.contact_single'))
                                    ->visible(fn (Get $get): bool => ! self::typeUsesSponsor($get('contract_type_id')))
                                    ->required(fn (Get $get): bool => ! self::typeUsesSponsor($get('contract_type_id')))
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => Contact::searchOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => Contact::find($value)?->optionLabel())
                                    ->options(fn (): array => Contact::searchOptions())
                                    ->allowHtml()
                                    ->loadingMessage(__('app.label.searching'))
                                    ->searchPrompt(__('app.helper.counterparty_search'))
                                    ->noSearchResultsMessage(__('app.message.counterparty_not_found'))
                                    ->createOptionForm(fn (Schema $schema) => ContactForm::configure($schema, inline: true))
                                    ->createOptionUsing(fn (array $data) => ContactForm::createWithBankAccounts($data)->getKey())
                                    ->columnSpanFull(),

                                Select::make('sponsor_id')
                                    ->label(__('app.label.sponsor_single'))
                                    ->visible(fn (Get $get): bool => self::typeUsesSponsor($get('contract_type_id')))
                                    ->required(fn (Get $get): bool => self::typeUsesSponsor($get('contract_type_id')))
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => Sponsor::searchOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => Sponsor::find($value)?->optionLabel())
                                    ->options(fn (): array => Sponsor::searchOptions())
                                    ->allowHtml()
                                    ->loadingMessage(__('app.label.searching'))
                                    ->searchPrompt(__('app.helper.counterparty_search'))
                                    ->noSearchResultsMessage(__('app.message.counterparty_not_found'))
                                    ->createOptionForm(fn (Schema $schema) => SponsorForm::configure($schema))
                                    ->createOptionUsing(fn (array $data) => Sponsor::create($data)->getKey())
                                    ->columnSpanFull(),

                                Grid::make(['default' => 1, 'md' => 3])
                                    ->columnSpanFull()
                                    ->schema([

                                        Select::make('project_year')
                                            ->label(__('app.label.project_year'))
                                            ->options(fn (): array => self::projectYearOptions())
                                            ->placeholder(__('app.label.all_years'))
                                            ->live()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (Set $set, ?Contract $record): void {
                                                if ($record?->project?->starts_on) {
                                                    $set('project_year', (string) $record->project->starts_on->year);
                                                }
                                            })
                                            ->afterStateUpdated(fn (Set $set) => $set('project_id', null))
                                            ->columnSpan(1),

                                        Select::make('project_id')
                                            ->label(__('app.label.project_single'))
                                            ->options(fn (Get $get): array => self::projectOptionsGrouped($get('project_year')))
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(['default' => 1, 'md' => 2]),
                                    ]),

                                Textarea::make('title')
                                    ->label(__('app.label.contract_title'))
                                    ->required()
                                    ->rows(2)
                                    ->autosize()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Select::make('currency_id')
                                    ->label(__('app.label.currency_single'))
                                    ->options(Currency::query()->where('status', true)->pluck('short_name', 'id'))
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),

                                TextInput::make('amount')
                                    ->label(__('app.label.amount'))
                                    ->prefix(fn (Get $get): ?string => Currency::find($get('currency_id'))?->short_name)
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => blank($get('currency_id')))
                                    ->dehydrated()
                                    ->placeholder(fn (Get $get): ?string => blank($get('currency_id'))
                                        ? __('app.label.select_currency_first')
                                        : null)
                                    ->columnSpanFull(),

                                ContractDossierUpload::make()

                                    ->preventFilePathTampering(allowFilePathUsing: fn (string $file, ?Contract $record): bool => (bool) $record?->attachments()
                                        ->where('file_path', $file)
                                        ->exists())
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('app.label.approval_chain'))
                            ->icon('heroicon-o-users')
                            ->visible(fn (Get $get): bool => ContractWorkflow::approvalEnabled() && ! $get('already_signed'))
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
                                    ->required(fn (Get $get): bool => ContractWorkflow::approvalEnabled() && ! $get('already_signed'))
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

    protected static function typeUsesSponsor(mixed $contractTypeId): bool
    {
        if (blank($contractTypeId)) {
            return false;
        }

        return in_array((int) $contractTypeId, ContractType::sponsorFacingIds(), true);
    }

    /** @return array<string, string> */
    protected static function projectYearOptions(): array
    {
        return Project::query()
            ->active()
            ->whereNotNull('starts_on')
            ->pluck('starts_on')
            ->map(fn ($date) => (string) $date->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (string $year): array => [$year => $year])
            ->all();
    }

    /** @return array<string, array<int, string>> */
    protected static function projectOptionsGrouped(?string $year = null): array
    {
        return Project::groupedOptions($year);
    }

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

    /** @return array<int, int> */
    public static function defaultApproverIds(): array
    {
        $user = Auth::user();

        return $user
            ? app(ApprovalChain::class)->defaultUserIdsFor($user)
            : [];
    }

    protected static function approvalChainPreview(mixed $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));

        if ($ids === []) {
            return '<p style="margin:0;color:#9ca3af;font-size:.875rem;">'.e(__('app.helper.approval_chain_empty')).'</p>';
        }

        $users = User::with(['department', 'position'])->whereIn('id', $ids)->get()->keyBy('id');

        $rowStyle = 'display:flex;align-items:center;gap:.7rem;padding:.6rem .8rem;'
            .'border:1px solid rgba(127,127,127,.22);border-radius:.65rem;'
            .'background:rgba(127,127,127,.05);';
        $numStyle = 'flex-shrink:0;width:1.55rem;height:1.55rem;display:flex;align-items:center;'
            .'justify-content:center;border-radius:50%;background:rgba(37,99,235,.18);'
            .'color:#2563eb;font-size:.78rem;font-weight:700;';
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
                ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&color=60A5FA&background=DBEAFE';

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
