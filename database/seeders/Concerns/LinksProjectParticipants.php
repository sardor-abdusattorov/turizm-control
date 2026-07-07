<?php

namespace Database\Seeders\Concerns;

use App\Enums\ParticipantRole;
use App\Models\Contact;
use App\Models\Sponsor;

/**
 * Shared participant-linking rules for the registry import seeders: airline
 * sponsor detection (per the PR Centre: Uzbekistan Airways rows are sponsor
 * contributions, not participation fees) and best-effort matching of the
 * registry's free-text company spellings onto the Contacts directory.
 */
trait LinksProjectParticipants
{
    /** @var array<string, int>|null normalized contact name => id */
    private ?array $contactMap = null;

    private ?int $airwaysSponsorId = null;

    /**
     * Registry spelling fragments (normalized) => canonical directory name.
     * Matching is substring-based so «OOO "Premium Trans"/Orient Star» and
     * «ORIENT STAR GROUP MCHJ» both land on the same counterparty.
     *
     * @var array<string, string>
     */
    private static array $contactAliases = [
        'ORIENT STAR' => 'Orient Star Group',
        'PREMIUM TRANS' => 'Orient Star Group',
        'ZAMIN DMC' => 'Zamin DMC',
        'DOLORES TRAVEL' => 'Dolores Travel Services',
        'CENTRAL ASIA TRAVEL' => 'Central Asia Travel',
        'ADVANTOUR' => 'Advantour',
        'ANUR TOUR' => 'Anur Tour',
        'SELFIE TRAVEL' => 'Selfie Travel',
        'GLOBAL EXPLORE' => 'Global Explore',
        'GEO TOUR SERVICE' => 'Geo Tour Service',
        'SANAT TRAVEL' => 'Sanat Travel Experts',
        'BEYOND DMC' => 'Beyond DMC',
        'BEYOND EXPECTATIONS' => 'Beyond Expectations',
        'REZBOOK' => 'Rezbook Global International',
        'PEOPLETRAVEL' => 'PeopleTravel',
        'MEGATOUR' => 'Megatour',
        'MEGA TOUR' => 'Megatour',
        'IMRAN TOURS' => 'Imran Tours',
        'IMRAN-TOURS' => 'Imran Tours',
        'SITARA INTERNATIONAL' => 'Sitara International',
        'SEZAM TRAVEL' => 'Sezam Travel',
        'SAMARKAND TOURISTIC CENTRE' => 'Samarkand Touristic Centre',
        'EL MUNDO' => 'El Mundo Tour',
        'AFSONA TRAVEL' => 'Afsona Travel',
        'EAST ASIA POINT' => 'East Asia Point',
        'AKFA DREAM WORLD' => 'Akfa Dream World',
        'ENJOY TRAVEL' => 'Enjoy Travel',
        'TRAVEL ISTAN' => 'Travel Istan',
    ];

    private function isSponsorRow(string $name): bool
    {
        return (bool) preg_match('/uzairways|uzbekistan\s+airways/iu', $name);
    }

    private function participantRole(string $name): string
    {
        return $this->isSponsorRow($name)
            ? ParticipantRole::Sponsor->value
            : ParticipantRole::Participant->value;
    }

    private function sponsorIdFor(string $name): ?int
    {
        if (! $this->isSponsorRow($name)) {
            return null;
        }

        return $this->airwaysSponsorId ??= Sponsor::query()
            ->firstOrCreate(
                ['name' => 'Uzbekistan Airways'],
                ['website' => 'https://www.uzairways.com', 'phone' => '+998 78 140 02 00', 'status' => true],
            )
            ->getKey();
    }

    private function contactIdFor(string $name): ?int
    {
        if ($this->isSponsorRow($name)) {
            return null;
        }

        $this->contactMap ??= Contact::query()->get()
            ->mapWithKeys(fn (Contact $contact): array => [
                self::normalizeCompany($contact->getTranslation('name', 'ru')) => $contact->getKey(),
            ])
            ->all();

        $normalized = self::normalizeCompany($name);

        foreach (self::$contactAliases as $fragment => $canonical) {
            if (str_contains($normalized, $fragment)) {
                return $this->contactMap[self::normalizeCompany($canonical)] ?? null;
            }
        }

        return $this->contactMap[$normalized] ?? null;
    }

    private static function normalizeCompany(string $name): string
    {
        $value = mb_strtoupper($name);
        $value = preg_replace('/\b(OOO|ООО|MCHJ|МЧЖ|СП|AJ|АЖ|LTD|OK|ОК|ЧП|JSC)\b/u', ' ', $value);
        $value = str_replace(['«', '»', '"', "'", '`'], ' ', $value);
        $value = preg_replace('/[-–—]/u', ' ', $value);
        $value = str_replace('ORIET', 'ORIENT', $value);

        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
