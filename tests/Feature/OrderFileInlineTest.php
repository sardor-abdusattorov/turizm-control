<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('serves an order PDF inline so the browser renders it instead of downloading', function () {
    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/test.pdf',
    ]);
    Storage::disk('local')->put($order->file_path, '%PDF-fake');

    actingAs(User::factory()->create());

    $response = get(route('orders.file.inline', $order));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('serves an order xlsx inline', function () {
    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/test.xlsx',
    ]);
    Storage::disk('local')->put($order->file_path, 'fake-xlsx');

    actingAs(User::factory()->create());

    $response = get(route('orders.file.inline', $order));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('returns 404 when the order file is missing on disk', function () {
    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/missing.pdf',
    ]);

    actingAs(User::factory()->create());

    get(route('orders.file.inline', $order))->assertNotFound();
});
