<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;

class RealTourAgentsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->agents() as $data) {
            if (Contact::query()->where('name->ru', $data['name'])->exists()) {
                continue;
            }

            Contact::query()->create([
                'type' => Contact::TYPE_LEGAL,
                'name' => ['ru' => $data['name'], 'uz' => $data['name'], 'en' => $data['name']],
                'website' => $data['website'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => true,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function agents(): array
    {
        return [
            ['name' => 'Orient Star Group', 'website' => 'https://orientstar.uz'],
            ['name' => 'Zamin DMC', 'website' => 'https://zamindmc.uz'],
            ['name' => 'Dolores Travel Services', 'website' => 'https://dolorestravel.com'],
            ['name' => 'Central Asia Travel', 'website' => 'https://centralasia-travel.com'],
            [
                'name' => 'Advantour',
                'website' => 'https://www.advantour.com',
                'phone' => '+998 78 150 30 20',
                'email' => 'tashkent@advantour.com',
                'address' => ['ru' => 'г. Ташкент', 'uz' => 'Toshkent sh.', 'en' => 'Tashkent'],
            ],
            ['name' => 'Anur Tour', 'website' => 'https://www.tourstouzbekistan.com'],
            ['name' => 'Selfie Travel', 'website' => 'https://selfietravel.kz', 'phone' => '+998 71 205 51 41'],
            ['name' => 'Global Explore', 'website' => 'https://uzbekistandiscovery.com'],
            ['name' => 'Geo Tour Service'],
            [
                'name' => 'Sanat Travel Experts',
                'website' => 'https://sanattravel.com',
                'phone' => '+998 71 140 06 08',
                'email' => 'info@ste.uz',
            ],
            ['name' => 'Beyond DMC', 'website' => 'https://beyond-dmc.com'],
            ['name' => 'Beyond Expectations', 'phone' => '+998 90 345-14-27'],
            ['name' => 'Rezbook Global International', 'website' => 'https://rezbookglobal.com'],
            ['name' => 'PeopleTravel', 'website' => 'https://www.people-travels.com'],
            ['name' => 'Megatour', 'website' => 'https://megatour.uz', 'phone' => '+998 90 185 33 33'],
            [
                'name' => 'Imran Tours',
                'phone' => '+998 97 919-69-67',
                'email' => 'imrantourservice@mail.ru',
                'address' => ['ru' => 'г. Самарканд', 'uz' => 'Samarqand sh.', 'en' => 'Samarkand'],
            ],
            ['name' => 'Sitara International', 'website' => 'https://www.sitara.com', 'phone' => '+998 71 281 41 48'],
            ['name' => 'Sezam Travel', 'website' => 'https://sezamtravel.uz'],
            ['name' => 'Samarkand Touristic Centre', 'website' => 'https://silkroad-samarkand.com'],
            ['name' => 'El Mundo Tour', 'website' => 'https://elmundotour.com'],
            ['name' => 'Afsona Travel', 'website' => 'https://afsona-travel.com', 'phone' => '+998 90 809-26-01'],
            ['name' => 'East Asia Point'],
            ['name' => 'Akfa Dream World', 'website' => 'https://www.akfagroup.com'],
            ['name' => 'Enjoy Travel', 'website' => 'https://enjoytravel.uz'],
            ['name' => 'Travel Istan', 'phone' => '+998 99 171-84-48'],
        ];
    }
}
