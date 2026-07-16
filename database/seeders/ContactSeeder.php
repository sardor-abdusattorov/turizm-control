<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [
            // 1. ООО «BEYOND DMC»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «BEYOND DMC»',
                    'uz' => '"BEYOND DMC" MChJ',
                    'en' => 'BEYOND DMC LLC',
                ],
                'inn' => '311687587',
                'address' => [
                    'ru' => 'Республика Узбекистан, г. Ташкент, Яккасарайский район, МСГ «Ракатбоши», ул. Баходир, 44А',
                    'uz' => 'Oʻzbekiston Respublikasi, Toshkent shahri, Yakkasaroy tumani, "Rakatboshi" MFY, Bahodir koʻchasi, 44A',
                    'en' => '44A Bahodir Street, Rakatboshi Mahalla, Yakkasaray District, Tashkent, Republic of Uzbekistan',
                ],
                'phone' => '+998 99 235 48 36, +998 94 092 11 75',
                'email' => 'info@beyond-dmc.com',
                'director_name' => 'Rajesh Kumar',
                'bank_account' => '20208000907160357001',
                'bank_name' => 'ATB "Uzsanoatqurilishbank", Rakat Branch',
                'mfo' => null,
                'swift' => 'USJIUZ22',
                'oked' => null,
            ],

            // 2. СП «Beyond Expectations»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'СП',
                'name' => [
                    'ru' => 'СП «Beyond Expectations»',
                    'uz' => '"Beyond Expectations" QK',
                    'en' => 'Beyond Expectations JV',
                ],
                'inn' => '304415856',
                'address' => [
                    'ru' => 'г. Ташкент, Яккасарайский район, Ракат 1-13',
                    'uz' => 'Toshkent shahri, Yakkasaroy tumani, Rakat 1-13',
                    'en' => 'Rakat 1-13, Yakkasaray District, Tashkent',
                ],
                'phone' => '+998 90 955 94 31',
                'email' => 'beyondexpectations.uz@gmail.com',
                'director_name' => 'Хакимова О.О.',
                'bank_account' => '20208000200690529001',
                'bank_name' => 'в Яккасарайском фил. Давр банк',
                'mfo' => '01069',
                'swift' => null,
                'oked' => '79110',
            ],

            // 3. ООО «ASIA LUXE TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ASIA LUXE TRAVEL»',
                    'uz' => '"ASIA LUXE TRAVEL" MChJ',
                    'en' => 'ASIA LUXE TRAVEL LLC',
                ],
                'inn' => '305855245',
                'address' => [
                    'ru' => 'г. Ташкент, Мирабадский район, ул. Амира Темура, 24',
                    'uz' => 'Toshkent shahri, Mirabod tumani, Amir Temur koʻchasi, 24',
                    'en' => '24 Amir Temur Street, Mirabad District, Tashkent',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'Ташмухамедов Сирожидин',
                'bank_account' => '20208000604996690001',
                'bank_name' => 'ATIB "IPOTEKA BANK" Yunusabad Branch',
                'mfo' => '00837',
                'swift' => 'UZHOUZ22',
                'oked' => null,
            ],

            // 4. ООО «Rezbook Global International»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Rezbook Global International»',
                    'uz' => '"Rezbook Global International" MChJ',
                    'en' => 'Rezbook Global International LLC',
                ],
                'inn' => '311247643',
                'address' => [
                    'ru' => 'г. Ташкент, Яккасарайский район, ул. Мукумий 7/1',
                    'uz' => 'Toshkent shahri, Yakkasaroy tumani, Mukumiy koʻchasi 7/1',
                    'en' => '7/1 Mukumiy Street, Yakkasaray District, Tashkent',
                ],
                'phone' => '+998 90 951 46 64, +998 93 324 38 83',
                'email' => 'Uzbekistan@rezbookglobal.com',
                'director_name' => 'Сафаров Ж.Ф.',
                'bank_account' => '20208000907044121001',
                'bank_name' => '"Asia Alliance Bank" City Branch Tashkent',
                'mfo' => '00981',
                'swift' => null,
                'oked' => '79110',
            ],

            // 5. ООО «VERSAILLES TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «VERSAILLES TRAVEL»',
                    'uz' => '"VERSAILLES TRAVEL" MChJ',
                    'en' => 'VERSAILLES TRAVEL LLC',
                ],
                'inn' => '207153986',
                'address' => [
                    'ru' => '100015, г. Ташкент, ул. Афросиеб 8/1',
                    'uz' => '100015, Toshkent shahri, Afrosiyob koʻchasi 8/1',
                    'en' => '8/1 Afrosiyob Street, Tashkent, 100015',
                ],
                'phone' => '(+99871) 256 64 21; (+99891) 162 22 09',
                'email' => null,
                'director_name' => 'Иноятова Н.Ш.',
                'bank_account' => '20208000604996690001',
                'bank_name' => 'в ОАИКБ "Ипак Йули" Яккасарайский Филиал',
                'mfo' => '01028',
                'swift' => null,
                'oked' => '63300',
            ],

            // 6. СП «Sanat Travel Experts»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'СП',
                'name' => [
                    'ru' => 'СП «Sanat Travel Experts»',
                    'uz' => '"Sanat Travel Experts" QK',
                    'en' => 'Sanat Travel Experts JV',
                ],
                'inn' => '305072628',
                'address' => [
                    'ru' => 'г. Ташкент, Мирзо-Улугбекский район, ул. Чаткол 2, индекс 100007',
                    'uz' => 'Toshkent shahri, Mirzo Ulugʻbek tumani, Chatqol koʻchasi 2, 100007',
                    'en' => '2 Chatqol Street, Mirzo Ulugbek District, Tashkent, 100007',
                ],
                'phone' => '+998 99 807 06 08',
                'email' => 'timur@ste.uz',
                'director_name' => 'Ишмухаметов Тимур',
                'bank_account' => '20208000500802685001',
                'bank_name' => 'ЧАБ "TRASTBANK" Darhan Branch',
                'mfo' => '00954',
                'swift' => null,
                'oked' => '79.12.0',
            ],

            // 7. ООО «SAMARCANDA ADVENTURES»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SAMARCANDA ADVENTURES»',
                    'uz' => '"SAMARCANDA ADVENTURES" MChJ',
                    'en' => 'SAMARCANDA ADVENTURES LLC',
                ],
                'inn' => '311728344',
                'address' => [
                    'ru' => 'Ависозлар-3, 50, Яшнабадский р-н г. Ташкент',
                    'uz' => 'Avisozlar-3, 50, Yashnobod tumani, Toshkent',
                    'en' => 'Avisozlar-3, 50, Yashnabad District, Tashkent',
                ],
                'phone' => '+998-50-444-17-17',
                'email' => 'info@samarcanda.uz',
                'director_name' => 'Муминов А.К.',
                'bank_account' => '20208000607167211001',
                'bank_name' => 'ОПЕРУ АИКБ Яшнабадский ф-л КАПИТАЛБАНК',
                'mfo' => '01136',
                'swift' => null,
                'oked' => '79120',
            ],

            // 8. ООО «ZAMIN TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ZAMIN TRAVEL»',
                    'uz' => '"ZAMIN TRAVEL" MChJ',
                    'en' => 'ZAMIN TRAVEL LLC',
                ],
                'inn' => '205315190',
                'address' => [
                    'ru' => 'г. Самарканд, ул. И. Каримова, 35',
                    'uz' => 'Samarqand, I. Karimov koʻchasi, 35',
                    'en' => '35 I. Karimov Street, Samarkand',
                ],
                'phone' => '(99866) 2350036',
                'email' => 'info@zamin-travel.com',
                'director_name' => 'Усанов И.Х.',
                'bank_account' => '20208000704368503001',
                'bank_name' => 'СО НБ ВЭД Р. Уз',
                'mfo' => '00278',
                'swift' => null,
                'oked' => '79120',
            ],

            // 9. ООО «GLOBAL EXPLORE»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «GLOBAL EXPLORE»',
                    'uz' => '"GLOBAL EXPLORE" MChJ',
                    'en' => 'GLOBAL EXPLORE LLC',
                ],
                'inn' => '308716494',
                'address' => [
                    'ru' => 'Чиланзарский район, Е квартал, 3/82, г. Ташкент',
                    'uz' => 'Chilonzor tumani, Y kvartal, 3/82, Toshkent',
                    'en' => 'Y квартал 3/82, Chilanzar District, Tashkent',
                ],
                'phone' => '+99897 444 45 33',
                'email' => 'info@uzbekistandiscovery.com',
                'director_name' => 'Курбонов И.Н.',
                'bank_account' => '20208000405418270001',
                'bank_name' => null,
                'mfo' => '00401',
                'swift' => null,
                'oked' => null,
            ],

            // 10. ООО «Sonder Voyage»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Sonder Voyage»',
                    'uz' => '"Sonder Voyage" MChJ',
                    'en' => 'Sonder Voyage LLC',
                ],
                'inn' => '311240258',
                'address' => [
                    'ru' => 'г. Ташкент, Кибрайский район, Янги Авлод, 154',
                    'uz' => 'Toshkent, Qibray tumani, Yangi Avlod, 154',
                    'en' => '154 Yangi Avlod, Kibray District, Tashkent',
                ],
                'phone' => '+998 90 908 00 82',
                'email' => 'sonderuz@gmail.com',
                'director_name' => 'Сарикова Д.У.',
                'bank_account' => '20208000107085063001',
                'bank_name' => 'ОПЕРУ АИКБ Кибрай т. ЧЭКИ "INVEST FINANCE BANK"',
                'mfo' => '01056',
                'swift' => null,
                'oked' => '79120',
            ],

            // 11. ООО «ADVANTOUR»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ADVANTOUR»',
                    'uz' => '"ADVANTOUR" MChJ',
                    'en' => 'ADVANTOUR LLC',
                ],
                'inn' => '204702676',
                'address' => [
                    'ru' => 'г. Ташкент, Яккасарайский район, ул. Миробод, 1 дом 43',
                    'uz' => 'Toshkent, Yakkasaroy tumani, Mirobod koʻchasi, 1-uy 43',
                    'en' => '43 Mirobod Street, 1, Yakkasaray District, Tashkent',
                ],
                'phone' => '+99878 150 30 20',
                'email' => 'info@advantour.com',
                'director_name' => 'Назарова Ф.О.',
                'bank_account' => '20208000504288840001',
                'bank_name' => 'АО КДБ Банк Узбекистан',
                'mfo' => '00842',
                'swift' => null,
                'oked' => '79120',
            ],

            // 12. ООО «EL MUNDO TOUR»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «EL MUNDO TOUR»',
                    'uz' => '"EL MUNDO TOUR" MChJ',
                    'en' => 'EL MUNDO TOUR LLC',
                ],
                'inn' => '206752534',
                'address' => [
                    'ru' => 'г. Ташкент, Шайхантахурский район, край Чорсу, дом 4, квартира 117',
                    'uz' => 'Toshkent, Shayxontohur tumani, Chorsu chekkasi, 4-uy, 117-xonadon',
                    'en' => '4 Chorsu, Apartment 117, Shaykhantakhur District, Tashkent',
                ],
                'phone' => '+998 97 707 32 82',
                'email' => null,
                'director_name' => 'Хаитов Т.Ю.',
                'bank_account' => '20208000104537447001',
                'bank_name' => 'г. Ташкент, "УЗСАНОАТКУРИЛИШБАНКИ" АКБ Головной филиал',
                'mfo' => '00440',
                'swift' => null,
                'oked' => '79120',
            ],

            // 13. ООО «CAUCASSIA TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «CAUCASSIA TRAVEL»',
                    'uz' => '"CAUCASSIA TRAVEL" MChJ',
                    'en' => 'CAUCASSIA TRAVEL LLC',
                ],
                'inn' => '310711881',
                'address' => [
                    'ru' => 'Самарканд шахар, Орзу МФЙ, Гагарина кучаси, 36-уй',
                    'uz' => 'Samarqand shahar, Orzu MFY, Gagarin koʻchasi, 36-uy',
                    'en' => '36 Gagarin Street, Orzu MFY, Samarkand',
                ],
                'phone' => '+998 94 540 53 54',
                'email' => null,
                'director_name' => 'Анохин В.',
                'bank_account' => '20208000405702929001',
                'bank_name' => 'ЧАБ "TRASTBANK" Дарханский ф-л.',
                'mfo' => '01061',
                'swift' => null,
                'oked' => null,
            ],

            // 14. ИП ООО «UZTUR INVESTMENT AND DEVELOPMENT»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ИП ООО',
                'name' => [
                    'ru' => 'ИП ООО «UZTUR INVESTMENT AND DEVELOPMENT»',
                    'uz' => '"UZTUR INVESTMENT AND DEVELOPMENT" IP MChJ',
                    'en' => 'UZTUR INVESTMENT AND DEVELOPMENT LLC',
                ],
                'inn' => '306777698',
                'address' => [
                    'ru' => 'Республика Узбекистан, г. Ташкент, Юнусабадский р-он, ул. А. Кодирий, 24А',
                    'uz' => 'Oʻzbekiston Respublikasi, Toshkent shahri, Yunusobod tumani, A. Qodiriy koʻchasi, 24A',
                    'en' => '24A A. Kodiriy Street, Yunusabad District, Tashkent, Republic of Uzbekistan',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'Эрик Сейдул',
                'bank_account' => '20208000905132507009',
                'bank_name' => '"IpotekaBank" ОТВ Group',
                'mfo' => '00937',
                'swift' => null,
                'oked' => null,
            ],

            // 15. ООО «SunRoad»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SunRoad»',
                    'uz' => '"SunRoad" MChJ',
                    'en' => 'SunRoad LLC',
                ],
                'inn' => '311534832',
                'address' => [
                    'ru' => '100070, г. Ташкент, ул. Пахлавон Махмуд, 2 проезд 26',
                    'uz' => '100070, Toshkent, Paxlavon Mahmud koʻchasi, 2-tor koʻchasi 26',
                    'en' => '26, 2nd Passage, Pakhlavon Makhmud Street, Tashkent, 100070',
                ],
                'phone' => '+998 90 922 85 49',
                'email' => null,
                'director_name' => 'Исакова Г.',
                'bank_account' => '20208000907123985001',
                'bank_name' => '«Капитал» АТБ банк',
                'mfo' => '01158',
                'swift' => null,
                'oked' => '79120',
            ],

            // 16. ООО «Another Travel»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Another Travel»',
                    'uz' => '"Another Travel" MChJ',
                    'en' => 'Another Travel LLC',
                ],
                'inn' => '309484364',
                'address' => [
                    'ru' => 'г. Ташкент, Шайхантахурский район, ул. Зулфияхоним, Лабзак МСГ, 14-дом',
                    'uz' => 'Toshkent, Shayxontohur tumani, Zulfiyaxonim koʻchasi, Labzak MFY, 14-uy',
                    'en' => '14 Zulfiyaxonim Street, Labzak MFY, Shaykhantakhur District, Tashkent',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'Тагаева М.',
                'bank_account' => '20208000805519373001',
                'bank_name' => 'ЧАБ "TRASTBANK" Дарханский ф-л.',
                'mfo' => '00954',
                'swift' => null,
                'oked' => '79110',
            ],

            // 17. СП «East Asia Point»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'СП',
                'name' => [
                    'ru' => 'СП «East Asia Point»',
                    'uz' => '"East Asia Point" QK',
                    'en' => 'East Asia Point JV',
                ],
                'inn' => '207160718',
                'address' => [
                    'ru' => 'г. Ташкент, ул. Бинокор, дом 25',
                    'uz' => 'Toshkent, Binokor koʻchasi, 25',
                    'en' => '25 Binokor Street, Tashkent',
                ],
                'phone' => '+998977207752',
                'email' => null,
                'director_name' => 'Расулев Б.Б.',
                'bank_account' => '20208000306000278001',
                'bank_name' => 'Bank Asaka Nurafshan branch',
                'mfo' => '00873',
                'swift' => 'ASBKUZ22XXX',
                'oked' => '79120',
            ],

            // 18. ООО «KARAVAN TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «KARAVAN TRAVEL»',
                    'uz' => '"KARAVAN TRAVEL" MChJ',
                    'en' => 'KARAVAN TRAVEL LLC',
                ],
                'inn' => '300832523',
                'address' => [
                    'ru' => 'Улица М. Кошгарий 72, Самарканд, 140100',
                    'uz' => 'M. Koshgariy koʻchasi 72, Samarqand, 140100',
                    'en' => '72 M. Koshgariy Street, Samarkand, 140100',
                ],
                'phone' => '+998 97 910 13 87, +998 97 288 44 00',
                'email' => 'jahongir.kt@gmail.com',
                'director_name' => 'Санакулов Ж.Х.',
                'bank_account' => '20208000504706353001',
                'bank_name' => 'Национальный банк внешнеэкономической деятельности Республики Узбекистан, Самаркандский филиал',
                'mfo' => '00273',
                'swift' => null,
                'oked' => null,
            ],

            // 19. ООО «SACRED EAST TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SACRED EAST TRAVEL»',
                    'uz' => '"SACRED EAST TRAVEL" MChJ',
                    'en' => 'SACRED EAST TRAVEL LLC',
                ],
                'inn' => '308308202',
                'address' => [
                    'ru' => 'г. Самарканд, ул. Пушкина 3',
                    'uz' => 'Samarqand, Pushkin koʻchasi 3',
                    'en' => '3 Pushkin Street, Samarkand',
                ],
                'phone' => '+998 906550500',
                'email' => 'info@sacredeasttravel.com',
                'director_name' => 'Садыкова Н.У.',
                'bank_account' => '20208000605365263001',
                'bank_name' => 'ЧАБ «Трастбанк» г. Ташкент',
                'mfo' => '00491',
                'swift' => null,
                'oked' => null,
            ],

            // 20. ООО «Uktamxon Tour»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Uktamxon Tour»',
                    'uz' => '"Uktamxon Tour" MChJ',
                    'en' => 'Uktamxon Tour LLC',
                ],
                'inn' => '310148514',
                'address' => [
                    'ru' => 'г. Самарканд, ул. Мирзо Улугбек-31',
                    'uz' => 'Samarqand, Mirzo Ulugʻbek koʻchasi 31',
                    'en' => '31 Mirzo Ulugbek Street, Samarkand',
                ],
                'phone' => '(97) 577 07 57',
                'email' => 'uktamxontour01@gmail.com',
                'director_name' => 'Махсудов Мансур Уктамович',
                'bank_account' => '20208000305603836001',
                'bank_name' => 'ХАТБ «Ипак йули банк»',
                'mfo' => '00283',
                'swift' => null,
                'oked' => null,
            ],

            // 21. ООО «Selfie Travel»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Selfie Travel»',
                    'uz' => '"Selfie Travel" MChJ',
                    'en' => 'Selfie Travel LLC',
                ],
                'inn' => '305299709',
                'address' => [
                    'ru' => 'г. Ташкент, Шайхантахурский р-н, ул. Себзор 7/71',
                    'uz' => 'Toshkent, Shayxontohur tumani, Sebzor koʻchasi 7/71',
                    'en' => '7/71 Sebzor Street, Shaykhantakhur District, Tashkent',
                ],
                'phone' => '+998910185141',
                'email' => 'inc@selfietravel.uz',
                'director_name' => 'Мухрумбаев Р.М.',
                'bank_account' => '20208000200843864001',
                'bank_name' => 'ЦОО АКБ "Капиталбанк"',
                'mfo' => '01088',
                'swift' => null,
                'oked' => null,
            ],

            // 22. ЧП «EMERALD TRAVEL»
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ЧП',
                'name' => [
                    'ru' => 'ЧП «EMERALD TRAVEL»',
                    'uz' => '"EMERALD TRAVEL" XK',
                    'en' => 'EMERALD TRAVEL PE',
                ],
                'inn' => '206228464',
                'address' => [
                    'ru' => 'Бухара, улица М. Анбара, дом 91, индекс 200118',
                    'uz' => 'Buxoro, M. Anbar koʻchasi, 91-uy, 200118',
                    'en' => '91 M. Anbar Street, Bukhara, 200118',
                ],
                'phone' => '+998905120123',
                'email' => 'info@emerald.uz',
                'director_name' => 'Ботирова Нигинабону',
                'bank_account' => '20208000104477456001',
                'bank_name' => '«Национальный банк» Филиал Академический, город Ташкент',
                'mfo' => '00431',
                'swift' => null,
                'oked' => null,
            ],

            // 23. ООО «SEZAM TRAVEL» (НОВЫЙ - ITB B-2)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SEZAM TRAVEL»',
                    'uz' => '"SEZAM TRAVEL" MChJ',
                    'en' => 'SEZAM TRAVEL LLC',
                ],
                'inn' => '301755974',
                'address' => [
                    'ru' => '44/1, ул. Чиланзарская, г. Ташкент',
                    'uz' => '44/1, Chilonzor koʻchasi, Toshkent',
                    'en' => '44/1 Chilanzar Street, Tashkent',
                ],
                'phone' => '+99871 271 2267',
                'email' => 'sales@sezamtravel.com',
                'director_name' => 'Сабитова К.А.',
                'bank_account' => '20208000904879519001',
                'bank_name' => 'ЧАБ Трастбанк офис ТОШКЕНТ',
                'mfo' => '00491',
                'swift' => null,
                'oked' => '79900',
            ],

            // 24. ЧП «JALOL QUDUQ AVIA TRANS» (НОВЫЙ - ITB B-3)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ЧП',
                'name' => [
                    'ru' => 'ЧП «JALOL QUDUQ AVIA TRANS»',
                    'uz' => '"JALOL QUDUQ AVIA TRANS" XK',
                    'en' => 'JALOL QUDUQ AVIA TRANS PE',
                ],
                'inn' => '302076552',
                'address' => [
                    'ru' => 'Джизакская область, Жалолкудукский район, г. Охунбобоев, махалля Намуна',
                    'uz' => 'Jizzax viloyati, Jalolquduq tumani, Oxunboboyev shahri, Namuna mahallasi',
                    'en' => 'Namuna Mahalla, Okhunboboyev, Jalolquduq District, Jizzakh Region',
                ],
                'phone' => '+9989117305001',
                'email' => 'saida9717@mail.ru',
                'director_name' => 'Ахмедова Саида',
                'bank_account' => '20208840104929827005',
                'bank_name' => 'TRASTBANK',
                'mfo' => '01074',
                'swift' => null,
                'oked' => null,
            ],

            // 25. ООО «PEOPLETRAVEL» (НОВЫЙ - ITB B-4)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «PEOPLETRAVEL»',
                    'uz' => '"PEOPLETRAVEL" MChJ',
                    'en' => 'PEOPLETRAVEL LLC',
                ],
                'inn' => '207071194',
                'address' => [
                    'ru' => 'г. Ташкент, ул. М. Кеужа, 47/1',
                    'uz' => 'Toshkent, M. Keuja koʻchasi, 47/1',
                    'en' => '47/1 M. Keuja Street, Tashkent',
                ],
                'phone' => '+998 71 232 13 33',
                'email' => 'info@peopletravel.uz',
                'director_name' => 'Султанов У.А.',
                'bank_account' => '2020800053414824001',
                'bank_name' => 'ОПЕРУ АНБ, Айна Айланее Банк',
                'mfo' => '01085',
                'swift' => null,
                'oked' => '79900',
            ],

            // 26. СП «Antique Travel Experts» (НОВЫЙ - ITB B-5)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'СП',
                'name' => [
                    'ru' => 'СП «Antique Travel Experts»',
                    'uz' => '"Antique Travel Experts" QK',
                    'en' => 'Antique Travel Experts JV',
                ],
                'inn' => '310149060',
                'address' => [
                    'ru' => 'г. Бухара, ул. К. Муртазоева, 9/1, 6-хонадон',
                    'uz' => 'Buxoro, Q. Murtazoyev koʻchasi 9/1, 6-xonadon',
                    'en' => '9/1 K. Murtazoyev Street, Apartment 6, Bukhara',
                ],
                'phone' => '+997977255255',
                'email' => 'info@antique-travel.com',
                'director_name' => 'Ражабов Бекзод',
                'bank_account' => '20208000505603800001',
                'bank_name' => '«Milly bank» BOSH Ofisi AJ',
                'mfo' => '00450',
                'swift' => null,
                'oked' => null,
            ],

            // 27. ООО «IMRAN-TOURS» (НОВЫЙ - ITB B-8)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «IMRAN-TOURS»',
                    'uz' => '"IMRAN-TOURS" MChJ',
                    'en' => 'IMRAN-TOURS LLC',
                ],
                'inn' => '309963464',
                'address' => [
                    'ru' => 'г. Ташкент, Кичик Халка Йули, 2-уй, 2-хонадон',
                    'uz' => 'Toshkent, Kichik Xalqa Yoʻli, 2-uy, 2-xonadon',
                    'en' => '2 Kichik Xalqa Yoli Street, Apartment 2, Tashkent',
                ],
                'phone' => '+998977376060',
                'email' => 'assalom.tour@mail.ru',
                'director_name' => 'Дадаходжаев С.Ф.',
                'bank_account' => '20208000905579066001',
                'bank_name' => 'Хамкор Банк Шайхантахур филиали',
                'mfo' => '00083',
                'swift' => null,
                'oked' => '46900',
            ],

            // 28. ООО «Geo Tour Service» (НОВЫЙ - ITB B-9)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Geo Tour Service»',
                    'uz' => '"Geo Tour Service" MChJ',
                    'en' => 'Geo Tour Service LLC',
                ],
                'inn' => '303906847',
                'address' => [
                    'ru' => 'г. Ташкент, Чиланзарский район, 9 квартал, дом 12, квартира 1',
                    'uz' => 'Toshkent, Chilonzor tumani, 9-mavze, 12-uy, 1-xonadon',
                    'en' => '9 Mavze, 12, Apartment 1, Chilanzar District, Tashkent',
                ],
                'phone' => '+998 71 288 44 48',
                'email' => 'info@orientmice.com',
                'director_name' => 'Пардаев Ш.',
                'bank_account' => '20208000200611475001',
                'bank_name' => null,
                'mfo' => null,
                'swift' => null,
                'oked' => '79110',
            ],

            // 29. ИП ООО «SITARA INTERNATIONAL LTD» (НОВЫЙ - ITB B-14)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ИП ООО',
                'name' => [
                    'ru' => 'ИП ООО «SITARA INTERNATIONAL LTD»',
                    'uz' => '"SITARA INTERNATIONAL LTD" IP MChJ',
                    'en' => 'SITARA INTERNATIONAL LTD LLC',
                ],
                'inn' => '201904269',
                'address' => [
                    'ru' => '100100 г. Ташкент, ул. Шота Руставели, 45',
                    'uz' => '100100 Toshkent, Shota Rustaveli koʻchasi, 45',
                    'en' => '45 Shota Rustaveli Street, Tashkent, 100100',
                ],
                'phone' => '+99871 2814148',
                'email' => 'tashkent@sitara.com',
                'director_name' => 'Сангилова Н.Б.',
                'bank_account' => '20208000300155609001',
                'bank_name' => 'SQB "Uzpromstroybank"',
                'mfo' => '00440',
                'swift' => null,
                'oked' => '79120',
            ],

            // 30. ООО «CENTRAL ASIA TRAVEL» (ОБНОВЛЕН - ITB B-1)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «CENTRAL ASIA TRAVEL»',
                    'uz' => '"CENTRAL ASIA TRAVEL" MChJ',
                    'en' => 'CENTRAL ASIA TRAVEL LLC',
                ],
                'inn' => '206976184',
                'address' => [
                    'ru' => 'Улица Ойбек 40, офис 3, Ташкент, Узбекистан',
                    'uz' => 'Oybek koʻchasi 40, ofis 3, Toshkent, Oʻzbekiston',
                    'en' => '40 Oybek Street, Office 3, Tashkent, Uzbekistan',
                ],
                'phone' => '71 20002 99',
                'email' => 'info@centralasia-travel.com',
                'director_name' => 'Сербин Н.А.',
                'bank_account' => '20208000304736797001',
                'bank_name' => '«АСАКА БАНК» Юнусабадского отд.',
                'mfo' => '00873',
                'swift' => null,
                'oked' => '79120',
            ],

            // 31. СП ООО «DOLORES TRAVEL SERVICES» (ОБНОВЛЕН - ITB B-6)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'СП ООО',
                'name' => [
                    'ru' => 'СП ООО «Dolores Travel Services»',
                    'uz' => '"Dolores Travel Services" QK MChJ',
                    'en' => 'Dolores Travel Services JV LLC',
                ],
                'inn' => '205424619',
                'address' => [
                    'ru' => '104 А, ул. Кичик Бешагач, Яккасарайский р-он, г. Ташкент',
                    'uz' => '104A, Kichik Beshagach koʻchasi, Yakkasaroy tumani, Toshkent',
                    'en' => '104A Kichik Beshagach Street, Yakkasaray District, Tashkent',
                ],
                'phone' => '+99855 515-88-83',
                'email' => 'office@dolorestravel.com',
                'director_name' => 'Байзакова Ш.Б.',
                'bank_account' => '20214000804383781001',
                'bank_name' => 'АКБ «Хамкорбанк» Ташкентский региональный ОБУ',
                'mfo' => '00083',
                'swift' => null,
                'oked' => '79120',
            ],

            // 32. ООО «MEGA TOUR» (ОБНОВЛЕН - ITB B-7)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «MEGA TOUR»',
                    'uz' => '"MEGA TOUR" MChJ',
                    'en' => 'MEGA TOUR LLC',
                ],
                'inn' => '205886850',
                'address' => [
                    'ru' => 'г. Ташкент, ул. Буюк Ипак Йули, 235 А',
                    'uz' => 'Toshkent, Buyuk Ipak Yoʻli koʻchasi, 235 A',
                    'en' => '235 A Buyuk Ipak Yoli Street, Tashkent',
                ],
                'phone' => '+998 90 185 33 33',
                'email' => 'megatour@mail.com',
                'director_name' => 'Хабарова А.В.',
                'bank_account' => '20208000004442549001',
                'bank_name' => 'ЧЗАКБ «Davr Bank» М. Улугбекский ф-л',
                'mfo' => '01072',
                'swift' => null,
                'oked' => '79120',
            ],

            // 33. ООО «ORIENT STAR GROUP» (ОБНОВЛЕН - ITB B-10)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ORIENT STAR GROUP»',
                    'uz' => '"ORIENT STAR GROUP" MChJ',
                    'en' => 'ORIENT STAR GROUP LLC',
                ],
                'inn' => '303722254',
                'address' => [
                    'ru' => '100011, г. Ташкент, Шайхонтохурский район, ул. Анхор буйи, 6',
                    'uz' => '100011, Toshkent, Shayxontohur tumani, Anhor boʻyi koʻchasi, 6',
                    'en' => '6 Anhor Boʻyi Street, Shaykhantakhur District, Tashkent, 100011',
                ],
                'phone' => '+998977222226',
                'email' => 'welcome@orientstar.uz',
                'director_name' => 'Бегматов С.Х.',
                'bank_account' => '20208000400565085001',
                'bank_name' => 'АКБ "Hamkorbank" Яккасарайский филиал',
                'mfo' => '00083',
                'swift' => 'KHKKUZ22XXX',
                'oked' => '79120',
            ],

            // 34. ООО «ANUR TOUR» (ОБНОВЛЕН - ITB B-15)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ANUR TOUR»',
                    'uz' => '"ANUR TOUR" MChJ',
                    'en' => 'ANUR TOUR LLC',
                ],
                'inn' => '202232646',
                'address' => [
                    'ru' => 'г. Ташкент, Ц-5, дом-65, кв-185, 100017',
                    'uz' => 'Toshkent, C-5, 65-uy, 185-xonadon, 100017',
                    'en' => 'C-5, 65, Apartment 185, Tashkent, 100017',
                ],
                'phone' => '+99871 2302260',
                'email' => 'info@anurtour.com',
                'director_name' => 'Раубаев М.К.',
                'bank_account' => '20208000201522892001',
                'bank_name' => 'ОПЕРУ АИКБ «Ипак Йули»',
                'mfo' => '00444',
                'swift' => null,
                'oked' => null,
            ],

            // 35. ООО «OLIMPIK TURSERVIS» (ОБНОВЛЕН - ITB B-17)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «OLIMPIK TURSERVIS»',
                    'uz' => '"OLIMPIK TURSERVIS" MChJ',
                    'en' => 'OLIMPIK TURSERVIS LLC',
                ],
                'inn' => '204511749',
                'address' => [
                    'ru' => 'г. Ташкент, ул. С. Азимова, 51А',
                    'uz' => 'Toshkent, S. Azimov koʻchasi, 51A',
                    'en' => '51A S. Azimov Street, Tashkent',
                ],
                'phone' => '+998977470630',
                'email' => 'info@ots.uz',
                'director_name' => 'Сильченко В.В.',
                'bank_account' => '20208000904262013001',
                'bank_name' => 'АКБ «Azia Alliance Bank»',
                'mfo' => '01095',
                'swift' => null,
                'oked' => '79900',
            ],

            // 36. ООО «ROCKET DMC REGISTAN» (ОБНОВЛЕН - ITB B-12)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Rocket DMC Registan»',
                    'uz' => '"Rocket DMC Registan" MChJ',
                    'en' => 'Rocket DMC Registan LLC',
                ],
                'inn' => '310367653',
                'address' => [
                    'ru' => 'Самарканд, Кунигил',
                    'uz' => 'Samarqand, Kunigil',
                    'en' => 'Samarkand, Kunigil',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'Мухтарова Н.М.',
                'bank_account' => '20208000705631732001',
                'bank_name' => null,
                'mfo' => '00450',
                'swift' => null,
                'oked' => null,
            ],

            // 37. ООО «ZAMIN DMC» (ОБНОВЛЕН - ITB B-13)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ZAMIN DMC»',
                    'uz' => '"ZAMIN DMC" MChJ',
                    'en' => 'ZAMIN DMC LLC',
                ],
                'inn' => '311343097',
                'address' => [
                    'ru' => 'г. Ташкент, Мирабадский район, ул. А. Темура, дом 22',
                    'uz' => 'Toshkent shahri, Mirabod tumani, A. Temur koʻchasi, 22',
                    'en' => '22 A. Temur Street, Mirabad District, Tashkent',
                ],
                'phone' => '+998 99 575 50 50',
                'email' => 'inbound@asialuxe.uz',
                'director_name' => 'Ходжаев Т.А.',
                'bank_account' => '20208000605646416001',
                'bank_name' => null,
                'mfo' => '00440',
                'swift' => null,
                'oked' => '55100',
            ],
        ];

        foreach ($contacts as $data) {
            // Bank details live in their own table now (one account per
            // currency). Peel the flat block out of the literal and attach it
            // as the counterparty's first, currency-agnostic account.
            $bank = [
                'account_number' => $data['bank_account'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'mfo' => $data['mfo'] ?? null,
                'swift' => $data['swift'] ?? null,
            ];
            unset($data['bank_account'], $data['bank_name'], $data['mfo'], $data['swift']);

            $key = $data['type'] === Contact::TYPE_LEGAL
                ? ['inn' => $data['inn'] ?? null]
                : ['pinfl' => $data['pinfl'] ?? null];

            $contact = Contact::firstOrCreate(
                $key,
                array_merge($data, ['status' => true])
            );

            if (filled($bank['account_number'])) {
                $contact->bankAccounts()->firstOrCreate(
                    ['account_number' => $bank['account_number']],
                    $bank,
                );
            }
        }
    }
}
