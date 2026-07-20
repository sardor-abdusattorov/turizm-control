@php
    use App\Models\Contact;

    /** @var \App\Models\Contact $record */
    $record = $this->record;

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $loc = fn ($v) => is_array($v) ? ($v[app()->getLocale()] ?? $v['ru'] ?? reset($v) ?: null) : $v;

    $isLegal = $record->type === Contact::TYPE_LEGAL;
    $typeLabel = Contact::getTypes()[$record->type] ?? $record->type;
    $heroVariant = $record->status ? 'success' : 'gray';

    $accounts = $record->bankAccounts;

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

    {{-- Contracts + project participations: the same stock Filament tables
         the project page embeds — search, sorting and pagination for free. --}}
    @livewire(\App\Filament\Resources\Contacts\Widgets\ContactContractsTableWidget::class, ['contactId' => $record->id], key('contact-contracts-'.$record->id))
    @livewire(\App\Filament\Resources\Contacts\Widgets\ContactProjectsTableWidget::class, ['contactId' => $record->id], key('contact-projects-'.$record->id))
</div>
</x-filament-panels::page>
