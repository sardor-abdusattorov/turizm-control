<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    config([
        'onlyoffice.public_url' => 'http://onlyoffice',
        'onlyoffice.internal_url' => 'http://onlyoffice',
        'onlyoffice.callback_host' => 'http://nginx',
        'onlyoffice.jwt_secret' => 'test-secret',
    ]);
});

function makeOrderWithDocx(): Order
{
    $order = Order::factory()->create();
    Storage::disk('local')->put($order->file_path, 'fake-docx');

    return $order->fresh();
}

it('issues a fresh document_key when the order is created', function () {
    $order = Order::factory()->create();

    expect($order->document_key)->not->toBeEmpty()
        ->and(strlen($order->document_key))->toBe(20);
});

it('renders the OnlyOffice editor view for an order with a docx', function () {
    $order = makeOrderWithDocx();

    actingAs(User::factory()->create());

    get(route('orders.editor', $order))->assertOk();
});

it('returns 404 when the order file is missing', function () {
    $order = Order::factory()->create();

    actingAs(User::factory()->create());

    get(route('orders.editor', $order))->assertNotFound();
});

it('returns 404 when the order file is not a docx', function () {
    $order = Order::factory()->create([
        'file_path' => 'uploads/files/orders/2026/06/report.pdf',
    ]);
    Storage::disk('local')->put($order->file_path, '%PDF-fake');

    actingAs(User::factory()->create());

    get(route('orders.editor', $order))->assertNotFound();
});

it('downloads the order docx via OnlyOffice with a valid shared_key', function () {
    $order = makeOrderWithDocx();

    get(route('onlyoffice.order.document', [
        'order' => $order,
        'shared_key' => $order->document_key,
    ]))->assertOk();
});

it('rejects OnlyOffice document requests with a wrong shared_key', function () {
    $order = makeOrderWithDocx();

    get(route('onlyoffice.order.document', [
        'order' => $order,
        'shared_key' => 'wrong',
    ]))->assertForbidden();
});

it('persists the edited order docx on OnlyOffice callback', function () {
    Http::fake([
        '*/order-edited.docx' => Http::response('%edited'),
    ]);

    $order = makeOrderWithDocx();
    $originalKey = $order->document_key;

    post(route('onlyoffice.order.callback', [
        'order' => $order,
        'shared_key' => $order->document_key,
    ]), [
        'status' => 2,
        'url' => 'http://onlyoffice/cache/files/order-edited.docx',
    ])->assertOk()->assertExactJson(['error' => 0]);

    expect(Storage::disk('local')->get($order->file_path))->toBe('%edited')
        ->and($order->fresh()->document_key)->not->toBe($originalKey);
});

it('keeps the key on order forcesave callback (status 6)', function () {
    Http::fake([
        '*/order-edited.docx' => Http::response('%mid'),
    ]);

    $order = makeOrderWithDocx();
    $originalKey = $order->document_key;

    post(route('onlyoffice.order.callback', [
        'order' => $order,
        'shared_key' => $order->document_key,
    ]), [
        'status' => 6,
        'url' => 'http://onlyoffice/cache/files/order-edited.docx',
    ])->assertOk();

    expect($order->fresh()->document_key)->toBe($originalKey);
});
