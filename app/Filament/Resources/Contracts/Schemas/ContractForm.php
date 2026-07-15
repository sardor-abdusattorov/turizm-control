<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\Contracts\ApprovalChain;
use App\Services\Contracts\ContractWorkflow;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

                                // Legacy import switch: a signed paper contract is
                                // filed as approved straight away — no chain, just
                                // the signing date and the scans below.
                                Toggle::make('already_signed')
                                    ->label(__('app.label.already_signed'))
                                    ->helperText(__('app.helper.already_signed'))
                                    ->visible(fn (?Contract $record): bool => $record === null)
                                    ->live()
                                    ->columnSpanFull(),

                                DatePicker::make('signed_at')
                                    ->label(__('app.label.signed_date'))
                                    ->visible(fn (Get $get, ?Contract $record): bool => $record === null && (bool) $get('already_signed'))
                                    ->default(now())
                                    ->maxDate(now())
                                    ->required(fn (Get $get, ?Contract $record): bool => $record === null && (bool) $get('already_signed'))
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
                                    ->afterStateUpdated(fn (Set $set) => $set('contract_template_id', null))
                                    ->columnSpanFull(),

                                Select::make('contract_template_id')
                                    ->label(__('app.label.contract_template_single'))
                                    ->options(fn (Get $get): array => self::templateOptions($get('contract_type_id')))
                                    ->disabled(fn (Get $get): bool => blank($get('contract_type_id')))
                                    ->placeholder(fn (Get $get): string => blank($get('contract_type_id'))
                                        ? __('app.label.select_contract_type_first')
                                        : __('app.label.select_option'))
                                    ->helperText(__('app.helper.template_optional'))
                                    ->searchable()
                                    ->columnSpanFull(),

                                Select::make('order_id')
                                    ->label(__('app.label.order_basis'))
                                    ->options(fn (): array => self::orderOptions())
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
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

                                Select::make('project_id')
                                    ->label(__('app.label.project_single'))
                                    ->options(fn (): array => self::projectOptionsGrouped())
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->columnSpanFull(),

                                TextInput::make('title')
                                    ->label(__('app.label.contract_title'))
                                    ->required()
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

                                // Scans can be dropped right at creation — no need
                                // to save first and hunt for the upload button on
                                // the view page. Stored as dossier attachments in
                                // CreateContract::afterCreate.
                                FileUpload::make('attachment_files')
                                    ->label(__('app.label.attachments'))
                                    ->helperText(__('app.helper.attachment_scans'))
                                    ->visible(fn (?Contract $record): bool => $record === null)
                                    ->multiple()
                                    ->disk('local')
                                    ->directory('uploads/files/contract-attachments')
                                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                    ->maxSize(25600)
                                    ->storeFileNamesIn('attachment_names')
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

    /**
     * Active templates for the chosen contract type, plus untyped "general"
     * templates that apply to any kind. Empty until a kind is selected so the
     * dropdown stays gated behind the contract type.
     *
     * @return array<int, string>
     */
    protected static function templateOptions(?int $contractTypeId): array
    {
        if (blank($contractTypeId)) {
            return [];
        }

        return ContractTemplate::query()
            ->forContractType($contractTypeId)
            ->get()
            ->mapWithKeys(fn (ContractTemplate $t): array => [
                $t->id => $t->name,
            ])
            ->toArray();
    }

    /**
     * Active projects grouped into optgroups by «тип · год» (newest first, as
     * the projects come ordered by start date) so the picker reads like the
     * sidebar instead of one flat 35-row list.
     *
     * @return array<string, array<int, string>>
     */
    protected static function projectOptionsGrouped(): array
    {
        return Project::query()
            ->active()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Project $project): string => trim(
                $project->type->label().($project->starts_on ? ' · '.$project->starts_on->year : ''),
            ))
            ->map(fn ($group) => $group->mapWithKeys(
                fn (Project $project): array => [$project->id => $project->name],
            )->toArray())
            ->toArray();
    }

    /**
     * Active buyruqs the contract can name as its basis, grouped into
     * optgroups by issue year (newest first), labelled by number (falling
     * back to the title for unnumbered drafts).
     *
     * @return array<string, array<int, string>>
     */
    protected static function orderOptions(): array
    {
        return Order::query()
            ->where('status', true)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Order $order): string => $order->issued_at?->format('Y') ?? '—')
            ->map(fn ($group) => $group->mapWithKeys(fn (Order $order): array => [
                $order->id => trim(($order->number ? $order->number.' · ' : '').$order->title),
            ])->toArray())
            ->toArray();
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

    /**
     * Pre-filled approver IDs (in order) from the manager's profile default
     * recipients, falling back to the global settings flow when none are set.
     *
     * @return array<int, int>
     */
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
