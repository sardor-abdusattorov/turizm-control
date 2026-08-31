<?php

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\Order;
use App\Models\Payment;
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

it('deletes attachment files from disk when the contract is deleted', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    foreach (['scan-1.pdf', 'scan-2.pdf'] as $i => $name) {
        Storage::disk('local')->put("uploads/files/contract-attachments/t/{$name}", 'fake');
        $contract->attachments()->create([
            'file_path' => "uploads/files/contract-attachments/t/{$name}",
            'original_name' => $name, 'size' => 4, 'sort' => $i + 1,
        ]);
    }

    $contract->delete();

    expect(ContractAttachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('uploads/files/contract-attachments/t/scan-1.pdf');
    Storage::disk('local')->assertMissing('uploads/files/contract-attachments/t/scan-2.pdf');
});

it('deletes payment proof files from disk when the contract is deleted', function () {
    Storage::fake('local');

    $contract = Contract::factory()->create();
    $proofs = ['uploads/images/payments/t/a.png', 'uploads/images/payments/t/b.pdf'];
    foreach ($proofs as $path) {
        Storage::disk('local')->put($path, 'fake');
    }
    Payment::factory()->forContract($contract)->create(['screenshots' => $proofs]);

    $contract->delete();

    // The DB cascade alone would drop the row but strand the files — the
    // observer must delete payments through Eloquent, like attachments.
    expect(Payment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($proofs[0]);
    Storage::disk('local')->assertMissing($proofs[1]);
});
