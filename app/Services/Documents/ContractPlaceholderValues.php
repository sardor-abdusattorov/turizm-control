<?php

namespace App\Services\Documents;

use App\Models\Contract;

class ContractPlaceholderValues
{
    public function for(Contract $contract): array
    {
        $contract->loadMissing(['contact', 'currency']);

        $contact = $contract->contact;
        $currency = $contract->currency;
        $signed = $contract->signed_at;
        $created = $contract->created_at ?? now();
        // Generated documents are Russian, so pull the Russian translation of
        // the counterparty's name/address regardless of the panel locale.
        $locale = 'ru';

        $contactName = $contact?->getTranslation('name', $locale, false)
            ?: ($contact?->name ?? '');

        $contactAddress = $contact?->getTranslation('address', $locale, false)
            ?: ($contact?->address ?? '');

        // The counterparty keeps one account per currency; quote the one that
        // matches this deal's currency (falling back to a generic account).
        $bankAccount = $contact?->bankAccountFor($contract->currency_id);

        return [
            'number' => (string) $contract->number,
            'title' => (string) $contract->title,
            'date.day' => $created->format('d'),
            'date.month' => $created->format('m'),
            'date.year' => $created->format('Y'),
            'date.full' => $created->format('d.m.Y'),
            'signed_at' => $signed?->format('d.m.Y') ?? '',
            'amount' => number_format((float) $contract->amount, 2, '.', ' '),
            'currency' => (string) ($currency?->short_name ?? ''),
            'contact.name' => (string) $contactName,
            'contact.legal_form' => (string) ($contact?->legal_form ?? ''),
            'contact.inn' => (string) ($contact?->inn ?? ''),
            'contact.pinfl' => (string) ($contact?->pinfl ?? ''),
            'contact.oked' => (string) ($contact?->oked ?? ''),
            'contact.director' => (string) ($contact?->director_name ?? ''),
            'contact.address' => (string) $contactAddress,
            'contact.phone' => (string) ($contact?->phone ?? ''),
            'contact.email' => (string) ($contact?->email ?? ''),
            'contact.bank_account' => (string) ($bankAccount?->account_number ?? ''),
            'contact.bank_name' => (string) ($bankAccount?->bank_name ?? ''),
            'contact.bank_address' => (string) ($bankAccount?->bank_address ?? ''),
            'contact.mfo' => (string) ($bankAccount?->mfo ?? ''),
            'contact.swift' => (string) ($bankAccount?->swift ?? ''),
        ];
    }
}
