@php
    use App\Filament\Resources\Contracts\ContractResource;
    use App\Filament\Resources\Projects\BaseProjectResource;
    use App\Models\Contact;

    /** @var \App\Models\Contact $record */
    $record = $this->record;

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $fmt = fn ($n) => \App\Support\Money::format($n);
    $loc = fn ($v) => is_array($v) ? ($v[app()->getLocale()] ?? $v['ru'] ?? reset($v) ?: null) : $v;

    $isLegal = $record->type === Contact::TYPE_LEGAL;
    $typeLabel = Contact::getTypes()[$record->type] ?? $record->type;
    $heroVariant = $record->status ? 'success' : 'gray';

    $accounts = $record->bankAccounts;
    $contracts = $this->contracts();
    $participations = $this->participations();

    // Every column the contact carries — «выводим всё, что есть».
    $details = array_values(array_filter([
        ['heroicon-o-identification', __('app.label.contact_type'), $typeLabel],
        ['heroicon-o-tag', __('app.label.legal_form'), $record->legal_form],
        ['heroicon-o-finger-print', __('app.label.inn'), $record->inn],
        ['heroicon-o-finger-print', __('app.label.pinfl'), $record->pinfl],
        ['heroicon-o-bookmark', __('app.label.oked'), $record->oked],
        ['heroicon-o-user', __('app.label.director_name'), $record->director_name],
        ['heroicon-o-user-circle', __('app.label.contact_person'), $record->contact_person],
        ['heroicon-o-phone', __('app.label.phone'), $record->phone],
        ['heroicon-o-envelope', __('app.label.email'), $record->email],
        ['heroicon-o-globe-alt', __('app.label.website'), $record->website],
        ['heroicon-o-map-pin', __('app.label.address'), $loc($record->address)],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i')],
    ], fn ($r) => filled($r[2])));
@endphp

<x-filament-panels::page>
<div class="pj" style="display:flex;flex-direction:column;gap:1rem;">
    {{-- No separate near-empty hero: тип + статус ride in the card header (the
         name is already the page H1), the data lives in the bordered card. --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-clipboard-document-list', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
            <span class="pj-hd-tags">
                <span class="pj-chip">{!! $ic($isLegal ? 'heroicon-o-building-office-2' : 'heroicon-o-user', 14) !!} {{ $typeLabel }}</span>
                <span class="pj-pill pj-pill--{{ $heroVariant }}">{{ $record->status ? __('app.status.active') : __('app.status.inactive') }}</span>
            </span>
        </header>
        <div class="ow-dets">
            @foreach ($details as [$icon, $label, $value])
                <div class="ow-row">
                    <span class="ow-row__k"><span class="ow-row__ic">{!! $ic($icon, 14) !!}</span><span class="ow-row__lb">{{ $label }}</span></span>
                    <span class="ow-row__v"><span class="ow-row__vl">{{ $value }}</span></span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Bank accounts --}}
    @if ($isLegal)
        <section class="ow-card">
            <header class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-building-library', 18) !!}</span>
                <h2 class="ow-hd__t">{{ __('app.label.bank_requisites') }}</h2>
                <span class="pj-count">{{ $accounts->count() }}</span>
            </header>
            @if ($accounts->isEmpty())
                <p class="pj-empty">{{ __('app.message.no_bank_accounts') }}</p>
            @else
                <div class="pj-table-wrap">
                    <table class="pj-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.label.currency_single') }}</th>
                                <th>{{ __('app.label.bank_account') }}</th>
                                <th>{{ __('app.label.bank_name') }}</th>
                                <th>{{ __('app.label.mfo') }}</th>
                                <th>{{ __('app.label.swift') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td>{{ $account->currency?->short_name ?? __('app.label.bank_account_any_currency') }}</td>
                                <td style="font-variant-numeric:tabular-nums;white-space:nowrap;">{{ $account->account_number }}</td>
                                <td>{{ $account->bank_name }}@if ($account->bank_address)<br><span style="color:var(--m);font-size:.78rem;">{{ $account->bank_address }}</span>@endif</td>
                                <td>{{ $account->mfo }}</td>
                                <td>{{ $account->swift }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Contracts (visibleTo-scoped) --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-document-text', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.contracts') }}</h2>
            <span class="pj-count">{{ $contracts->count() }}</span>
        </header>
        @if ($contracts->isEmpty())
            <p class="pj-empty">{{ __('app.message.no_contracts_for_contact') }}</p>
        @else
            <div class="pj-table-wrap">
                <table class="pj-table">
                    <thead>
                        <tr>
                            <th>{{ __('app.label.contract_number') }}</th>
                            <th>{{ __('app.label.contract_type_single') }}</th>
                            <th class="pj-table__num">{{ __('app.label.amount') }}</th>
                            <th>{{ __('app.label.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($contracts as $contract)
                        <tr>
                            <td style="white-space:nowrap;font-weight:600;">
                                <a class="pj-link" href="{{ ContractResource::getUrl('view', ['record' => $contract]) }}">{{ $contract->number }}</a>
                            </td>
                            <td>
                                @if ($contract->contractType)
                                    <span class="pj-pill pj-pill--{{ $contract->contractType->direction?->color() ?? 'gray' }}">{{ $contract->contractType->title }}</span>
                                @endif
                            </td>
                            <td class="pj-table__num">{{ $fmt($contract->amount) }} {{ $contract->currency?->short_name }}</td>
                            <td><span class="pj-pill pj-pill--{{ $contract->status->color() }}">{{ $contract->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Project participations --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-briefcase', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.projects') }}</h2>
            <span class="pj-count">{{ $participations->count() }}</span>
        </header>
        @if ($participations->isEmpty())
            <p class="pj-empty">{{ __('app.message.no_projects_for_contact') }}</p>
        @else
            <div class="pj-table-wrap">
                <table class="pj-table">
                    <thead>
                        <tr>
                            <th>{{ __('app.label.project_single') }}</th>
                            <th class="pj-table__num">{{ __('app.label.participant_amount') }}</th>
                            <th class="pj-table__num">{{ __('app.label.paid') }}</th>
                            <th>{{ __('app.label.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($participations as $p)
                        @php $pay = $p->payment_status; @endphp
                        <tr>
                            <td>
                                @if ($p->project)
                                    <a class="pj-link" href="{{ BaseProjectResource::resourceFor($p->project)::getUrl('view', ['record' => $p->project]) }}">{{ $p->project->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="pj-table__num">{{ $fmt($p->amount) }} {{ $p->currency?->short_name }}</td>
                            <td class="pj-table__num">{{ $fmt($p->paidAmount()) }}</td>
                            <td>
                                @if ((float) $p->amount > 0)
                                    <span class="pj-pill pj-pill--{{ $pay->color() }}">{{ $pay->label() }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
</x-filament-panels::page>
