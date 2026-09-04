<?php

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RequisitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PositionSeeder::class, DepartmentSeeder::class, RolesAndPermissionsSeeder::class, TestUsersSeeder::class]);
});

it('seeds requisitions covering every status with real approval chains', function () {
    $this->seed(RequisitionSeeder::class);

    expect(Requisition::query()->pluck('status'))
        ->toContain(RequisitionStatus::Draft, RequisitionStatus::InReview, RequisitionStatus::Approved, RequisitionStatus::Rejected);

    $approved = Requisition::query()->where('status', RequisitionStatus::Approved)->first();
    expect($approved->activeApprovals()->every(fn ($a) => $a->status === ApprovalStatus::Approved))->toBeTrue();

    $rejected = Requisition::query()->where('status', RequisitionStatus::Rejected)->first();
    expect($rejected->activeApprovals()->contains(fn ($a) => $a->status === ApprovalStatus::Rejected))->toBeTrue();

    $inReview = Requisition::query()->where('status', RequisitionStatus::InReview)->first();
    expect($inReview->currentApproval())->not->toBeNull();
});

it('does not duplicate requisitions when seeded twice', function () {
    $this->seed(RequisitionSeeder::class);
    $count = Requisition::query()->count();

    $this->seed(RequisitionSeeder::class);

    expect(Requisition::query()->count())->toBe($count);
});

it('seeds default requisition approvers so the chain works out of the box', function () {
    $this->seed(SettingsSeeder::class);

    expect(Requisition::defaultApproverIds())->toHaveCount(1);
});

it('badges requisitions with a total count like every other resource', function () {
    $this->seed(RequisitionSeeder::class);

    actingAs(userWithPermission('view_any_requisition', 'view_all_requisitions'));

    expect(RequisitionResource::getNavigationBadge())->toBe((string) Requisition::query()->count());
});
