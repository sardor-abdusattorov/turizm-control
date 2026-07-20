@php
    use App\Enums\ContractApproverStatus;
    use App\Models\Contract;
    use App\Models\ContractApprover;
    use Illuminate\Support\Carbon;

    $statusColor = $record->status->color();
    $statusLabel = $record->status->label();
    $current = $record->currentApprover();
    $directorUserId = $record->directorUser()?->id;
    $hero = $this->heroContext();

    $active = $record->activeApprovers;
    $historical = $record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]);
    $approvedCount = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    // Use the highest `order` in the chain so a "Step 3 / X" tile reads
    // correctly even when an earlier slot was invalidated and removed from
    // the active rows.
    $totalCount = (int) max($active->max('order') ?? 0, $active->count());
    $progress = $totalCount ? round($approvedCount / $totalCount * 100) : 0;

    // People who only appear in cancelled/skipped rows — e.g. an approver who
    // was dropped from the chain. They get a muted row at the foot of the
    // chain so their attempts stay reachable now that the standalone history
    // button is gone. (Normally every historical user is mirrored into the
    // active chain, so this stays empty.)
    $activeUserIds = $active->pluck('user_id')->all();
    $historicalOnly = $historical->whereNotIn('user_id', $activeUserIds)->unique('user_id')->values();

    $pillFor = fn (ContractApproverStatus $status): string => $status->color();

    // Before a contract is submitted the approvers are technically "queued",
    // but the review hasn't started — so show "Not submitted" instead of
    // "In queue" while the contract is still a draft.
    $isDraft = $record->status === Contract::STATUS_DRAFT;
    $approverLabel = fn (ContractApproverStatus $status): string => $isDraft && $status === ContractApproverStatus::Queued
        ? __('app.label.not_submitted')
        : $status->label();

    $ic = fn (string $name, int $size = 18) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $activities = $this->getActivities()
        ->unique(fn ($a) => ($a->description ?? '').'|'.$a->created_at?->format('YmdHi'))
        ->values();

    // Every fact in full — no status row (the pill rides on the tab strip).
    $details = [
        ['heroicon-o-hashtag', __('app.label.contract_number'), $record->number, null],
        ['heroicon-o-building-office-2', __('app.label.contact_single'), $record->contact?->name, $record->contact ? 'contact' : null],
        ['heroicon-o-tag', __('app.label.contract_type_single'), $record->contractType?->title, null],
        ['heroicon-o-presentation-chart-bar', __('app.label.project_single'), $record->project?->name, null],
        ['heroicon-o-document-text', __('app.label.order_basis'), $record->project?->order ? trim(($record->project->order->number ? $record->project->order->number.' · ' : '').$record->project->order->title) : null, null],
        ['heroicon-o-user', __('app.label.responsible'), $record->responsible?->name, null],
        ['heroicon-o-banknotes', __('app.label.amount'), \App\Support\Money::format($record->amount).' '.($record->currency?->short_name ?? ''), null],
        ['heroicon-o-document-duplicate', __('app.label.contract_template_single'), $record->template?->name, null],
        ['heroicon-o-paper-airplane', __('app.label.submitted'), $this->submittedAt()?->format('d.m.Y H:i'), null],
        ['heroicon-o-calendar-days', __('app.label.signing_date'), $record->signed_at?->format('d.m.Y'), null],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i'), null],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i'), null],
    ];

    $contact = $record->contact;
    // The account matching this contract's currency (falls back to a generic one).
    $bankAccount = $contact?->bankAccountFor($record->currency_id);
    $contactType = $contact?->type === \App\Models\Contact::TYPE_INDIVIDUAL
        ? __('app.contact.type.individual')
        : __('app.contact.type.legal');

    // Group fields exactly the way Filament's ContactForm splits them:
    // identity + tax/legal + contacts + bank requisites.
    $contactGroups = $contact ? [
        [__('app.label.basic_information'), array_values(array_filter([
            ['heroicon-o-building-office-2', __('app.label.name'), $contact->name],
            ['heroicon-o-identification', __('app.label.contact_type'), $contactType],
            ['heroicon-o-tag', __('app.label.legal_form'), $contact->legal_form],
            ['heroicon-o-map-pin', __('app.label.address'), $contact->address],
        ], fn ($r) => ! empty($r[2])))],

        [__('app.label.legal_details'), array_values(array_filter([
            ['heroicon-o-finger-print', __('app.label.inn'), $contact->inn],
            ['heroicon-o-finger-print', __('app.label.pinfl'), $contact->pinfl],
            ['heroicon-o-bookmark', __('app.label.oked'), $contact->oked],
            ['heroicon-o-user', __('app.label.director_name'), $contact->director_name],
            ['heroicon-o-user-circle', __('app.label.contact_person'), $contact->contact_person],
        ], fn ($r) => ! empty($r[2])))],

        [__('app.label.contacts'), array_values(array_filter([
            ['heroicon-o-phone', __('app.label.phone'), $contact->phone],
            ['heroicon-o-envelope', __('app.label.email'), $contact->email],
        ], fn ($r) => ! empty($r[2])))],

        [__('app.label.bank_requisites'), array_values(array_filter([
            ['heroicon-o-building-library', __('app.label.bank_name'), $bankAccount?->bank_name],
            ['heroicon-o-map-pin', __('app.label.bank_address'), $bankAccount?->bank_address],
            ['heroicon-o-banknotes', __('app.label.bank_account'), $bankAccount?->account_number],
            ['heroicon-o-hashtag', __('app.label.mfo'), $bankAccount?->mfo],
            ['heroicon-o-globe-alt', __('app.label.swift'), $bankAccount?->swift],
        ], fn ($r) => ! empty($r[2])))],
    ] : [];
    // Drop any group that ended up empty after filtering.
    $contactGroups = array_values(array_filter($contactGroups, fn ($g) => count($g[1]) > 0));
@endphp

<x-filament-panels::page>
    @include('filament.resources.contracts.pages.view-contract.styles')

    <div class="cw"
        x-data="{ contactOpen: false, tab: 'overview', go(t) { this.tab = t; if (this.$root.getBoundingClientRect().top < 0) this.$root.scrollIntoView(); } }"
        x-effect="document.documentElement.classList.toggle('cw-noscroll', contactOpen)"
        @keydown.escape.window="contactOpen = false">
        @php
            $submittedAt = $this->submittedAt();
        @endphp

        {{-- Native Filament tabs — overall status pill rides on the right so
             it stays visible on every tab without a redundant strip up top. --}}
        <div class="rec-tabs-row">
            <x-filament::tabs>
                <x-filament::tabs.item icon="heroicon-o-rectangle-group" alpine-active="tab === 'overview'" x-on:click="go('overview')">
                    {{ __('app.label.overview') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item icon="heroicon-o-paper-clip" alpine-active="tab === 'attachments'" x-on:click="go('attachments')"
                    :badge="$this->attachments()->count() ?: null">
                    {{ __('app.label.attachments') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item icon="heroicon-o-clock" alpine-active="tab === 'history'" x-on:click="go('history')"
                    :badge="$activities->count() ?: null">
                    {{ __('app.label.history') }}
                </x-filament::tabs.item>
            </x-filament::tabs>
            <span class="cw-pill cw-pill--{{ $statusColor }} cw-pill--lg rec-tabs-row__side">{{ $statusLabel }}</span>
        </div>

        @include('filament.resources.contracts.pages.view-contract.overview')

        @include('filament.resources.contracts.pages.view-contract.attachments')

        @include('filament.resources.contracts.pages.view-contract.history')

        @include('filament.resources.contracts.pages.view-contract.contact-modal')
    </div>

    @include('filament.resources.contracts.pages.view-contract.scripts')
</x-filament-panels::page>
