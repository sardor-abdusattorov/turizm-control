<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Counterparties (tour agents, suppliers and foreign legal entities) taken from
 * the requisites cards supplied for the exhibition contracts. Trilingual name and
 * address are curated for the long-standing agents and generated for the rest;
 * every bank account on file is attached, one row per currency.
 */
class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [
            // 1. ООО «BEYOND DMC»  (ИНН 311687587)
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
                'phone' => '+998 99 235 48 36; +998 94 092 11 75; 909062456',
                'email' => 'info@beyond-dmc.com',
                'director_name' => 'Раджеш Кумар',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000907160357001', 'currency' => 'UZS', 'bank_name' => 'АТБ «УзсаноатКурилишбанк» ф-л Ракат', 'mfo' => '00440', 'swift' => 'UJSIUZ22', 'bank_address' => null],
                ],
            ],
            // 2. СП «Beyond Expectations»  (ИНН 304415856)
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
                'director_name' => 'О.О. Хакимова',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000200690529001', 'currency' => 'UZS', 'bank_name' => 'в Яккасарайском фил. Давр банк', 'mfo' => '01069', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 3. ООО «ASIA LUXE TRAVEL»  (ИНН 305855245)
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
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000604996690001', 'currency' => 'UZS', 'bank_name' => 'АТIB "IPOTEKA BANK" Юнусабадский Ф-л', 'mfo' => '00837', 'swift' => 'UZHOUZ22', 'bank_address' => null],
                ],
            ],
            // 4. ООО «Rezbook Global International»  (ИНН 311247643)
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
                'phone' => '+998 90 951 46 64; +998 93 324 38 83',
                'email' => 'Uzbekistan@rezbookglobal.com',
                'director_name' => 'Ж.Ф. Сафаров',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000907044121001', 'currency' => 'UZS', 'bank_name' => '«Asia Alliance Bank» городской филиал г. Ташкент', 'mfo' => '00981', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 5. ООО «VERSAILLES TRAVEL»  (ИНН 207153986)
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
                'oked' => '63300',
                'accounts' => [
                    ['account_number' => '20208000604996690001', 'currency' => 'UZS', 'bank_name' => 'в ОАИКБ "Ипак Йули" Яккасарай Ф-л', 'mfo' => '01028', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 6. СП «Sanat Travel Experts»  (ИНН 305072628)
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
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000500802685001', 'currency' => 'UZS', 'bank_name' => 'ЧАБ "TRASTBANK" Филиал Дархан', 'mfo' => '00954', 'swift' => null, 'bank_address' => 'Город Ташкент, Мирзо-Улугбекский район, площадь Хамида Алимджана, 5 Б, западная сторона'],
                ],
            ],
            // 7. ООО «ZAMIN DMC»  (ИНН 311343097)
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
                'phone' => '+998 99 575 50 50; +99899 5750550',
                'email' => 'info@zamindmc.uz',
                'director_name' => 'Ходжаев Т.А.',
                'oked' => '55100',
                'accounts' => [
                    ['account_number' => '20208000107076480001', 'currency' => 'UZS', 'bank_name' => 'АКБ «Капитал банк» городской филиал г. Ташкента', 'mfo' => '00445', 'swift' => null, 'bank_address' => null],
                    ['account_number' => '20208840407076480001', 'currency' => 'USD', 'bank_name' => 'АКБ «Капитал банк» городской филиал г. Ташкента', 'mfo' => '00445', 'swift' => null, 'bank_address' => null],
                    ['account_number' => '20208978807076480001', 'currency' => 'EUR', 'bank_name' => 'АКБ «Капитал банк» городской филиал г. Ташкента', 'mfo' => '00445', 'swift' => null, 'bank_address' => null],
                    ['account_number' => '20208643507076480001', 'currency' => 'RUB', 'bank_name' => 'АКБ «Капитал банк» городской филиал г. Ташкента', 'mfo' => '00445', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 8. ООО «Rocket DMC Registan»  (ИНН 310367653)
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
                'phone' => '90 809 26 01; +998 90 512 24 78',
                'email' => 'rocketuz.all@rocketdmc.com',
                'director_name' => 'Н.М Мухтарова',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000705631732001', 'currency' => 'UZS', 'bank_name' => 'Самаркандское отд, Национального банка ВЭД РУз', 'mfo' => '00450', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 9. ООО «SAMARCANDA ADVENTURES»  (ИНН 311728344)
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
                'director_name' => 'А.К. Муминов',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000607167211001', 'currency' => 'UZS', 'bank_name' => 'ОПЕРУ АИКБ Яшнабадский ф-л КАПИТАЛБАНК', 'mfo' => '01136', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 10. ООО «ZAMIN TRAVEL»  (ИНН 205315190)
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
                'director_name' => 'Усанов. И.Х',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000704368503001', 'currency' => 'UZS', 'bank_name' => 'СО НБ ВЭД Р. Уз', 'mfo' => '00278', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 11. СП ООО «Dolores Travel Services»  (ИНН 205424619)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
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
                'phone' => '(99871) 1208883; +99855 515-88-83; (99875) 5158883; (99878) 1208883; (998 78) 1208873',
                'email' => 'info@dolores.uz',
                'director_name' => 'Байзакова Ш.Б.',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20214000804383781001', 'currency' => 'UZS', 'bank_name' => '«HAMKORBANK» АТБ Ташкент региональный ОБУ', 'mfo' => '00083', 'swift' => null, 'bank_address' => 'Узбекистан, г. Ташкент, ул. Фуркат 14'],
                ],
            ],
            // 12. ООО «GLOBAL EXPLORE»  (ИНН 308716494)
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
                'director_name' => 'И.Н.Курбонов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000405418270001', 'currency' => 'UZS', 'bank_name' => null, 'mfo' => '00401', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 13. ООО «Sonder Voyage»  (ИНН 311240258)
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
                'phone' => '998 90 908 00 82',
                'email' => 'sonderuz@gmail.com',
                'director_name' => 'Д.У.Сарикова',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000107085063001', 'currency' => 'UZS', 'bank_name' => 'ОПЕРУ АИКБ Кибрай т. ЧЭКИ «INVEST FINANCE BANK»', 'mfo' => '01056', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 14. ООО «ADVANTOUR»  (ИНН 204702676)
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
                'director_name' => 'Ф.О. Назарова',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000504288840001', 'currency' => 'UZS', 'bank_name' => 'АО КДБ Банк Узбекистан', 'mfo' => '00842', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 15. ООО «OLIMPIK TURSERVIS»  (ИНН 204511749)
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
                'phone' => '(998 78) 120-63-00; (998 78) 120-64-00; +998977470630',
                'email' => 'info@olympic-ts.com',
                'director_name' => 'В.В. Сильченко',
                'oked' => '79900',
                'accounts' => [
                    ['account_number' => '20208000904262013001', 'currency' => 'UZS', 'bank_name' => 'АКБ «Азия Альянс банк»', 'mfo' => '01095', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 16. ООО «EL MUNDO TOUR»  (ИНН 206752534)
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
                'phone' => '97 707 32 82',
                'email' => null,
                'director_name' => 'Хаитов Т.Ю',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000104537447001', 'currency' => 'UZS', 'bank_name' => 'г.Ташкент., «УЗСАНОАТКУРИЛИШБАНКИ» АКБ Головной филиал', 'mfo' => '00440', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 17. ООО «ORIENT STAR GROUP»  (ИНН 303722254)
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
                'phone' => '+99897-722-22-26; +998974441555',
                'email' => 'welcome@orientstar.uz',
                'director_name' => 'С. Х. Бегматов',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000400565085001', 'currency' => 'UZS', 'bank_name' => 'АКБ «Hamkorbank» Яккасарайский филиал', 'mfo' => '00083', 'swift' => 'KHKKUZ22XXX', 'bank_address' => null],
                    ['account_number' => '20208840900565085002', 'currency' => 'USD', 'bank_name' => 'АКБ "Hamkorbank" Яккасарайский филиал', 'mfo' => '00083', 'swift' => 'KHKKUZ22XXX', 'bank_address' => null],
                    ['account_number' => '20208978200565085002', 'currency' => 'EUR', 'bank_name' => 'АКБ "Hamkorbank" Яккасарайский филиал', 'mfo' => '00083', 'swift' => 'KHKKUZ22XXX', 'bank_address' => null],
                ],
            ],
            // 18. ООО «CAUCASSIA TRAVEL»  (ИНН 310711881)
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
                'director_name' => 'В. Анохин',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000405702929001', 'currency' => 'UZS', 'bank_name' => 'ЧАБ «TRASTBANK» Дарханский ф-л.', 'mfo' => '01061', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 19. ИП ООО «UZTUR INVESTMENT AND DEVELOPMENT»  (ИНН 306777698)
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
                'director_name' => 'Эрик Сейлер',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000905132507009', 'currency' => 'UZS', 'bank_name' => '\'\'IpotekaBank\'\' OTB Group', 'mfo' => '00937', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 20. ООО «SunRoad»  (ИНН 311534832)
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
                'phone' => '909228549',
                'email' => null,
                'director_name' => 'Г. Исакова',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000907123985001', 'currency' => 'UZS', 'bank_name' => '«Капитал» АТБ банк', 'mfo' => '01158', 'swift' => null, 'bank_address' => 'г. Ташкент'],
                ],
            ],
            // 21. ООО «Another Travel»  (ИНН 309484364)
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
                'director_name' => 'М. Тагаева',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000805519373001', 'currency' => 'UZS', 'bank_name' => 'ЧАБ "TRASTBANK" Дарханский ф-л.', 'mfo' => '00954', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 22. АО «UZBEKISTAN AIRWAYS»  (ИНН 306628114)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'АО',
                'name' => [
                    'ru' => 'АО «UZBEKISTAN AIRWAYS»',
                    'uz' => '«UZBEKISTAN AIRWAYS» AJ',
                    'en' => 'UZBEKISTAN AIRWAYS JSC',
                ],
                'inn' => '306628114',
                'address' => [
                    'ru' => 'Республика Узбекистан, г. Ташкент, 100060, проспект Амира Темура 41,',
                    'uz' => 'Республика Узбекистан, г. Ташкент, 100060, проспект Амира Темура 41,',
                    'en' => 'Республика Узбекистан, г. Ташкент, 100060, проспект Амира Темура 41,',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'У.А. Хусанов',
                'oked' => '52230',
                'accounts' => [
                    ['account_number' => '20210000905115307001', 'currency' => 'UZS', 'bank_name' => 'Центральный операционный Центр Банковских услуг АО «Узнацбанка»', 'mfo' => '00450', 'swift' => null, 'bank_address' => '101, улица Амира Темура, Ташкент 100084'],
                    ['account_number' => '20210000305115307009', 'currency' => 'UZS', 'bank_name' => 'ОПЕРУ при ЧАКБ «Ориент Финанс»', 'mfo' => '01071', 'swift' => 'ORFBUZ22', 'bank_address' => 'Узбекистан, 100029, г. Ташкент, Мирзо-Улугбекский район, ул.Осие-5'],
                ],
            ],
            // 23. СП «East Asia Point»  (ИНН 207160718)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'Семейное предприятие',
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
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000306000278001', 'currency' => 'UZS', 'bank_name' => 'Bank Asaka Nurafshan branch (в Асака Банк Нурафшон ф-л)', 'mfo' => '00873', 'swift' => 'ASBKUZ22XXX', 'bank_address' => null],
                    ['account_number' => '20208840605000278001', 'currency' => 'USD', 'bank_name' => 'Bank Asaka Nurafshan branch (в Асака Банк Нурафшон ф-л)', 'mfo' => '00873', 'swift' => 'ASBKUZ22XXX', 'bank_address' => null],
                ],
            ],
            // 24. ООО «ANUR TOUR»  (ИНН 202232646)
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
                'phone' => '+99855 501 22 60; +99871 2302260',
                'email' => 'info@anurtour.com',
                'director_name' => 'М.К. Раубаев',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000201522892001', 'currency' => 'UZS', 'bank_name' => 'г. ТАШКЕНТ, ГОЛОВНОЙ ОФИС АО БАНКА "ИПАК ЙУЛИ"', 'mfo' => '00444', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 25. ООО «KARAVAN TRAVEL»  (ИНН 300832523)
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
                'phone' => '+998 97 910 13 87; +998 97 288 44 00',
                'email' => 'jahongir.kt@gmail.com',
                'director_name' => 'Ж.Х. Санакулов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000504706353001', 'currency' => 'UZS', 'bank_name' => 'Национальный банк внешнеэкономической деятельности Республики Узбекистан, Самаркандский филиал', 'mfo' => '00278', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 26. ООО «MEGA TOUR»  (ИНН 205886850)
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
                'phone' => '+99890-185-33-33',
                'email' => 'megatour@mail.com',
                'director_name' => 'А.В. Хабарова',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000004442549001', 'currency' => 'UZS', 'bank_name' => '"DAVR-BANK" Мирзо-Улугбекский ф-л', 'mfo' => '01072', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 27. ООО «SACRED EAST TRAVEL»  (ИНН 308308202)
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
                'director_name' => 'Н.У.Садыкова',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000605365263001', 'currency' => 'UZS', 'bank_name' => 'ЧАБ «Трастбанк» г.Ташкент', 'mfo' => '00491', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 28. ООО «Uktamxon Tour»  (ИНН 310148514)
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
                'director_name' => 'Махсудов М.У.',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000305603836001', 'currency' => 'UZS', 'bank_name' => 'ХАТБ «Ипак йули банк»', 'mfo' => '00283', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 29. ООО «Selfie Travel»  (ИНН 305299709)
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
                'email' => 'inc@selfietravel.uzb',
                'director_name' => 'Р.М.Мухрумбаев',
                'oked' => '79900',
                'accounts' => [
                    ['account_number' => '20208000200843864001', 'currency' => 'UZS', 'bank_name' => 'ЦОО АКБ "Капиталбанк"', 'mfo' => '01088', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 30. ООО «CENTRAL ASIA TRAVEL»  (ИНН 206976184)
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
                'phone' => '71 20002 99; +99871 2525008; +998 71 252 50 07',
                'email' => 'info@centralasia-travel.com',
                'director_name' => 'Н.А. Сербин',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000304736797001', 'currency' => 'UZS', 'bank_name' => '«АСАКА БАНК» Юнусабадского отд.', 'mfo' => '00873', 'swift' => null, 'bank_address' => 'Узбекистан, Ташкент, Юнус-Абад кв-л 2, дом 8.'],
                ],
            ],
            // 31. ЧП «EMERALD TRAVEL»  (ИНН 206228464)
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
                'phone' => '998905120123',
                'email' => 'info@emerald.uz',
                'director_name' => 'Н. Ботирова',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000104477456001', 'currency' => 'UZS', 'bank_name' => '«Национальный банк» Филиал Академический, город Ташкент', 'mfo' => '00431', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 32. ООО «SEZAM TRAVEL»  (ИНН 301755974)
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
                'oked' => '79900',
                'accounts' => [
                    ['account_number' => '20208000904879519001', 'currency' => 'UZS', 'bank_name' => 'ЧАБ Трастбанк офис ТОШКЕНТ', 'mfo' => '00491', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 33. ЧП «JALOL QUDUQ AVIA TRANS»  (ИНН 302076552)
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
                'email' => 'SAIDA9717@MAIL.RU',
                'director_name' => 'АХМЕДОВА САИДА',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208840104929827005', 'currency' => 'USD', 'bank_name' => 'TRASTBANK', 'mfo' => '01074', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 34. ООО «PEOPLETRAVEL»  (ИНН 207071194)
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
                'phone' => '+998 71 232 23 33',
                'email' => 'info@peopletravel.uz',
                'director_name' => 'Султанов У.А.',
                'oked' => '79900',
                'accounts' => [
                    ['account_number' => '20208000504848824001', 'currency' => 'UZS', 'bank_name' => 'ОПЕРУ АКБ «Asian Alliance Bank»', 'mfo' => '01095', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 35. СП «Antique Travel Experts»  (ИНН 310149060)
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
                'director_name' => 'Б.Р.Ражабов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000505603800001', 'currency' => 'UZS', 'bank_name' => '«Milly bank» BOSH Ofisi AJ', 'mfo' => '00450', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 36. ООО «IMRAN-TOURS»  (ИНН 309963464)
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
                'phone' => '+998977376060; +99897 548 33 33',
                'email' => 'assalom.tour@mail.ru',
                'director_name' => 'Дадаходжаев.С.Ф',
                'oked' => '46900',
                'accounts' => [
                    ['account_number' => '20208000905579066001', 'currency' => 'UZS', 'bank_name' => 'Хамкор Банк Шайхантахур филиали', 'mfo' => '00083', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 37. ООО «Geo Tour Service»  (ИНН 303906847)
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
                'phone' => '+998 71 288 44 48; +998 99 575 5050; +99890 9600150; +99899 7610150',
                'email' => 'info@orientmice.com',
                'director_name' => 'Ш. Пардаев',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000200611475001', 'currency' => 'UZS', 'bank_name' => 'Автотранспортный филиал банка «Асака»', 'mfo' => '01069', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 38. ООО «ZAMIN DESTINATION»  (ИНН 310442497)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ZAMIN DESTINATION»',
                    'uz' => '«ZAMIN DESTINATION» MChJ',
                    'en' => 'ZAMIN DESTINATION LLC',
                ],
                'inn' => '310442497',
                'address' => [
                    'ru' => 'г. Ташкент, Мирабадский район, ул. А.Темура, дом24/2',
                    'uz' => 'г. Ташкент, Мирабадский район, ул. А.Темура, дом24/2',
                    'en' => 'г. Ташкент, Мирабадский район, ул. А.Темура, дом24/2',
                ],
                'phone' => '+998-99-575-50-50',
                'email' => 'inbound@asialuxe.uz',
                'director_name' => 'Т.А.Ходжаев',
                'oked' => '55100',
                'accounts' => [
                    ['account_number' => '20208000605646416001', 'currency' => 'UZS', 'bank_name' => 'АКБ «УзПСБ» Лабзак филиал', 'mfo' => '00440', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 39. ИП ООО «SITARA INTERNATIONAL LTD»  (ИНН 201904269)
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
                'phone' => '+99871 2814148; (+99871) 255-35-04',
                'email' => 'tashkent@sitara.com',
                'director_name' => 'Н.Б. Сангилова',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000300155609001', 'currency' => 'UZS', 'bank_name' => 'SQB "Uzpromstroybank" г. Ташкент', 'mfo' => '00440', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 40. ООО «IZEL»  (ИНН 311884303)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «IZEL»',
                    'uz' => '«IZEL» MChJ',
                    'en' => 'IZEL LLC',
                ],
                'inn' => '311884303',
                'address' => [
                    'ru' => 'Тошкент вилояти.Янгиюл шахар Туркистон МФЙ, Регистон кучаси, 16-дом',
                    'uz' => 'Тошкент вилояти.Янгиюл шахар Туркистон МФЙ, Регистон кучаси, 16-дом',
                    'en' => 'Тошкент вилояти.Янгиюл шахар Туркистон МФЙ, Регистон кучаси, 16-дом',
                ],
                'phone' => '+998 94 092 55 44',
                'email' => 'izelchina@gmail.com',
                'director_name' => 'Е.А Умаралиев',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000307196304001', 'currency' => 'UZS', 'bank_name' => 'ЯНГИЙУЛ Т.. "ИПАК ЙУЛИ" АИТ БАНКИНИНГ ЯНГИЙУЛ ФИЛИАЛИ', 'mfo' => '01081', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 41. ООО «ZIYARAH TRAVEL»  (ИНН 305282002)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ZIYARAH TRAVEL»',
                    'uz' => '«ZIYARAH TRAVEL» MChJ',
                    'en' => 'ZIYARAH TRAVEL LLC',
                ],
                'inn' => '305282002',
                'address' => [
                    'ru' => 'Наманганская область, город Наманган, улица Машраб, Дом-5',
                    'uz' => 'Наманганская область, город Наманган, улица Машраб, Дом-5',
                    'en' => 'Наманганская область, город Наманган, улица Машраб, Дом-5',
                ],
                'phone' => '+998 97 182 82 00',
                'email' => 'info@ziyarah-travel.uz',
                'director_name' => null,
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000500838807001', 'currency' => 'UZS', 'bank_name' => 'Наманганский филиал АКБ «Капиталбанк»', 'mfo' => '01085', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 42. ООО «SkyWay DMC»  (ИНН 311092054)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SkyWay DMC»',
                    'uz' => '«SkyWay DMC» MChJ',
                    'en' => 'SkyWay DMC LLC',
                ],
                'inn' => '311092054',
                'address' => [
                    'ru' => 'Ташкент, Ул. Нукус 86/3',
                    'uz' => 'Ташкент, Ул. Нукус 86/3',
                    'en' => 'Ташкент, Ул. Нукус 86/3',
                ],
                'phone' => '+998500767016',
                'email' => null,
                'director_name' => 'А. А. Леу',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000507006239001', 'currency' => 'UZS', 'bank_name' => '«Капитал банк» ат банкининг «Капитал 24» чакана бизнес филиал', 'mfo' => null, 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 43. WTFI INVESTMENTS L.L.C  (foreign, no ИНН)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => null,
                'name' => [
                    'ru' => 'WTFI INVESTMENTS L.L.C',
                    'uz' => 'WTFI INVESTMENTS L.L.C',
                    'en' => 'WTFI INVESTMENTS L.L.C',
                ],
                'inn' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
                'director_name' => null,
                'oked' => null,
                'accounts' => [
                    ['account_number' => '9878794616', 'currency' => null, 'bank_name' => 'Wio Bank PJSC', 'mfo' => null, 'swift' => 'WIOBAEADXXX', 'bank_address' => 'Etihad Airways Centre 5th Floor, Abu Dhabi, UAE'],
                ],
            ],
            // 44. ООО «TIMURWAY TOUR»  (ИНН 309957606)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «TIMURWAY TOUR»',
                    'uz' => '«TIMURWAY TOUR» MChJ',
                    'en' => 'TIMURWAY TOUR LLC',
                ],
                'inn' => '309957606',
                'address' => [
                    'ru' => 'Узбекистан, Город Ташкент, Мирзо-Улугбексий р-он, Ахмад Югнакий МФЙ, Ахмад Югнакий мавзеси, 19 а-уй',
                    'uz' => 'Узбекистан, Город Ташкент, Мирзо-Улугбексий р-он, Ахмад Югнакий МФЙ, Ахмад Югнакий мавзеси, 19 а-уй',
                    'en' => 'Узбекистан, Город Ташкент, Мирзо-Улугбексий р-он, Ахмад Югнакий МФЙ, Ахмад Югнакий мавзеси, 19 а-уй',
                ],
                'phone' => '+998 90 985 00 95',
                'email' => null,
                'director_name' => 'Салихова Л.У.',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000105579192001', 'currency' => 'UZS', 'bank_name' => 'Мирзо-Улугбекский ф-ал АКБ «ASIA ALLIANCE BANK»', 'mfo' => '01103', 'swift' => 'ASACUZ22', 'bank_address' => 'Tashkent, Uzbekistan'],
                ],
            ],
            // 45. ООО «Sole Vita»  (ИНН 300614889)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Sole Vita»',
                    'uz' => '«Sole Vita» MChJ',
                    'en' => 'Sole Vita LLC',
                ],
                'inn' => '300614889',
                'address' => [
                    'ru' => 'Узбекистан, город Самарканд, улица Мирзо Улугбека, 79а, 140103',
                    'uz' => 'Узбекистан, город Самарканд, улица Мирзо Улугбека, 79а, 140103',
                    'en' => 'Узбекистан, город Самарканд, улица Мирзо Улугбека, 79а, 140103',
                ],
                'phone' => '+998 78 210 0444; +998 93 720 0444',
                'email' => null,
                'director_name' => 'Турдалиева Д.И.',
                'oked' => '55100',
                'accounts' => [
                    ['account_number' => '20208000904688880001', 'currency' => 'UZS', 'bank_name' => 'Микрокредит банк, Самаркандский филиал', 'mfo' => '00281', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 46. ООО «CATO MOTORS»  (ИНН 310109134)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «CATO MOTORS»',
                    'uz' => '«CATO MOTORS» MChJ',
                    'en' => 'CATO MOTORS LLC',
                ],
                'inn' => '310109134',
                'address' => [
                    'ru' => 'г. Самарканд ул. Фирдавсий 3.',
                    'uz' => 'г. Самарканд ул. Фирдавсий 3.',
                    'en' => 'г. Самарканд ул. Фирдавсий 3.',
                ],
                'phone' => '+998(93) 355-11-17',
                'email' => 'azim.travel@gmail.com',
                'director_name' => 'Азимова А.У.',
                'oked' => '96120',
                'accounts' => [
                    ['account_number' => '20208000805599212001', 'currency' => 'UZS', 'bank_name' => 'АКБ Хамкор Банк', 'mfo' => '00083', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 47. ООО «MY-TRIPGUIDE»  (ИНН 309943434)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «MY-TRIPGUIDE»',
                    'uz' => '«MY-TRIPGUIDE» MChJ',
                    'en' => 'MY-TRIPGUIDE LLC',
                ],
                'inn' => '309943434',
                'address' => [
                    'ru' => 'г. Ургенч, Хорезм, Камолот МФЙ,ул. Тонг 1, д.4',
                    'uz' => 'г. Ургенч, Хорезм, Камолот МФЙ,ул. Тонг 1, д.4',
                    'en' => 'г. Ургенч, Хорезм, Камолот МФЙ,ул. Тонг 1, д.4',
                ],
                'phone' => '+998 77 026 77 14',
                'email' => 'info@tripguide.uz',
                'director_name' => 'Б.Р. Удаев',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '2020800090577030001', 'currency' => 'UZS', 'bank_name' => 'Узсаноаткурилишбанк АТБ Хоразм', 'mfo' => '00440', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 48. ООО «FAYZ GRAND MINON»  (ИНН 309570052)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «FAYZ GRAND MINON»',
                    'uz' => '«FAYZ GRAND MINON» MChJ',
                    'en' => 'FAYZ GRAND MINON LLC',
                ],
                'inn' => '309570052',
                'address' => [
                    'ru' => 'г. Ташкент Яккасарой р. Ул. Мукумий 7/1-дом',
                    'uz' => 'г. Ташкент Яккасарой р. Ул. Мукумий 7/1-дом',
                    'en' => 'г. Ташкент Яккасарой р. Ул. Мукумий 7/1-дом',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'Ж.Ф. Сафаров',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000305528854001', 'currency' => 'UZS', 'bank_name' => '"Asia Alliance Bank" AT Banki', 'mfo' => '01095', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 49. ООО «SULTAN TRAVEL UZBEKISTAN»  (ИНН 311367502)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SULTAN TRAVEL UZBEKISTAN»',
                    'uz' => '«SULTAN TRAVEL UZBEKISTAN» MChJ',
                    'en' => 'SULTAN TRAVEL UZBEKISTAN LLC',
                ],
                'inn' => '311367502',
                'address' => [
                    'ru' => 'г.Андижан, ул. Истиклол-9',
                    'uz' => 'г.Андижан, ул. Истиклол-9',
                    'en' => 'г.Андижан, ул. Истиклол-9',
                ],
                'phone' => '93-253-53-54',
                'email' => 'sultan_travel@bk.ru',
                'director_name' => 'Х.Султонов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000807083433001', 'currency' => 'UZS', 'bank_name' => '"Ипак йули банк"', 'mfo' => '01120', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 50. ООО «MIRAN TRIPS»  (ИНН 310635027)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «MIRAN TRIPS»',
                    'uz' => '«MIRAN TRIPS» MChJ',
                    'en' => 'MIRAN TRIPS LLC',
                ],
                'inn' => '310635027',
                'address' => [
                    'ru' => 'г.Ташкент, Шайхантахурский р. Лабзак 30/49',
                    'uz' => 'г.Ташкент, Шайхантахурский р. Лабзак 30/49',
                    'en' => 'г.Ташкент, Шайхантахурский р. Лабзак 30/49',
                ],
                'phone' => '+998 97 036 38 30',
                'email' => 'mirantripsuz@gmail.com',
                'director_name' => 'Б.Н. Туляганов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '8000405672574001', 'currency' => 'UZS', 'bank_name' => null, 'mfo' => '00997', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 51. ООО «ONS Travel»  (ИНН 311530213)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ONS Travel»',
                    'uz' => '«ONS Travel» MChJ',
                    'en' => 'ONS Travel LLC',
                ],
                'inn' => '311530213',
                'address' => [
                    'ru' => 'г.Ташкент, Чиланзарский-2-МФЙ 76-уй',
                    'uz' => 'г.Ташкент, Чиланзарский-2-МФЙ 76-уй',
                    'en' => 'г.Ташкент, Чиланзарский-2-МФЙ 76-уй',
                ],
                'phone' => '+998 91 996 49 49',
                'email' => 'info@onstravel.uz',
                'director_name' => 'Б.Б. Эгамберганов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000607123613001', 'currency' => 'UZS', 'bank_name' => null, 'mfo' => '01071', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 52. ООО «ENJOY TRAVEL»  (ИНН 302881501)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ENJOY TRAVEL»',
                    'uz' => '«ENJOY TRAVEL» MChJ',
                    'en' => 'ENJOY TRAVEL LLC',
                ],
                'inn' => '302881501',
                'address' => [
                    'ru' => '100128, г.Ташкент, Ц-12 д.1, кв-18',
                    'uz' => '100128, г.Ташкент, Ц-12 д.1, кв-18',
                    'en' => '100128, г.Ташкент, Ц-12 д.1, кв-18',
                ],
                'phone' => '+998 71 241 06 49',
                'email' => 'enjoytravel.uz@gmail.com',
                'director_name' => 'С.С. Мухамедов',
                'oked' => '79120',
                'accounts' => [
                    ['account_number' => '20208000500300189001', 'currency' => 'UZS', 'bank_name' => null, 'mfo' => null, 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 53. ООО «AKFA Dream World»  (ИНН 305163498)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «AKFA Dream World»',
                    'uz' => '«AKFA Dream World» MChJ',
                    'en' => 'AKFA Dream World LLC',
                ],
                'inn' => '305163498',
                'address' => [
                    'ru' => '100027, г.Ташкент, ул. Укчи, д.1',
                    'uz' => '100027, г.Ташкент, ул. Укчи, д.1',
                    'en' => '100027, г.Ташкент, ул. Укчи, д.1',
                ],
                'phone' => '+998 71 210 88 88',
                'email' => null,
                'director_name' => 'Ж.К. Абидов',
                'oked' => '55100',
                'accounts' => [
                    ['account_number' => '20208000607123613001', 'currency' => 'UZS', 'bank_name' => null, 'mfo' => '01176', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 54. ООО «Right Flight»  (ИНН 311043178)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Right Flight»',
                    'uz' => '«Right Flight» MChJ',
                    'en' => 'Right Flight LLC',
                ],
                'inn' => '311043178',
                'address' => [
                    'ru' => 'г. Ташкент, Шайхантахурский район, ул. Кичик Халка юли/9,',
                    'uz' => 'г. Ташкент, Шайхантахурский район, ул. Кичик Халка юли/9,',
                    'en' => 'г. Ташкент, Шайхантахурский район, ул. Кичик Халка юли/9,',
                ],
                'phone' => '+998900882222',
                'email' => 'info@right-flight.uz',
                'director_name' => 'Ким Анжела',
                'oked' => '52230',
                'accounts' => [
                    ['account_number' => '20208000605740548001', 'currency' => 'UZS', 'bank_name' => 'ЧАБ "Капиталбанк" ф-л. Города Ташкента', 'mfo' => '00445', 'swift' => 'KACHUZ22', 'bank_address' => null],
                ],
            ],
            // 55. ООО «EAST-STAR HOTEL»  (ИНН 309748074)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «EAST-STAR HOTEL»',
                    'uz' => '«EAST-STAR HOTEL» MChJ',
                    'en' => 'EAST-STAR HOTEL LLC',
                ],
                'inn' => '309748074',
                'address' => [
                    'ru' => '140100, Узбекистан, г. Самарканд, ул. Мироншох, 34.',
                    'uz' => '140100, Узбекистан, г. Самарканд, ул. Мироншох, 34.',
                    'en' => '140100, Узбекистан, г. Самарканд, ул. Мироншох, 34.',
                ],
                'phone' => '(998 66) 233 77 77',
                'email' => null,
                'director_name' => 'Кулиев М.И.',
                'oked' => '55100',
                'accounts' => [
                    ['account_number' => '20208000205552380001', 'currency' => 'UZS', 'bank_name' => 'ORIENT FINANS BANK, Самарканд филиал', 'mfo' => '01071', 'swift' => 'ORFBUZ22', 'bank_address' => 'г. Самарканд, ул. Мирзо Улугбек, 48'],
                    ['account_number' => '20208840505552380001', 'currency' => 'USD', 'bank_name' => 'ORIENT FINANS BANK, Самарканд филиал', 'mfo' => '01071', 'swift' => 'ORFBUZ22', 'bank_address' => 'г. Самарканд, ул. Мирзо Улугбек, 48'],
                ],
            ],
            // 56. ООО «MY FREIGHTER»  (ИНН 306985993)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «MY FREIGHTER»',
                    'uz' => '«MY FREIGHTER» MChJ',
                    'en' => 'MY FREIGHTER LLC',
                ],
                'inn' => '306985993',
                'address' => [
                    'ru' => 'ул. Буюк Ипак Йули, 262, Мирзо Улугбекский район, Ташкент, Узбекистан, 100187.',
                    'uz' => 'ул. Буюк Ипак Йули, 262, Мирзо Улугбекский район, Ташкент, Узбекистан, 100187.',
                    'en' => 'ул. Буюк Ипак Йули, 262, Мирзо Улугбекский район, Ташкент, Узбекистан, 100187.',
                ],
                'phone' => '+99891-030-82-21',
                'email' => 'a.osintsev@centrum-air.com',
                'director_name' => 'А.А.Абдурахманов',
                'oked' => '51210',
                'accounts' => [
                    ['account_number' => '20208000805182960005', 'currency' => 'UZS', 'bank_name' => 'АО «KDB BANK UZBEKISTAN»', 'mfo' => '00842', 'swift' => null, 'bank_address' => 'ул. Бухара, 3, Ташкент, Узбекистан 100047'],
                ],
            ],
            // 57. ООО «INTURIZM»  (ИНН 311153864)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «INTURIZM»',
                    'uz' => '«INTURIZM» MChJ',
                    'en' => 'INTURIZM LLC',
                ],
                'inn' => '311153864',
                'address' => [
                    'ru' => 'г.Ташкент Яккасарайский район, Хамидсулаймон МФУ, ул. Кичик Бешогоч, 70 дом, 24 кв.',
                    'uz' => 'г.Ташкент Яккасарайский район, Хамидсулаймон МФУ, ул. Кичик Бешогоч, 70 дом, 24 кв.',
                    'en' => 'г.Ташкент Яккасарайский район, Хамидсулаймон МФУ, ул. Кичик Бешогоч, 70 дом, 24 кв.',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => '?ой С.И.',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000707023815001', 'currency' => 'UZS', 'bank_name' => 'ТГФ АКБ «Капитал Банк»', 'mfo' => '01158', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 58. ООО «TRAVEL RENTCAR»  (ИНН 308291281)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «TRAVEL RENTCAR»',
                    'uz' => '«TRAVEL RENTCAR» MChJ',
                    'en' => 'TRAVEL RENTCAR LLC',
                ],
                'inn' => '308291281',
                'address' => [
                    'ru' => 'г.Самарканд, ул. Б.И.Йули, 99/34.',
                    'uz' => 'г.Самарканд, ул. Б.И.Йули, 99/34.',
                    'en' => 'г.Самарканд, ул. Б.И.Йули, 99/34.',
                ],
                'phone' => '+99893-331-31-13',
                'email' => null,
                'director_name' => 'А.Х. Ахатов',
                'oked' => '77110',
                'accounts' => [
                    ['account_number' => '20208000405369978001', 'currency' => 'UZS', 'bank_name' => 'Самаркандский областной филиал Invest Finance Bank', 'mfo' => '01133', 'swift' => null, 'bank_address' => 'г.Самарканд, ул. Узбекистанская, 22'],
                ],
            ],
            // 59. ООО «NEGEN-TOUR»  (ИНН 309292350)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «NEGEN-TOUR»',
                    'uz' => '«NEGEN-TOUR» MChJ',
                    'en' => 'NEGEN-TOUR LLC',
                ],
                'inn' => '309292350',
                'address' => [
                    'ru' => 'г. Самарканд, ул. Фирдавсий, 35',
                    'uz' => 'г. Самарканд, ул. Фирдавсий, 35',
                    'en' => 'г. Самарканд, ул. Фирдавсий, 35',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'Рустамова П.Ж.',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000505492956001', 'currency' => 'UZS', 'bank_name' => 'АТИБ «Ипотека Банк» Филиал Кук-Сарой', 'mfo' => '00262', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 60. ООО «MODERNTRAVEL»  (ИНН 312001822)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «MODERNTRAVEL»',
                    'uz' => '«MODERNTRAVEL» MChJ',
                    'en' => 'MODERNTRAVEL LLC',
                ],
                'inn' => '312001822',
                'address' => [
                    'ru' => 'Г.Ташкент Юнксабадский р-н ул. Беназир, 12 дом',
                    'uz' => 'Г.Ташкент Юнксабадский р-н ул. Беназир, 12 дом',
                    'en' => 'Г.Ташкент Юнксабадский р-н ул. Беназир, 12 дом',
                ],
                'phone' => '(99) 988 77 74',
                'email' => null,
                'director_name' => 'А. Рахимжонов',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000907217582001', 'currency' => 'UZS', 'bank_name' => 'Шайхантахурский филиал АКБ «ASIA ALLIANCE BANK»', 'mfo' => '01095', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 61. ООО «TASHKENT TRAVEL HUB»  (ИНН 311997415)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «TASHKENT TRAVEL HUB»',
                    'uz' => '«TASHKENT TRAVEL HUB» MChJ',
                    'en' => 'TASHKENT TRAVEL HUB LLC',
                ],
                'inn' => '311997415',
                'address' => [
                    'ru' => 'Республика Узбекистан, г Ташкент, Укчи-Олмазор МФЙ, Фаргона йули кучаси, 44-уй, 21-хонадон',
                    'uz' => 'Республика Узбекистан, г Ташкент, Укчи-Олмазор МФЙ, Фаргона йули кучаси, 44-уй, 21-хонадон',
                    'en' => 'Республика Узбекистан, г Ташкент, Укчи-Олмазор МФЙ, Фаргона йули кучаси, 44-уй, 21-хонадон',
                ],
                'phone' => '+998 95 289-60-66',
                'email' => null,
                'director_name' => 'О.Ю. Юсуфов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000907216618001', 'currency' => 'UZS', 'bank_name' => '"ОРИЕНТ ФИНАНС" ХАТ БАНКИ', 'mfo' => '01071', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 62. ООО «Inter MICE Asia»  (ИНН 303380705)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Inter MICE Asia»',
                    'uz' => '«Inter MICE Asia» MChJ',
                    'en' => 'Inter MICE Asia LLC',
                ],
                'inn' => '303380705',
                'address' => [
                    'ru' => 'Узбекистан, г. Самарканд 140100, ул. Узбекистанская 116Г',
                    'uz' => 'Узбекистан, г. Самарканд 140100, ул. Узбекистанская 116Г',
                    'en' => 'Узбекистан, г. Самарканд 140100, ул. Узбекистанская 116Г',
                ],
                'phone' => '+998902714429',
                'email' => 'info@mice-uzbekistan.uz',
                'director_name' => 'Агзамова Ш.',
                'oked' => '79110',
                'accounts' => [
                    ['account_number' => '20208000600478554001', 'currency' => 'UZS', 'bank_name' => 'Самаркандский филиал ЧАБ «Трастбанк»', 'mfo' => '00491', 'swift' => null, 'bank_address' => 'Республика Узбекистан, г. Самарканд, ул. М. Улугбека 47А'],
                ],
            ],
            // 63. ООО «Sapphire Asia»  (ИНН 302588595)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Sapphire Asia»',
                    'uz' => '«Sapphire Asia» MChJ',
                    'en' => 'Sapphire Asia LLC',
                ],
                'inn' => '302588595',
                'address' => [
                    'ru' => 'г. Самарканд, ул. Рудаки 179',
                    'uz' => 'г. Самарканд, ул. Рудаки 179',
                    'en' => 'г. Самарканд, ул. Рудаки 179',
                ],
                'phone' => '+998972880088',
                'email' => 'info@sapphireasiatours.com',
                'director_name' => 'Х. Б. Шайманов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '22614840100217421001', 'currency' => 'USD', 'bank_name' => 'г. Самарканд, «Агробанк» АТБ региональный филиал г. Самарканд', 'mfo' => '00279', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 64. ООО «HOLIDAYTRAVEL»  (ИНН 308945946)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «HOLIDAYTRAVEL»',
                    'uz' => '«HOLIDAYTRAVEL» MChJ',
                    'en' => 'HOLIDAYTRAVEL LLC',
                ],
                'inn' => '308945946',
                'address' => [
                    'ru' => 'Узбекистон Овози 21, г. Ташкент',
                    'uz' => 'Узбекистон Овози 21, г. Ташкент',
                    'en' => 'Узбекистон Овози 21, г. Ташкент',
                ],
                'phone' => '+998972621661',
                'email' => 'info@uzholiday.com',
                'director_name' => 'З.Х. Бабаева',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000005447829001', 'currency' => 'UZS', 'bank_name' => 'Капитал Банк, АКБ, г. Ташкент', 'mfo' => '01088', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 65. ООО «Samarkanda Travel and Tours»  (ИНН 206930939)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Samarkanda Travel and Tours»',
                    'uz' => '«Samarkanda Travel and Tours» MChJ',
                    'en' => 'Samarkanda Travel and Tours LLC',
                ],
                'inn' => '206930939',
                'address' => [
                    'ru' => '100100, г.Ташкент, ул. Коракум 2, дом 19',
                    'uz' => '100100, г.Ташкент, ул. Коракум 2, дом 19',
                    'en' => '100100, г.Ташкент, ул. Коракум 2, дом 19',
                ],
                'phone' => '+998977650105',
                'email' => 'aziza@samarkanda-travel.com',
                'director_name' => 'К.А. ?',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000404644732001', 'currency' => 'UZS', 'bank_name' => 'АТБ «Asia Alliance Bank»', 'mfo' => '01095', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 66. ООО «SARBON TOURS»  (ИНН 204130068)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «SARBON TOURS»',
                    'uz' => '«SARBON TOURS» MChJ',
                    'en' => 'SARBON TOURS LLC',
                ],
                'inn' => '204130068',
                'address' => [
                    'ru' => 'Тошкент ш. 100060, Шахрисабз кўчаси 38/3',
                    'uz' => 'Тошкент ш. 100060, Шахрисабз кўчаси 38/3',
                    'en' => 'Тошкент ш. 100060, Шахрисабз кўчаси 38/3',
                ],
                'phone' => '+998 78-147-04-77',
                'email' => 'sarbontour@gmail.com',
                'director_name' => 'Н.Р.Маматкулов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000004203222001', 'currency' => 'UZS', 'bank_name' => 'ГОО НБ ВЭД РУз', 'mfo' => '00450', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 67. ООО «TOUR EAST»  (ИНН 306151418)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «TOUR EAST»',
                    'uz' => '«TOUR EAST» MChJ',
                    'en' => 'TOUR EAST LLC',
                ],
                'inn' => '306151418',
                'address' => [
                    'ru' => 'г.Бухара ул.Мустакиллик 33/31',
                    'uz' => 'г.Бухара ул.Мустакиллик 33/31',
                    'en' => 'г.Бухара ул.Мустакиллик 33/31',
                ],
                'phone' => '+998997778020',
                'email' => 'toureastorg@yandex.com',
                'director_name' => 'В.Б. Розикович',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000501030239001', 'currency' => 'UZS', 'bank_name' => 'АНДИЖОН Ш., ЧЕТ ЭЛ КАПИТАЛИ ИШТИРОКИДАГИ "HAMKORBANK" АТ БАНКИНИНГ БОШ ОФИСИ', 'mfo' => '00083', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 68. ООО «AFSONA TRAVEL»  (ИНН 202822781)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «AFSONA TRAVEL»',
                    'uz' => '«AFSONA TRAVEL» MChJ',
                    'en' => 'AFSONA TRAVEL LLC',
                ],
                'inn' => '202822781',
                'address' => [
                    'ru' => '140100 Самарканд, массив Конигил',
                    'uz' => '140100 Самарканд, массив Конигил',
                    'en' => '140100 Самарканд, массив Конигил',
                ],
                'phone' => '+998 90 809 26 01',
                'email' => null,
                'director_name' => 'Сайдаминова Ш.Х.',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000903991804001', 'currency' => 'UZS', 'bank_name' => 'Уз НацБанк ВЭД', 'mfo' => '00450', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 69. ООО «TRAVEL ISTAN»  (ИНН 311123576)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «TRAVEL ISTAN»',
                    'uz' => '«TRAVEL ISTAN» MChJ',
                    'en' => 'TRAVEL ISTAN LLC',
                ],
                'inn' => '311123576',
                'address' => [
                    'ru' => 'ул. Ахмад Дониш 62, Ташкент, Узбекистан, 100170',
                    'uz' => 'ул. Ахмад Дониш 62, Ташкент, Узбекистан, 100170',
                    'en' => 'ул. Ахмад Дониш 62, Ташкент, Узбекистан, 100170',
                ],
                'phone' => '+998909060052',
                'email' => 'sanjar.azizov@travelistan.uz',
                'director_name' => 'С.Я. Азизов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000407012567001', 'currency' => 'UZS', 'bank_name' => 'АТ «Aloqabank»', 'mfo' => '00401', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 70. ООО «Ariana Tours»  (ИНН 304790036)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Ariana Tours»',
                    'uz' => '«Ariana Tours» MChJ',
                    'en' => 'Ariana Tours LLC',
                ],
                'inn' => '304790036',
                'address' => [
                    'ru' => 'Шота Руставели 4А/1, Самарканд 140100 Узбекистан',
                    'uz' => 'Шота Руставели 4А/1, Самарканд 140100 Узбекистан',
                    'en' => 'Шота Руставели 4А/1, Самарканд 140100 Узбекистан',
                ],
                'phone' => '+99897-9183055',
                'email' => 'info@arianatours.uz',
                'director_name' => 'Шаднева Г.Э.',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000000837410001', 'currency' => 'UZS', 'bank_name' => 'Самаркандский филиал ЧАБ «Трастбанк»', 'mfo' => '11977', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 71. ООО «ORIENT VOYAGES»  (ИНН 202960778)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «ORIENT VOYAGES»',
                    'uz' => '«ORIENT VOYAGES» MChJ',
                    'en' => 'ORIENT VOYAGES LLC',
                ],
                'inn' => '202960778',
                'address' => [
                    'ru' => '140120 г.Самарканд ул.Дагбитская 33',
                    'uz' => '140120 г.Самарканд ул.Дагбитская 33',
                    'en' => '140120 г.Самарканд ул.Дагбитская 33',
                ],
                'phone' => null,
                'email' => null,
                'director_name' => 'М. Сайдаминов',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000204005764001', 'currency' => 'UZS', 'bank_name' => 'ЧАКБ «ORIENT FINANS BANK» Самарканд ЦБУ', 'mfo' => '01071', 'swift' => null, 'bank_address' => null],
                ],
            ],
            // 72. ООО «Akbar Travel Asia»  (ИНН 306620600)
            [
                'type' => Contact::TYPE_LEGAL,
                'legal_form' => 'ООО',
                'name' => [
                    'ru' => 'ООО «Akbar Travel Asia»',
                    'uz' => '«Akbar Travel Asia» MChJ',
                    'en' => 'Akbar Travel Asia LLC',
                ],
                'inn' => '306620600',
                'address' => [
                    'ru' => '220100, 62/3, улица Ханкинская, город Ургенч, Узбекистан',
                    'uz' => '220100, 62/3, улица Ханкинская, город Ургенч, Узбекистан',
                    'en' => '220100, 62/3, улица Ханкинская, город Ургенч, Узбекистан',
                ],
                'phone' => null,
                'email' => 'info@akbartraveluz.com',
                'director_name' => 'Матчанов Б.Ю',
                'oked' => null,
                'accounts' => [
                    ['account_number' => '20208000405107718001', 'currency' => 'UZS', 'bank_name' => 'Головной офис АО Асакабанк', 'mfo' => '00873', 'swift' => null, 'bank_address' => null],
                ],
            ],
        ];

        $currencies = Currency::query()->pluck('id', 'short_name');

        foreach ($contacts as $data) {
            $accounts = $data['accounts'] ?? [];
            unset($data['accounts']);

            // Legal entities are unique by INN; the one foreign entity with no INN
            // is keyed by its Russian name instead.
            $contact = filled($data['inn'] ?? null)
                ? Contact::firstOrCreate(['inn' => $data['inn']], array_merge($data, ['status' => true]))
                : Contact::where('name->ru', $data['name']['ru'])->first()
                    ?? Contact::create(array_merge($data, ['status' => true]));

            foreach (array_values($accounts) as $sort => $acc) {
                $contact->bankAccounts()->firstOrCreate(
                    ['account_number' => $acc['account_number']],
                    [
                        'currency_id' => $acc['currency'] ? $currencies->get($acc['currency']) : null,
                        'bank_name' => $acc['bank_name'],
                        'bank_address' => $acc['bank_address'],
                        'mfo' => $acc['mfo'],
                        'swift' => $acc['swift'],
                        'sort' => $sort,
                    ],
                );
            }
        }
    }
}
