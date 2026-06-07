<?php

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('deletes the order docx from disk when the order is deleted', function () {
    Storage::fake('public');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/test.docx',
    ]);
    Storage::disk('public')->put($order->file_path, 'fake');

    $order->delete();

    Storage::disk('public')->assertMissing('uploads/files/orders/test.docx');
});

it('deletes the template docx from disk when the template is deleted', function () {
    Storage::fake('public');

    $template = ContractTemplate::factory()->create([
        'template_file' => 'uploads/files/contract-templates/test.docx',
    ]);
    Storage::disk('public')->put($template->template_file, 'fake');

    $template->delete();

    Storage::disk('public')->assertMissing('uploads/files/contract-templates/test.docx');
});

it('wipes the contract folder on disk when a contract is deleted', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    Storage::disk('local')->put("contracts/{$contract->id}/draft.docx", 'fake');
    Storage::disk('local')->put("contracts/{$contract->id}/contract.pdf", 'fake');

    $contract->delete();

    Storage::disk('local')->assertMissing("contracts/{$contract->id}/draft.docx");
    Storage::disk('local')->assertMissing("contracts/{$contract->id}/contract.pdf");
});
