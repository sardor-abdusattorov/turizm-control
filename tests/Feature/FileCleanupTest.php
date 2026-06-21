<?php

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('deletes the order docx from disk when the order is deleted', function () {
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/test.docx',
    ]);
    Storage::disk('local')->put($order->file_path, 'fake');

    $order->delete();

    Storage::disk('local')->assertMissing('uploads/files/orders/test.docx');
});

it('deletes the template docx from disk when the template is deleted', function () {
    Storage::fake('local');

    $template = ContractTemplate::factory()->create([
        'template_file' => 'uploads/files/uploads/files/contract-templates/test.docx',
    ]);
    Storage::disk('local')->put($template->template_file, 'fake');

    $template->delete();

    Storage::disk('local')->assertMissing('uploads/files/uploads/files/contract-templates/test.docx');
});

it('wipes the contract folder on disk when a contract is deleted', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    Storage::disk('local')->put("uploads/files/contracts/{$contract->id}/draft.docx", 'fake');
    Storage::disk('local')->put("uploads/files/contracts/{$contract->id}/contract.pdf", 'fake');

    $contract->delete();

    Storage::disk('local')->assertMissing("uploads/files/contracts/{$contract->id}/draft.docx");
    Storage::disk('local')->assertMissing("uploads/files/contracts/{$contract->id}/contract.pdf");
});

it('wipes files for every order in a bulk delete', function () {
    Storage::fake('local');

    $orders = Order::factory()->count(3)->create();

    foreach ($orders as $i => $order) {
        $order->update(['file_path' => "uploads/files/orders/bulk-{$i}.docx"]);
        Storage::disk('local')->put($order->file_path, 'fake');
    }

    Order::query()->whereIn('id', $orders->pluck('id'))->get()->each->delete();

    foreach ($orders as $i => $order) {
        Storage::disk('local')->assertMissing("uploads/files/orders/bulk-{$i}.docx");
    }
});
