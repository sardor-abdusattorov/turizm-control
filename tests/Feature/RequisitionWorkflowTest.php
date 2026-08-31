<?php

use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\Pages\CreateRequisition;
use App\Filament\Resources\Requisitions\Pages\EditRequisition;
use App\Filament\Resources\Requisitions\Pages\ListRequisitions;
use App\Filament\Resources\Requisitions\Pages\ViewRequisition;
use App\Models\Requisition;
use App\Models\Settings;
use App\Models\User;
use App\Services\Requisitions\RequisitionWorkflow;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function requisitionUser(array $abilities = ['view_any_requisition', 'view_requisition', 'create_requisition', 'update_requisition']): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach ($abilities as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user->fresh();
}

it('numbers a new requisition and stamps its author', function () {
    $author = requisitionUser();
    $reviewer = requisitionUser();
    actingAs($author);

    Livewire::test(CreateRequisition::class)
        ->fillForm([
            'title' => 'Канцелярия на III квартал',
            'description' => 'Бумага А4 — 20 пачек, картриджи — 4 шт.',
            'reviewer_id' => $reviewer->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $requisition = Requisition::firstWhere('title', 'Канцелярия на III квартал');

    expect($requisition->number)->toBe('ЗВ-'.now()->year.'-001')
        ->and($requisition->author_id)->toBe($author->id)
        ->and($requisition->reviewer_id)->toBe($reviewer->id)
        ->and($requisition->status)->toBe(RequisitionStatus::Draft)
        ->and($requisition->due_at)->toBeNull();
});

it('keeps numbering sequential within the year', function () {
    $author = requisitionUser();
    actingAs($author);

    Requisition::factory()->create(['number' => 'ЗВ-'.now()->year.'-007']);

    expect(Requisition::nextNumber())->toBe('ЗВ-'.now()->year.'-008');
});

it('pre-fills the reviewer from settings', function () {
    $author = requisitionUser();
    $defaultReviewer = requisitionUser();
    Settings::set('requisition.reviewer_id', $defaultReviewer->id);
    actingAs($author);

    Livewire::test(CreateRequisition::class)
        ->assertFormSet(['reviewer_id' => $defaultReviewer->id]);
});

it('stamps the review deadline from settings on submit', function () {
    Settings::set('requisition.review_days', 5);

    $author = requisitionUser();
    $requisition = Requisition::factory()->create(['author_id' => $author->id]);

    actingAs($author);

    expect(app(RequisitionWorkflow::class)->submit($requisition))->toBeTrue();

    $requisition->refresh();

    expect($requisition->status)->toBe(RequisitionStatus::InReview)
        ->and($requisition->submitted_at)->not->toBeNull()
        ->and((int) round(now()->diffInDays($requisition->due_at)))->toBe(5);
});

it('does not move a live deadline when the setting changes afterwards', function () {
    Settings::set('requisition.review_days', 2);

    $author = requisitionUser();
    $requisition = Requisition::factory()->create(['author_id' => $author->id]);
    actingAs($author);

    app(RequisitionWorkflow::class)->submit($requisition);
    $stamped = $requisition->fresh()->due_at;

    Settings::set('requisition.review_days', 30);

    expect($requisition->fresh()->due_at->timestamp)->toBe($stamped->timestamp);
});

it('refuses to submit a requisition someone else wrote', function () {
    $author = requisitionUser();
    $stranger = requisitionUser();
    $requisition = Requisition::factory()->create(['author_id' => $author->id]);

    actingAs($stranger);

    expect(app(RequisitionWorkflow::class)->submit($requisition))->toBeFalse()
        ->and($requisition->fresh()->status)->toBe(RequisitionStatus::Draft);
});

it('refuses to submit a requisition with nobody to review it', function () {
    $author = requisitionUser();
    $requisition = Requisition::factory()->create([
        'author_id' => $author->id,
        'reviewer_id' => null,
    ]);

    actingAs($author);

    expect(app(RequisitionWorkflow::class)->submit($requisition))->toBeFalse();
});

it('lets only the named reviewer settle it', function () {
    $reviewer = requisitionUser();
    $stranger = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['reviewer_id' => $reviewer->id]);

    actingAs($stranger);
    expect(app(RequisitionWorkflow::class)->approve($requisition))->toBeFalse();

    actingAs($reviewer);
    expect(app(RequisitionWorkflow::class)->approve($requisition, 'Согласовано.'))->toBeTrue();

    $requisition->refresh();

    expect($requisition->status)->toBe(RequisitionStatus::Approved)
        ->and($requisition->review_comment)->toBe('Согласовано.')
        ->and($requisition->reviewed_at)->not->toBeNull();
});

it('keeps the rejection reason and lets the author edit again', function () {
    $author = requisitionUser();
    $reviewer = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create([
        'author_id' => $author->id,
        'reviewer_id' => $reviewer->id,
    ]);

    actingAs($reviewer);
    app(RequisitionWorkflow::class)->reject($requisition, 'Нет обоснования суммы.');

    $requisition->refresh();

    actingAs($author);

    expect($requisition->status)->toBe(RequisitionStatus::Rejected)
        ->and($requisition->review_comment)->toBe('Нет обоснования суммы.')
        ->and($requisition->canBeEditedBy())->toBeTrue()
        ->and($requisition->canBeSubmittedBy())->toBeTrue();
});

it('freezes a requisition while it is under review', function () {
    $author = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['author_id' => $author->id]);

    actingAs($author);

    expect($requisition->canBeEditedBy())->toBeFalse();

    Livewire::test(EditRequisition::class, ['record' => $requisition->id])
        ->assertForbidden();
});

it('never settles the same requisition twice', function () {
    $reviewer = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['reviewer_id' => $reviewer->id]);

    actingAs($reviewer);

    $workflow = app(RequisitionWorkflow::class);

    expect($workflow->approve($requisition, 'Ок'))->toBeTrue()
        ->and($workflow->reject($requisition, 'Передумал'))->toBeFalse()
        ->and($requisition->fresh()->status)->toBe(RequisitionStatus::Approved);
});

it('shows only the requisitions a user is part of', function () {
    $author = requisitionUser();
    $reviewer = requisitionUser();
    $mine = Requisition::factory()->create(['author_id' => $author->id, 'reviewer_id' => $reviewer->id]);
    $toReview = Requisition::factory()->create(['reviewer_id' => $author->id]);
    $stranger = Requisition::factory()->create();

    actingAs($author);

    Livewire::test(ListRequisitions::class)
        ->assertCanSeeTableRecords([$mine, $toReview])
        ->assertCanNotSeeTableRecords([$stranger]);
});

it('shows the whole registry to oversight', function () {
    $overseer = requisitionUser(['view_any_requisition', 'view_requisition', 'view_all_requisitions']);
    $others = Requisition::factory()->count(3)->create();

    actingAs($overseer);

    Livewire::test(ListRequisitions::class)
        ->assertCanSeeTableRecords($others);
});

it('offers the review actions only to the reviewer', function () {
    $author = requisitionUser();
    $reviewer = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create([
        'author_id' => $author->id,
        'reviewer_id' => $reviewer->id,
    ]);

    actingAs($author);
    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionHidden('approveRequisition')
        ->assertActionHidden('rejectRequisition');

    actingAs($reviewer);
    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionVisible('approveRequisition')
        ->assertActionVisible('rejectRequisition');
});

it('rejects through the page action and records the reason', function () {
    $reviewer = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['reviewer_id' => $reviewer->id]);

    actingAs($reviewer);

    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->callAction(TestAction::make('rejectRequisition'), ['comment' => 'Смета не приложена.'])
        ->assertNotified();

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Rejected)
        ->and($requisition->fresh()->review_comment)->toBe('Смета не приложена.');
});

it('demands a reason before a rejection goes through', function () {
    $reviewer = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['reviewer_id' => $reviewer->id]);

    actingAs($reviewer);

    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->callAction(TestAction::make('rejectRequisition'), ['comment' => null])
        ->assertHasActionErrors(['comment' => 'required']);

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::InReview);
});

it('marks an unmet deadline as overdue', function () {
    $onTime = Requisition::factory()->inReview()->create();
    $late = Requisition::factory()->overdue()->create();
    $settled = Requisition::factory()->approved()->create(['due_at' => now()->subWeek()]);

    expect($onTime->isOverdue())->toBeFalse()
        ->and($late->isOverdue())->toBeTrue()
        ->and($settled->isOverdue())->toBeFalse();
});
