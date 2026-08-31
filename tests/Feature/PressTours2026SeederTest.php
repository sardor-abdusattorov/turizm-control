<?php

use App\Enums\PressTourDirection;
use App\Models\Order;
use App\Models\PressTour;
use App\Models\User;
use Database\Seeders\PressTours2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create();
});

it('imports the 2026 press, blogger and info-tour registry', function () {
    $this->seed(PressTours2026Seeder::class);

    expect(PressTour::count())->toBe(21)
        ->and(PressTour::where('direction', PressTourDirection::Inbound->value)->count())->toBe(7)
        ->and(PressTour::where('direction', PressTourDirection::Local->value)->count())->toBe(13)
        ->and(PressTour::where('direction', PressTourDirection::Outbound->value)->count())->toBe(1);
});

it('keeps the registry wording for periods and headcounts', function () {
    $this->seed(PressTours2026Seeder::class);

    $georgia = PressTour::where('place', 'Грузия')->firstOrFail();

    expect($georgia->period)->toBe('11-18 Август')
        ->and($georgia->starts_month)->toBe(8)
        ->and($georgia->people_count)->toBeNull()
        ->and($georgia->people_note)->toBe('6+11')
        ->and($georgia->peopleLabel())->toBe('6+11');

    $melon = PressTour::where('name', 'like', '%Праздник дыни%')->firstOrFail();
    expect($melon->people_count)->toBe(36)
        ->and($melon->peopleLabel())->toBe('36');
});

it('splits the two names the registry crammed into one cell', function () {
    $this->seed(PressTours2026Seeder::class);

    $egypt = PressTour::where('place', 'Египет')->firstOrFail();

    expect($egypt->responsible)->toBe('Шерзод Султонов')
        ->and($egypt->curator)->toBe('Хаёт Хамраев')
        ->and($egypt->responsibleNames())->toBe(['Шерзод Султонов', 'Хаёт Хамраев'])
        ->and($egypt->foreign_partner)->toBe('Посольство Узбекистана в Египте');
});

it('rests every domestic tour on the press-tour buyruq', function () {
    $this->seed(PressTours2026Seeder::class);

    $order = Order::where('number', '49-AF')->firstOrFail();

    expect(PressTour::where('direction', PressTourDirection::Local->value)->whereNull('order_id')->count())->toBe(0)
        ->and(PressTour::where('order_id', $order->id)->count())->toBe(13);
});

it('keeps same-named tours that recur in different months apart', function () {
    $this->seed(PressTours2026Seeder::class);

    $samarkand = PressTour::where('name', 'like', '%Самаркандской%')->get();

    expect($samarkand)->toHaveCount(2)
        ->and($samarkand->pluck('starts_month')->sort()->values()->all())->toBe([10, 11]);
});

it('is idempotent', function () {
    $this->seed(PressTours2026Seeder::class);
    $this->seed(PressTours2026Seeder::class);

    expect(PressTour::count())->toBe(21)
        ->and(Order::where('number', '49-AF')->count())->toBe(1);
});
