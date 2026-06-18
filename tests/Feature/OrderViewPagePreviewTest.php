<?php

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function actAsOrderViewer(): User
{
    $user = User::factory()->create();
    foreach (['view_any_order', 'view_order'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    actingAs($user->fresh());

    return $user;
}

it('embeds an inline PDF iframe on the order view page when the file is a PDF', function () {
    actAsOrderViewer();
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/sample.pdf',
    ]);
    Storage::disk('local')->put($order->file_path, '%PDF-fake');

    $html = Livewire::test(ViewOrder::class, ['record' => $order->id])->html();

    expect($html)->toContain(route('orders.file.inline', ['order' => $order]))
        ->and($html)->toContain('<iframe');
});

it('embeds an OnlyOffice viewer iframe on the order view page when the file is a docx', function () {
    actAsOrderViewer();
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/sample.docx',
    ]);
    Storage::disk('local')->put($order->file_path, 'fake-docx');

    $html = Livewire::test(ViewOrder::class, ['record' => $order->id])->html();

    expect($html)->toContain(route('orders.editor', ['order' => $order, 'mode' => 'view']))
        ->and($html)->toContain('<iframe');
});

it('hides the preview when the order has no file on disk', function () {
    actAsOrderViewer();
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/missing.pdf',
    ]);

    $html = Livewire::test(ViewOrder::class, ['record' => $order->id])->html();

    expect($html)->not->toContain('<iframe');
});
