<?php

use App\Filament\Pages\ProfileSettings;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the profile page with the avatar uploader and approver picker', function () {
    $department = Department::factory()->create(['code' => 'legal']);
    User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);

    actingAs(User::factory()->create()->fresh());

    Livewire::test(ProfileSettings::class)
        ->assertOk()
        ->assertFormFieldExists('avatar_url', 'profileForm')
        ->assertFormFieldExists('default_recipients', 'profileForm');
});
