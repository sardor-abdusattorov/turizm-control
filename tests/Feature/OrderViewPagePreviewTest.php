<?php

use App\Filament\Resources\Orders\Pages\ViewInternalOrder;
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

it('renders the file card with a PDF-open link for PDF orders', function () {
    actAsOrderViewer();
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/sample.pdf',
    ]);
    Storage::disk('local')->put($order->file_path, '%PDF-fake');

    $html = Livewire::test(ViewInternalOrder::class, ['record' => $order->id])->html();

    expect($html)->toContain('sample.pdf')
        ->toContain('PDF')
        ->toContain(route('orders.file.inline', ['order' => $order]))
        ->not->toContain('<iframe');
});

it('renders the file card with a serve link for docx orders', function () {
    actAsOrderViewer();
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/sample.docx',
    ]);
    Storage::disk('local')->put($order->file_path, 'fake-docx');

    $html = Livewire::test(ViewInternalOrder::class, ['record' => $order->id])->html();

    // Every file type now gets the same single action — serve the file; the
    // browser previews what it can and downloads a docx.
    expect($html)->toContain('sample.docx')
        ->toContain('DOCX')
        ->toContain(route('orders.file.inline', ['order' => $order]))
        ->not->toContain('<iframe');
});

it('hides the preview card when the order has no file on disk', function () {
    actAsOrderViewer();
    Storage::fake('local');

    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/missing.pdf',
    ]);

    $html = Livewire::test(ViewInternalOrder::class, ['record' => $order->id])->html();

    // No file -> the whole card is gated behind fileExists(), so the serve
    // URL never appears.
    expect($html)->not->toContain('<iframe')
        ->not->toContain(route('orders.file.inline', ['order' => $order]));
});
