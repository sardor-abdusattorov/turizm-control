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
        $deadline = $contract->deadline_at;
        $signed = $contract->signed_at;
        $created = $contract->created_at ?? now();
        $locale = $contract->language ?: 'ru';

        $contactName = $contact?->getTranslation('name', $locale, false)
            ?: ($contact?->name ?? '');

        $contactAddress = $contact?->getTranslation('address', $locale, false)
            ?: ($contact?->address ?? '');

        return [
            'number' => (string) $contract->number,
            'title' => (string) $contract->title,
            'date.day' => $created->format('d'),
            'date.month' => $created->format('m'),
            'date.year' => $created->format('Y'),
            'date.full' => $created->format('d.m.Y'),
            'deadline' => $deadline?->format('d.m.Y') ?? '',
            'signed_at' => $signed?->format('d.m.Y') ?? '',
            'amount' => number_format((float) $contract->amount, 2, '.', ' '),
            'currency' => (string) ($currency?->short_name ?? ''),
            'signing_place' => (string) ($contract->signing_place ?? ''),
            'contact.name' => (string) $contactName,
            'contact.legal_form' => (string) ($contact?->legal_form ?? ''),
            'contact.inn' => (string) ($contact?->inn ?? ''),
            'contact.pinfl' => (string) ($contact?->pinfl ?? ''),
            'contact.oked' => (string) ($contact?->oked ?? ''),
            'contact.director' => (string) ($contact?->director_name ?? ''),
            'contact.address' => (string) $contactAddress,
            'contact.phone' => (string) ($contact?->phone ?? ''),
            'contact.email' => (string) ($contact?->email ?? ''),
            'contact.bank_account' => (string) ($contact?->bank_account ?? ''),
            'contact.bank_name' => (string) ($contact?->bank_name ?? ''),
            'contact.mfo' => (string) ($contact?->mfo ?? ''),
        ];
    }
}
