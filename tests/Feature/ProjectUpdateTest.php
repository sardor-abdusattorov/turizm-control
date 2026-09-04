<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hands the first user every permission shield generates, widgets included', function () {
    $admin = User::factory()->create(['id' => 1]);

    $this->artisan('project:update')->assertSuccessful();

    $admin = $admin->fresh();

    expect($admin->hasRole('super_admin'))->toBeTrue()
        ->and($admin->can('view_contract_payments_table_widget'))->toBeTrue()
        ->and($admin->can('view_document_history_timeline_widget'))->toBeTrue();
});
