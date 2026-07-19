<?php

namespace Database\Seeders;

use App\Enums\ContractAttachmentType;
use App\Enums\ProjectType;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The real 2025 paper trail, transcribed from the scanned dossiers in
 * documents/: 13 committee buyruqs, 21 foreign contractors and the 26
 * expense contracts (space rental / stand construction / services / agency)
 * they signed — amounts, currencies and dates exactly as in the packages.
 * Dossier PDFs are attached and buyruq scans copied outside of tests only,
 * so the suite never shuffles ~350 MB of scans around.
 */
class RealDossiers2025Seeder extends Seeder
{
    public function run(): void
    {
        $responsible = User::firstWhere('email', 'manager@test.uz') ?? User::first();

        if (! $responsible) {
            $this->command?->warn('RealDossiers2025Seeder skipped: no users found.');

            return;
        }

        $orders = $this->seedOrders();
        $contacts = $this->seedContractors();
        $this->seedContracts($orders, $contacts, $responsible);
    }

    /**
     * Committee buyruqs (приказы-основания). The annual 74-АФ and 110-АФ have
     * no standalone scan — only copies stitched into the dossiers — so their
     * file stays empty.
     *
     * @return array<string, Order>
     */
    protected function seedOrders(): array
    {
        $rows = [
            ['06-АФ', '2025-01-06', 'Об участии в выставке «FITUR 2025» (Мадрид)', 'documents/Fitur/06-АФ Фитур.pdf'],
            ['34-АФ', '2025-02-07', 'Об участии в выставке «ITB Berlin 2025»', 'documents/ITB berlin/34-AF Берлин.pdf'],
            ['39-АФ', '2025-02-10', 'Об участии в выставке «SATTE 2025» (Нью-Дели)', 'documents/SATTE/39-AF Сатте.pdf'],
            ['73-АФ', '2025-03-13', 'Об участии в выставке «MITT 2025» (Москва)', 'documents/MITT/73-AF МИТТ.pdf'],
            ['74-АФ', '2025-03-13', 'Об участии с национальным стендом Узбекистана на международных туристических ярмарках 2025 года', null],
            ['110-АФ', '2025-04-18', 'Об участии в «SCITE Sichuan 2025» и роудшоу в Чунцине и Чанше', null],
            ['119-АФ', '2025-04-23', 'Об участии в выставке «ATM 2025» (Дубай)', 'documents/ATM/119-AF Дубай.pdf'],
            ['142-АФ', '2025-05-12', 'Об участии в выставке «GITF 2025» (Гуанчжоу) и роудшоу', 'documents/GITF/142-AF Гуанджоу.pdf'],
            ['166-АФ', '2025-05-27', 'Об участии в выставке «SITF 2025» (Сеул)', 'documents/SITF/166-AF SITF Корея.pdf'],
            ['292-АФ', '2025-08-26', 'Об участии в выставке «IFTM Top Resa 2025» (Париж)', 'documents/IFTM top resa/292-AF Top Resa.pdf'],
            ['301-АФ', '2025-08-28', 'Об участии в выставке «MATTA Fair 2025» (Куала-Лумпур)', 'documents/MATTA fair/301-AF МАТТА.pdf'],
            ['306-АФ', '2025-09-03', 'Об участии в выставке «TT Warsaw 2025» (Варшава)', 'documents/TT warsaw/306-AF Польша.pdf'],
            ['359-АФ', '2025-10-21', 'Об участии в выставке «World Travel Market 2025» (Лондон)', 'documents/world travel market/359-AF Лондон WTM.pdf'],
        ];

        $creator = User::firstWhere('email', 'manager@test.uz') ?? User::first();
        $orders = [];

        foreach ($rows as [$number, $issuedAt, $title, $sourcePath]) {
            $order = Order::firstOrCreate(
                ['number' => $number],
                [
                    'scope' => 'external',
                    'title' => $title,
                    'description' => 'Приказ Комитета по туризму при Министерстве экологии, охраны окружающей среды и изменения климата РУз.',
                    'file_path' => null,
                    'issued_at' => $issuedAt,
                    'status' => true,
                    'created_by' => $creator->id,
                ]
            );

            if ($sourcePath && ! $order->file_path && ! app()->runningUnitTests()) {
                $order->update(['file_path' => $this->copyFile($sourcePath, 'uploads/files/orders/'.$order->id)]);
            }

            $orders[$number] = $order;
        }

        return $orders;
    }

    /**
     * The foreign organisers, stand builders and intermediaries the centre
     * actually signed with in 2025, straight from the contract requisites.
     *
     * @return array<string, Contact>
     */
    protected function seedContractors(): array
    {
        $rows = [
            ['IFEMA Madrid', 'Avenida del Partenón 5, Madrid, Spain', 'Организатор выставки FITUR'],
            ['Stand Up Arquitectura Efimera SL', 'Madrid, Spain', 'Застройщик выставочных стендов'],
            ['Think Strawberries MENA LLC', 'Churchill Executive Tower, Business Bay, Dubai, UAE', 'DMC-посредник: аренда площадей и застройка стендов'],
            ['DASC Exhibitions & Events LLC', 'DIFC, Sheikh Zayed Rd, Dubai, UAE', 'Застройщик выставочных стендов'],
            ['BlinkBrand Solutions Private Limited', 'Saket, New Delhi, India', 'Организатор-партнёр выставки SATTE'],
            ['SAYS International LLC', 'ул. Новослободская 20, Москва, Россия', 'Застройщик выставочных стендов'],
            ['ITE Eurasian Exhibitions FZ-LLC', 'Dubai, UAE', 'Продажа выставочных площадей (MITT)'],
            ['TKS Exhibition Services Ltd', 'Hong Kong, China', 'Продажа выставочных площадей (ITE Hong Kong)'],
            ['Urumqi Rubber Tree Trade Events Co., Ltd', 'Urumqi, Xinjiang, China', 'Застройщик выставочных стендов'],
            ['Silk Road Hesheng (Beijing) International Trading Co., Ltd', 'Miyun District, Beijing, China', 'Услуги по организации роудшоу'],
            ['Shaanxi Feijing International Travel Agency Co., Ltd', "Xi'an, Shaanxi, China", 'Услуги по организации роудшоу'],
            ['Hannover Milano Fairs Shanghai Ltd (Guangzhou Branch)', 'Guangzhou, China', 'Организатор выставки GITF'],
            ['Shanghai Marking Exhibition Service Limited', 'Shanghai, China', 'Застройщик выставочных стендов'],
            ['KOTFA Co., Ltd', 'Myeongdong, Seoul, Republic of Korea', 'Организатор выставки SITF'],
            ['EVERBLOOM PROMO', 'Lucky Centre, Wan Chai, Hong Kong', 'Застройщик выставочных стендов'],
            ['MICEM SDN BHD', 'Kuala Lumpur, Malaysia', 'Организатор выставки MATTA Fair'],
            ['DESSO Mimarlik', 'Beylikdüzü, Istanbul, Türkiye', 'Застройщик выставочных стендов'],
            ['RX France S.A.S.', '52-54 Quai de Dion Bouton, Puteaux, France', 'Организатор выставки IFTM Top Resa'],
            ['Japan Association of Travel Agents (JATA)', 'Tokyo, Japan', 'Организатор выставки Tourism EXPO Japan'],
            ['PTAK Warsaw Expo Sp. z o.o.', 'Al. Katowicka 62, Nadarzyn, Poland', 'Организатор выставки TT Warsaw'],
            ['INSIGHT EXP FZE', 'One Central, Dubai World Trade Centre, UAE', 'Застройщик выставочных стендов'],
        ];

        $contacts = [];

        foreach ($rows as [$name, $address, $note]) {
            if (! Contact::query()->where('name->ru', $name)->exists()) {
                // $note documents the row for the reader; contacts carry no
                // free-text column, the role is visible from the contracts.
                Contact::create([
                    'type' => Contact::TYPE_LEGAL,
                    'name' => ['ru' => $name, 'uz' => $name, 'en' => $name],
                    'address' => ['ru' => $address, 'uz' => $address, 'en' => $address],
                    'status' => true,
                ]);
            }

            $contacts[$name] = Contact::query()->where('name->ru', $name)->first();
        }

        return $contacts;
    }

    /**
     * @param  array<string, Order>  $orders
     * @param  array<string, Contact>  $contacts
     */
    protected function seedContracts(array $orders, array $contacts, User $responsible): void
    {
        $kind = fn (string $ru) => ContractType::firstWhere('title->ru', $ru);
        $currency = fn (string $code) => Currency::firstWhere('short_name', $code);

        $rental = $kind('Аренда площади');
        $stand = $kind('Застройка стенда');
        $services = $kind('Услуги подрядчика');
        $agency = $kind('Агентские услуги');

        if (! $rental || ! $stand) {
            $this->command?->warn('RealDossiers2025Seeder skipped contracts: run ContractTypeSeeder first.');

            return;
        }

        // number · signed · contractor · kind · amount · currency · buyruq ·
        // project match · subject · dossier scan
        $rows = [
            ['2/FITUR 2025 Space', '2025-02-17', 'IFEMA Madrid', $rental, 16595.41, 'EUR', '06-АФ', 'FITUR', 'Аренда необорудованной выставочной площади 76,5 кв.м', 'documents/Fitur/+207127843204799073250200016 IFEMA MADRID 06-AF.pdf'],
            ['1 FITUR 2025', '2025-01-09', 'Stand Up Arquitectura Efimera SL', $stand, 47890.00, 'EUR', '06-АФ', 'FITUR', 'Застройка выставочного стенда 76,5 кв.м (9×8,5 м)', 'documents/Fitur/+207127843204799073250200001_Stand_Up_Arquitectura_Efimera_06_AF.pdf'],
            ['2/ITB Berlin 2025', '2025-02-07', 'DASC Exhibitions & Events LLC', $stand, 123000.00, 'USD', '34-АФ', 'ITB', 'Застройка выставочного стенда 162 кв.м', 'documents/ITB berlin/+207127843204799073250200013_DASC_EXHIBITIONS_AND_EVENTS_34_AF.pdf'],
            ['20/08', '2025-08-20', 'Think Strawberries MENA LLC', $agency ?? $rental, 55000.00, 'EUR', '34-АФ', 'ITB', 'Содействие в расчётах: аренда площади 162 кв.м (транзит Messe Berlin)', 'documents/ITB berlin/+207127843204799073250200102 THINK STRAWBERRIES 34-AF.pdf'],
            ['1/SATTE 2025', '2025-02-12', 'BlinkBrand Solutions Private Limited', $rental, 34381.19, 'USD', '39-АФ', 'SATTE', 'Аренда необорудованной выставочной площади 54 кв.м', 'documents/SATTE/+207127843204799073250200011_BLINKBRAND_SOLUTIONS_PRIVATE_LIMITED.pdf'],
            ['2/SATTE 2025', '2025-02-12', 'BlinkBrand Solutions Private Limited', $stand, 29074.00, 'USD', '39-АФ', 'SATTE', 'Застройка выставочного стенда 54 кв.м', 'documents/SATTE/+207127843204799073250200012_BLINKBRAND_SOLUTIONS_PRIVATE_LIMITED.pdf'],
            ['340', '2025-03-14', 'SAYS International LLC', $stand, 75000.00, 'USD', '74-АФ', 'MITT', 'Монтаж и демонтаж выставочного стенда 100,5 кв.м', 'documents/MITT/+207127843204799073250200026 SAYS INTERNATIONAL 74-AF.pdf'],
            ['Space/14-03', '2025-03-14', 'ITE Eurasian Exhibitions FZ-LLC', $rental, 86720.32, 'USD', '74-АФ', 'MITT', 'Аренда необорудованной выставочной площади 100,5 кв.м', 'documents/ITE hongkong/+207127843204799073250200028 ITE EURASIAN EXHIBITIONS 74-AF.pdf'],
            ['АТМ/1', '2025-04-11', 'Think Strawberries MENA LLC', $stand, 52705.80, 'USD', '74-АФ', 'ATM', 'Готовый выставочный стенд 66 кв.м (11×6)', 'documents/ATM/+207127843204799073250200038 THINK STRAWBERRIES 74-AF.pdf'],
            ['24/1', '2025-04-24', 'Think Strawberries MENA LLC', $rental, 84000.00, 'USD', '74-АФ', 'ATM', 'Аренда необорудованной выставочной площади 66 кв.м', 'documents/ATM/+207127843204799073250200044 THINK STRAWBERRIES 74-AF.pdf'],
            ['15/1', '2025-04-15', 'Urumqi Rubber Tree Trade Events Co., Ltd', $stand, 25000.00, 'USD', '74-АФ', 'SCITE', 'Готовый выставочный стенд 60 кв.м (10×6)', 'documents/SCITE/+207127843204799073250200042 URUMQI RUBBER TREE TRADE 74-AF.pdf'],
            ['24/4', '2025-04-24', 'Silk Road Hesheng (Beijing) International Trading Co., Ltd', $services ?? $rental, 19172.92, 'USD', '110-АФ', 'SCITE', 'Роудшоу в Чунцине: площадка 670 кв.м, оборудование, транспорт', 'documents/SCITE/+207127843204799073250200043_SILK_ROAD_HESHENG_INTERNATIONAL_TRADING.pdf'],
            ['25/4', '2025-04-25', 'Shaanxi Feijing International Travel Agency Co., Ltd', $services ?? $rental, 18793.80, 'USD', '110-АФ', 'SCITE', 'Роудшоу в Чанше: площадка 300 кв.м, оборудование, транспорт', 'documents/SCITE/+207127843204799073250200046_SHAANXI_FEIJING_INTERNATIONAL_110_AF.pdf'],
            ['G-1', '2025-04-25', 'Hannover Milano Fairs Shanghai Ltd (Guangzhou Branch)', $rental, 34210.72, 'USD', '74-АФ', 'GITF', 'Аренда необорудованной выставочной площади 70 кв.м', 'documents/GITF/+207127843204799073250200045_HANNOVER_MILANO_FAIRS_SHANGHAI_74_AF.pdf'],
            ['13/05', '2025-05-05', 'Shanghai Marking Exhibition Service Limited', $stand, 18368.00, 'USD', '74-АФ', 'GITF', 'Готовый выставочный стенд 70 кв.м', 'documents/GITF/+207127843204799073250200051 GITF 2025 STEND 74-AF.pdf'],
            ['26/5/1', '2025-05-26', 'KOTFA Co., Ltd', $rental, 25400.00, 'USD', '74-АФ', 'SITF', 'Аренда необорудованной выставочной площади 72 кв.м', 'documents/SITF/+207127843204799073250200058 KOTFA CO LTD - 74-AF.pdf'],
            ['S-19', '2025-05-22', 'EVERBLOOM PROMO', $stand, 50950.00, 'USD', '74-АФ', 'SITF', 'Застройка выставочного стенда (COEX, Сеул)', 'documents/SITF/+207127843204799073250200062 EVERBLOOM 74-AF.pdf'],
            ['28/1', '2025-03-28', 'TKS Exhibition Services Ltd', $rental, 28842.00, 'USD', '74-АФ', 'ITE Hong', 'Аренда выставочной площади 60 кв.м («остров»)', 'documents/ITE hongkong/+207127843204799073250200029 HONGKONG YER 74-AF.pdf'],
            ['01-01', '2025-08-11', 'MICEM SDN BHD', $rental, 7478.66, 'USD', '74-АФ', 'Matta', 'Аренда выставочной площади 72 кв.м (8 бут-юнитов)', 'documents/MATTA fair/+207127843204799073250200095 MICEM SDN BHD.pdf'],
            ['29/8/2', '2025-08-29', 'DESSO Mimarlik', $stand, 37900.00, 'USD', '74-АФ', 'Matta', 'Застройка выставочного стенда 72 кв.м', 'documents/MATTA fair/+207127843204799073250200101_DESSO_MIMARLIK_TAS_TAN_ORG_DEK_FUAR.pdf'],
            ['3/9/1', '2025-09-03', 'RX France S.A.S.', $rental, 88434.00, 'EUR', '74-АФ', 'IFTM', 'Аренда стандартной выставочной площади 115 кв.м + оргпакет', 'documents/IFTM top resa/+207127843204799073250200103 RX FRANCE 74-AF.pdf'],
            ['29/8/1', '2025-08-29', 'Japan Association of Travel Agents (JATA)', $rental, 27727.55, 'USD', '74-АФ', 'Tourism Expo', 'Аренда необорудованной выставочной площади 72 кв.м', 'documents/tourism expo  japan/+207127843204799073250200100_JAPAN_ASSOCIATION_OF_TRAVEL_AGENTS.pdf'],
            ['25/8/1', '2025-08-25', 'PTAK Warsaw Expo Sp. z o.o.', $rental, 26850.00, 'EUR', '74-АФ', 'TT Warsaw', 'Аренда выставочной площади 105 кв.м', 'documents/TT warsaw/+207127843204799073250200098 PTAK WARSAW EXPO 74-AF.pdf'],
            ['L-01-105', '2025-09-22', 'INSIGHT EXP FZE', $stand, 64000.00, 'EUR', '74-АФ', 'TT Warsaw', 'Застройка стенда «Узбекистан» 105 кв.м', 'documents/TT warsaw/+207127843204799073250200107 INSIGHT EXP FZE 74-AF.pdf'],
            ['16/7/1', '2025-07-16', 'Think Strawberries MENA LLC', $rental, 90490.31, 'GBP', '74-АФ', 'World Travel Market', 'Аренда площади bare space 119 кв.м (через посредника, RX)', 'documents/world travel market/+207127843204799073250200087 THINK STRAWBERRIES 74-AF.pdf'],
            ['24/09', '2025-10-24', 'Think Strawberries MENA LLC', $stand, 94800.00, 'GBP', '74-АФ', 'World Travel Market', 'Застройка выставочного стенда 119 кв.м', 'documents/world travel market/+207127843204799073250200119 THINK STRAWBERRIES 74-AF.pdf'],
        ];

        foreach ($rows as [$number, $signedAt, $contactName, $type, $amount, $code, $orderNumber, $projectKey, $subject, $dossierPath]) {
            $contact = $contacts[$contactName] ?? null;
            $cur = $currency($code);

            if (! $contact || ! $cur || ! $type) {
                continue;
            }

            $project = Project::query()
                ->where('type', ProjectType::International)
                ->whereYear('starts_on', 2025)
                ->where('name', 'like', "%{$projectKey}%")
                ->first();

            $contract = Contract::query()->firstWhere('number', $number);

            if (! $contract) {
                $contract = new Contract([
                    'number' => $number,
                    'contract_type_id' => $type->id,
                    'order_id' => $orders[$orderNumber]->id ?? null,
                    'contact_id' => $contact->id,
                    'project_id' => $project?->id,
                    'currency_id' => $cur->id,
                    'responsible_id' => $responsible->id,
                    'title' => $subject,
                    'amount' => $amount,
                    'status' => Contract::STATUS_DRAFT,
                    'signed_at' => $signedAt,
                ]);
                $contract->save();

                // The dossier arrived signed on paper — file it as approved
                // without waking the approval machinery.
                $contract->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();
            }

            if (! app()->runningUnitTests()
                && $dossierPath
                && $contract->attachments()->count() === 0
                && is_file(base_path($dossierPath))) {
                $stored = $this->copyFile($dossierPath, 'uploads/files/contract-attachments/'.$contract->id);

                $contract->attachments()->create([
                    'type' => ContractAttachmentType::ContractScan->value,
                    'file_path' => $stored,
                    'original_name' => basename($dossierPath),
                    'size' => Storage::disk('local')->size($stored),
                    'sort' => 1,
                ]);
            }
        }
    }

    /**
     * Copy a repository document into the private uploads disk, returning the
     * stored relative path.
     */
    protected function copyFile(string $sourcePath, string $directory): string
    {
        $target = $directory.'/'.basename($sourcePath);

        if (! Storage::disk('local')->exists($target)) {
            Storage::disk('local')->put($target, file_get_contents(base_path($sourcePath)));
        }

        return $target;
    }
}
