<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class ForeignPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [

            [
                'legal_form' => 'LLC',
                'name' => 'Think Strawberries MENA LLC',
                'inn' => null,
                'address' => '1610, Churchill Executive Tower, Business Bay, Dubai, UAE',
                'phone' => '+971 4529 8112; +971 524712504',
                'contact_person' => 'Sanya Zaidi (GCC Business Head)',
                'accounts' => [
                    ['account_number' => 'AE940330000019101156207', 'currency' => null, 'bank_name' => 'Mashreq Bank', 'swift' => 'BOMLAEAD', 'bank_address' => 'Ground Floor, Nojoum Hotel apartment building, Abu Bakr Al Siddique Road, Opposite to Al Qabayl centre & beside Hamrain Centre, Al Murraqabat Area, Dubai, UAE'],
                ],
            ],

            [
                'legal_form' => 'SL',
                'name' => 'Stand Up Arquitectura Efimera SL',
                'inn' => null,
                'address' => null,
                'phone' => '(34) 661779050',
                'contact_person' => 'Jorge Barrasa',
                'accounts' => [
                    ['account_number' => 'ES6501280058460100040873', 'currency' => 'EUR', 'bank_name' => 'Bankinter', 'swift' => 'BKBKESMMXXX', 'bank_address' => null],
                ],
            ],

            [
                'legal_form' => null,
                'name' => 'IFEMA Madrid',
                'inn' => null,
                'address' => 'Madrid, Spain',
                'phone' => '+34 91 722 30 00',
                'contact_person' => 'Carlos Daniel Martinez (Executive Vice-President)',
                'accounts' => [
                    ['account_number' => 'ES6400492222511510001900', 'currency' => 'EUR', 'bank_name' => 'Santander (VAT ESQ2873018B)', 'swift' => 'BSCHESMM', 'bank_address' => 'Avenida de los Andes 48 (Arroyo Santo), 28042 Madrid'],
                ],
            ],

            [
                'legal_form' => 'Ltd.',
                'name' => 'Hannover Milano Fairs Shanghai Ltd., Guangzhou Branch',
                'inn' => null,
                'address' => 'Room 1510, West Tower, Poly World Trade Center, 1000 Xingang East Road, Haizhu, Guangzhou',
                'phone' => null,
                'contact_person' => 'Cathy Cui',
                'accounts' => [
                    ['account_number' => '—', 'currency' => null, 'bank_name' => 'China CITIC Bank, Guangzhou Garden Sub-branch', 'swift' => 'CIBKCNBJ510', 'bank_address' => '1/F, 12 Huajiu Road, Pearl River New City, Tianhe District, Guangzhou, China'],
                ],
            ],

            [
                'legal_form' => 'Limited',
                'name' => 'Shanghai Marking Exhibition Service Limited',
                'inn' => null,
                'address' => 'Room 1728, Ganglong Tower, No. 453 Yinggang Road, Qingpu District, Shanghai, China',
                'phone' => null,
                'contact_person' => 'Kevin Cheng',
                'accounts' => [
                    ['account_number' => '31050183450000001335', 'currency' => null, 'bank_name' => 'China Construction Bank, Shanghai Branch, Shanghai Yangtze River Delta Integration Demonstration Zone Sub-branch', 'swift' => 'PCBCCNBJSHX', 'bank_address' => 'No. 550, East Chengzhong Road, Qingpu District, Shanghai, China'],
                ],
            ],

            [
                'legal_form' => 'S.A.S.',
                'name' => 'RX France S.A.S.',
                'inn' => null,
                'address' => '52-54 Quai de Dion-Bouton, CS 80001, 92806 Puteaux Cedex, France',
                'phone' => '+33 (0)1 47 56 52 45',
                'contact_person' => 'Laurence Gaborieau (Director of Division)',
                'accounts' => [
                    ['account_number' => 'FR7630066109470001006760268', 'currency' => 'EUR', 'bank_name' => 'Credit Industriel et Commercial', 'swift' => 'CMCIFRPP', 'bank_address' => '102 Boulevard Haussmann, 75008 Paris, France'],
                ],
            ],

            [
                'legal_form' => 'L.L.C',
                'name' => 'D.M.C.E Exhibitions & Events L.L.C',
                'inn' => null,
                'address' => 'Shera Al Emarat Building, Al Buhairah, UAE',
                'phone' => null,
                'contact_person' => 'Abdul Salam',
                'accounts' => [
                    ['account_number' => 'AE740500000000019065447', 'currency' => null, 'bank_name' => 'Abu Dhabi Islamic Bank — Al Buhairah', 'swift' => 'ABDIAEAD', 'bank_address' => 'Al Buhairah, UAE'],
                ],
            ],

            [
                'legal_form' => 'FZ-LLC',
                'name' => 'ITE Eurasian Exhibitions FZ-LLC',
                'inn' => null,
                'address' => 'Office No. 2303, 23rd Floor, Aurora Tower, Al Falak Street, Dubai Media City, P.O. Box 502778, Dubai, UAE',
                'phone' => null,
                'contact_person' => 'Adam Botha',
                'accounts' => [
                    ['account_number' => 'AE330260001025245515504', 'currency' => null, 'bank_name' => 'Emirates NBD Bank PJSC', 'swift' => 'EBILAEAD', 'bank_address' => null],
                ],
            ],

            [
                'legal_form' => 'Ltd',
                'name' => 'TKS Exhibition Services Ltd',
                'inn' => null,
                'address' => "Rooms 2301-04, Hang Seng North Point Building, 341 King's Road, North Point, Hong Kong",
                'phone' => '(852) 3155 0600',
                'contact_person' => 'Maggie Chiu (Organizer)',
                'accounts' => [
                    ['account_number' => '012-777-9-2023669', 'currency' => null, 'bank_name' => 'Bank of China (Hong Kong) Ltd', 'swift' => 'BKCHHKHHXXX', 'bank_address' => 'No.1 Garden Road, Central, Hong Kong'],
                ],
            ],

            [
                'legal_form' => 'SDN BHD',
                'name' => 'MICEM SDN BHD',
                'inn' => null,
                'address' => 'Wisma MATTA, No.6, Jalan Metro Pudu, 2, Fraser Business Park, 55100 Kuala Lumpur',
                'phone' => null,
                'contact_person' => 'Maziah Binti Mihat (General Manager, MATTA)',
                'accounts' => [
                    ['account_number' => '3209193736', 'currency' => null, 'bank_name' => 'Public Bank Berhad', 'swift' => 'PBBEMYKL', 'bank_address' => 'Bandar Sunway Branch, 48 & 50 Jln PJS 11/28A, Bandar Sunway, 46150 Petaling Jaya Selangor'],
                ],
            ],

            [
                'legal_form' => 'LTD ŞTI',
                'name' => 'DESSO MIMARLIK TAS. TAN. ORG. DEK. FUAR SAN. VE TIC. LTD. STI.',
                'inn' => null,
                'address' => 'Hurriyet Bulvari Skyport Residence No:1 K:14 D:141 Beylikduzu, Istanbul, Türkiye',
                'phone' => null,
                'contact_person' => null,
                'accounts' => [
                    ['account_number' => 'TR370006200115100009088632', 'currency' => 'USD', 'bank_name' => 'Turkiye Garanti Bankasi A.S.', 'swift' => null, 'bank_address' => null],
                ],
            ],

            [
                'legal_form' => 'ООО',
                'name' => 'ООО «СЕЙС»',
                'inn' => '7707480611',
                'address' => '127030, город Москва, улица Новослободская, д. 20, помещ. 26/1/2, ком./офис 15/Н-07',
                'phone' => null,
                'contact_person' => 'Мунавширов Р.Д.',
                'accounts' => [
                    ['account_number' => '40702810900000016119', 'currency' => 'RUB', 'bank_name' => 'Азия-Инвест Банк (АО), к/с 30101810445250000234', 'swift' => 'ASIJRUMM', 'bank_address' => null],
                ],
            ],

            [
                'legal_form' => 'Private Limited',
                'name' => 'Blinkbrand Solutions Private Limited',
                'inn' => null,
                'address' => 'L 1/8 Ground Floor, NDSE II, Near Ritu Kumar, South Ext. II, Andrewsganj, South Delhi - 110049',
                'phone' => null,
                'contact_person' => 'Noel Saxena',
                'accounts' => [
                    ['account_number' => '50200069433249', 'currency' => null, 'bank_name' => 'HDFC Bank (IFSC HDFC0000011, Current Account)', 'swift' => 'HDFCINBB', 'bank_address' => 'D-1, Shopping Centre No2, Vasant Vihar, New Delhi - 110057'],
                ],
            ],

            [
                'legal_form' => 'Co., Ltd',
                'name' => 'Urumqi Rubber Tree Trade Events Co., Ltd',
                'inn' => null,
                'address' => 'Office 2004, Unit 1, No. 9 Building, Urumqi Hengda Oasis, No.1616, Fengqi street, Shuimogou District, Urumqi City, Xinjiang, China',
                'phone' => null,
                'contact_person' => 'Bai Lu',
                'accounts' => [
                    ['account_number' => '8113714015300227504', 'currency' => 'USD', 'bank_name' => 'China CITIC Bank, Urumqi Branch', 'swift' => 'CIBKCNBJ830', 'bank_address' => 'No.165 Xinhua North Road, Urumqi, Xinjiang, China'],
                ],
            ],

            [
                'legal_form' => 'Co., Ltd.',
                'name' => 'Silk Road Hesheng (Beijing) International Trading Co., Ltd.',
                'inn' => null,
                'address' => 'Room 501, No. 8 Xingsheng South Road, Zhongguancun Miyun Park, Miyun District, Beijing',
                'phone' => null,
                'contact_person' => 'Chi Lu',
                'accounts' => [
                    ['account_number' => 'J1000350435701', 'currency' => null, 'bank_name' => 'Bank of China Limited, Beijing Miyun Sub-branch', 'swift' => 'BKCHCNBJ110', 'bank_address' => 'No. 24, Gulou South Street, Miyun District, Beijing'],
                ],
            ],

            [
                'legal_form' => 'CO.,LTD',
                'name' => 'Shaanxi Feijing International Travel Agency CO.,LTD',
                'inn' => null,
                'address' => "Room 0803, 8th Floor, Qujiang Global Center, Cuihua Road, Qujiang New District, Xi'an, Shaanxi",
                'phone' => null,
                'contact_person' => 'Liu Yu',
                'accounts' => [
                    ['account_number' => '72010078814100007470', 'currency' => null, 'bank_name' => 'Shanghai Pudong Development Bank', 'swift' => 'SPDBCNSH140', 'bank_address' => null],
                ],
            ],

            [
                'legal_form' => 'CO.,LTD',
                'name' => 'KOTFA CO.,LTD',
                'inn' => null,
                'address' => 'KOTFA Bldg, 3, Sogongro 4gil, Jung-gu, Seoul, Republic of Korea',
                'phone' => null,
                'contact_person' => 'LEE Chang Yeon',
                'accounts' => [
                    ['account_number' => '140011-544655', 'currency' => null, 'bank_name' => 'Shinhan Bank, Myeongdong Branch', 'swift' => 'SHBKKRSE', 'bank_address' => null],
                ],
            ],

            [
                'legal_form' => 'ООО',
                'name' => 'ООО «EVERBLOOM PROMO»',
                'inn' => '240450027396',
                'address' => 'Room 1803, 18/F, Lucky Centre, No.165-171 Wan Chai Road, Wan Chai, Hong Kong',
                'phone' => null,
                'contact_person' => 'Сайфуддинов Ш.Р.',
                'accounts' => [
                    ['account_number' => 'KZ418562203137406915', 'currency' => 'KZT', 'bank_name' => 'АО «Банк ЦентрКредит», филиал в г. Алматы', 'swift' => 'KCJBKZKX', 'bank_address' => 'Казахстан, г. Алматы, Алмалинский р-н, ул. Панфилова, дом 98'],
                    ['account_number' => 'KZ738562203237407131', 'currency' => 'EUR', 'bank_name' => 'АО «Банк ЦентрКредит», филиал в г. Алматы', 'swift' => 'KCJBKZKX', 'bank_address' => 'Казахстан, г. Алматы, Алмалинский р-н, ул. Панфилова, дом 98'],
                    ['account_number' => 'KZ948562203337407044', 'currency' => 'USD', 'bank_name' => 'АО «Банк ЦентрКредит», филиал в г. Алматы', 'swift' => 'KCJBKZKX', 'bank_address' => 'Казахстан, г. Алматы, Алмалинский р-н, ул. Панфилова, дом 98'],
                    ['account_number' => 'KZ108562203337407214', 'currency' => 'RUB', 'bank_name' => 'АО «Банк ЦентрКредит», филиал в г. Алматы', 'swift' => 'KCJBKZKX', 'bank_address' => 'Казахстан, г. Алматы, Алмалинский р-н, ул. Панфилова, дом 98'],
                ],
            ],

            [
                'legal_form' => null,
                'name' => 'Japan Association of Travel Agents',
                'inn' => null,
                'address' => 'Tourism Expo Japan Promotion Office, Zen-Nittsu Kasumigaseki Bldg. 4F, 3-3-3 Kasumigaseki, Chiyoda-ku, Tokyo 100-0013, Japan',
                'phone' => null,
                'contact_person' => 'Manabu Hayasaka',
                'accounts' => [
                    ['account_number' => '005-2498554', 'currency' => null, 'bank_name' => 'Mizuho Bank, Marunouchi Branch', 'swift' => 'MHCBJPJT', 'bank_address' => '1-5-5 Otemachi, Chiyoda-ku, Tokyo 100-8176, Japan'],
                ],
            ],

            [
                'legal_form' => 'Sp. z o.o.',
                'name' => 'PTAK WARSAW EXPO Limited Liability Company',
                'inn' => '5342544579',
                'address' => 'Al. Katowicka 62, 05-830 Nadarzyn, Poland',
                'phone' => null,
                'contact_person' => 'Tomasz Szypuła (Prezes Zarządu)',
                'accounts' => [
                    ['account_number' => 'PL91124034351978001062264412', 'currency' => null, 'bank_name' => 'Bank Pekao SA (VAT PL5342544579, REGON 366965350)', 'swift' => 'PKOPPLPW', 'bank_address' => 'Żubra 1, 01-066 Warsaw'],
                ],
            ],

            [
                'legal_form' => 'FZE',
                'name' => 'Insight Exp FZE',
                'inn' => null,
                'address' => '8th floor, The Offices 4, One Central, Dubai World Trade Centre, Dubai, UAE, P.O. Box No. 9696',
                'phone' => null,
                'contact_person' => 'Mykyta Venikov (Manager)',
                'accounts' => [
                    ['account_number' => '—', 'currency' => null, 'bank_name' => 'Emirates NBD Bank, Business Bay Branch', 'swift' => 'EBILAEAD', 'bank_address' => null],
                ],
            ],
        ];

        $currencies = Currency::query()->pluck('id', 'short_name');

        foreach ($partners as $data) {
            $accounts = $data['accounts'];
            unset($data['accounts']);

            $name = $data['name'];
            $data['name'] = ['ru' => $name, 'uz' => $name, 'en' => $name];

            $contact = Contact::where('name->ru', $name)->first()
                ?? Contact::create(array_merge($data, [
                    'type' => Contact::TYPE_LEGAL,
                    'status' => true,
                ]));

            foreach (array_values($accounts) as $sort => $acc) {
                $contact->bankAccounts()->firstOrCreate(
                    ['account_number' => $acc['account_number']],
                    [
                        'currency_id' => $acc['currency'] ? $currencies->get($acc['currency']) : null,
                        'bank_name' => $acc['bank_name'],
                        'bank_address' => $acc['bank_address'],
                        'swift' => $acc['swift'],
                        'mfo' => null,
                        'sort' => $sort,
                    ],
                );
            }
        }
    }
}
