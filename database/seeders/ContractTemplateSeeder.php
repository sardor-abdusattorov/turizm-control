<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\OrderType;
use Illuminate\Database\Seeder;

class ContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $orderType = OrderType::query()
            ->where('title->ru', 'Заявка на закупку')
            ->first();

        if (! $orderType) {
            $this->command?->warn('OrderType "Заявка на закупку" not found — run OrderTypeSeeder first. Skipping ContractTemplateSeeder.');

            return;
        }

        $body = $this->purchaseRequestBody();

        ContractTemplate::updateOrCreate(
            [
                'order_type_id' => $orderType->id,
                'sort' => 0,
            ],
            [
                'name' => [
                    'ru' => 'Заявка на закупку',
                    'uz' => 'Xarid uchun ariza',
                    'en' => 'Purchase request',
                ],
                'content' => [
                    'ru' => $body['ru'],
                    'uz' => $body['uz'],
                    'en' => $body['en'],
                ],
                'fields' => [
                    [
                        'name' => 'project_plan_item',
                        'label' => 'Пункт календарного плана',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                    [
                        'name' => 'procurement_type',
                        'label' => 'Применимый вид государственной закупки',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                ],
                'status' => true,
            ]
        );
    }

    /**
     * @return array{ru: string, uz: string, en: string}
     */
    protected function purchaseRequestBody(): array
    {
        $ruBody = <<<'HTML'
<div style="text-align: right; margin-bottom: 16px;">{{logo}}</div>

<h2 style="text-align: center; margin: 0 0 24px 0;">ЗАЯВКА НА ЗАКУПКУ</h2>

<table style="width: 100%; border: none; margin-bottom: 16px;">
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Номер:</strong></td>
        <td style="border: none; padding: 4px 0;">{{contract_number}}</td>
    </tr>
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Дата составления:</strong></td>
        <td style="border: none; padding: 4px 0;">{{created_at}}</td>
    </tr>
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Менеджер закупки:</strong></td>
        <td style="border: none; padding: 4px 0;">{{manager_name}}</td>
    </tr>
</table>

<h3>1. Проект (пункт календарного плана)</h3>
<p>{{project_plan_item}}</p>

<h3>2. Детали закупки</h3>
{{purchase_items}}

<h3>3. Применимый вид государственной закупки</h3>
<p>{{procurement_type}}</p>

{{approvers_block}}
HTML;

        $uzBody = <<<'HTML'
<div style="text-align: right; margin-bottom: 16px;">{{logo}}</div>

<h2 style="text-align: center; margin: 0 0 24px 0;">XARID UCHUN ARIZA</h2>

<table style="width: 100%; border: none; margin-bottom: 16px;">
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Raqami:</strong></td>
        <td style="border: none; padding: 4px 0;">{{contract_number}}</td>
    </tr>
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Tuzilgan sanasi:</strong></td>
        <td style="border: none; padding: 4px 0;">{{created_at}}</td>
    </tr>
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Xarid menejeri:</strong></td>
        <td style="border: none; padding: 4px 0;">{{manager_name}}</td>
    </tr>
</table>

<h3>1. Loyiha (kalendar reja bandi)</h3>
<p>{{project_plan_item}}</p>

<h3>2. Xarid tafsilotlari</h3>
{{purchase_items}}

<h3>3. Davlat xaridining qo'llaniladigan turi</h3>
<p>{{procurement_type}}</p>

{{approvers_block}}
HTML;

        $enBody = <<<'HTML'
<div style="text-align: right; margin-bottom: 16px;">{{logo}}</div>

<h2 style="text-align: center; margin: 0 0 24px 0;">PURCHASE REQUEST</h2>

<table style="width: 100%; border: none; margin-bottom: 16px;">
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Number:</strong></td>
        <td style="border: none; padding: 4px 0;">{{contract_number}}</td>
    </tr>
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Date:</strong></td>
        <td style="border: none; padding: 4px 0;">{{created_at}}</td>
    </tr>
    <tr>
        <td style="border: none; padding: 4px 0;"><strong>Procurement manager:</strong></td>
        <td style="border: none; padding: 4px 0;">{{manager_name}}</td>
    </tr>
</table>

<h3>1. Project (calendar plan item)</h3>
<p>{{project_plan_item}}</p>

<h3>2. Procurement details</h3>
{{purchase_items}}

<h3>3. Applicable type of public procurement</h3>
<p>{{procurement_type}}</p>

{{approvers_block}}
HTML;

        return [
            'ru' => $ruBody,
            'uz' => $uzBody,
            'en' => $enBody,
        ];
    }
}
