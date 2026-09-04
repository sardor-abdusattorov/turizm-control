<?php

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Filament\Resources\Requisitions\Pages\CreateRequisition;
use App\Filament\Resources\Requisitions\Pages\EditRequisition;
use App\Filament\Resources\Requisitions\Pages\ListRequisitions;
use App\Filament\Resources\Requisitions\Pages\ViewRequisition;
use App\Filament\Widgets\ApprovalsTimelineWidget;
use App\Models\Requisition;
use App\Models\Settings;
use App\Models\User;
use App\Services\Approvals\ApprovalWorkflow;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function requisitionUser(array $abilities = ['view_any_requisition', 'view_requisition', 'create_requisition', 'update_requisition', 'approve_requisitions']): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach ($abilities as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user->fresh();
}

it('numbers a new requisition and queues the whole chain', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    actingAs($author);

    Livewire::test(CreateRequisition::class)
        ->fillForm([
            'title' => 'Канцелярия на III квартал',
            'description' => 'Бумага А4 — 20 пачек.',
            'approver_ids' => [$first->id, $second->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $requisition = Requisition::firstWhere('title', 'Канцелярия на III квартал');

    expect($requisition->number)->toBe('ЗВ-'.now()->year.'-001')
        ->and($requisition->author_id)->toBe($author->id)
        ->and($requisition->status)->toBe(RequisitionStatus::Draft)
        ->and($requisition->activeApprovals())->toHaveCount(2)
        ->and($requisition->activeApprovals()->pluck('status')->unique()->all())->toBe([ApprovalStatus::Queued]);
});

it('pre-fills the chain from settings', function () {
    $author = requisitionUser();
    $a = requisitionUser();
    $b = requisitionUser();
    Settings::set('requisition.approver_ids', [$a->id, $b->id]);
    actingAs($author);

    Livewire::test(CreateRequisition::class)
        ->assertFormSet(['approver_ids' => [$a->id, $b->id]]);
});

it('opens only the first step on submit and leaves the rest queued', function () {
    Settings::set('requisition.review_days', 5);

    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->withChain([$first, $second])->create(['author_id' => $author->id]);

    actingAs($author);
    app(ApprovalWorkflow::class)->submit($requisition->fresh());

    $requisition = $requisition->fresh()->load('approvals');
    $steps = $requisition->activeApprovals();

    expect($requisition->status)->toBe(RequisitionStatus::InReview)
        ->and($requisition->submitted_at)->not->toBeNull()
        ->and($steps->firstWhere('user_id', $first->id)->status)->toBe(ApprovalStatus::Pending)
        ->and($steps->firstWhere('user_id', $second->id)->status)->toBe(ApprovalStatus::Queued)
        ->and((int) round(now()->diffInDays($steps->firstWhere('user_id', $first->id)->due_at)))->toBe(5);
});

it('hands the turn to the next approver and settles when the last one approves', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create(['author_id' => $author->id]);

    $workflow = app(ApprovalWorkflow::class);

    actingAs($first);
    $workflow->approve($requisition->fresh()->load('approvals'), $first, 'Ок от первого.');

    $mid = $requisition->fresh()->load('approvals');

    expect($mid->status)->toBe(RequisitionStatus::InReview)
        ->and($mid->activeApprovals()->firstWhere('user_id', $second->id)->status)->toBe(ApprovalStatus::Pending);

    actingAs($second);
    $workflow->approve($mid, $second, 'Ок от второго.');

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Approved);
});

it('refuses an approver whose turn has not come', function () {
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create();

    actingAs($second);

    expect(fn () => app(ApprovalWorkflow::class)->approve($requisition->fresh()->load('approvals'), $second))
        ->toThrow(RuntimeException::class, __('app.approval.error.waiting_for_previous'));
});

it('stops the whole chain when somebody rejects', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $third = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second, $third])->create(['author_id' => $author->id]);

    actingAs($first);
    app(ApprovalWorkflow::class)->reject($requisition->fresh()->load('approvals'), $first, 'Нет обоснования суммы.');

    $requisition = $requisition->fresh()->load('approvals');
    $rows = $requisition->approvals;

    expect($requisition->status)->toBe(RequisitionStatus::Rejected)
        ->and($rows->firstWhere('user_id', $first->id)->status)->toBe(ApprovalStatus::Rejected)
        ->and($rows->firstWhere('user_id', $first->id)->comment)->toBe('Нет обоснования суммы.')

        ->and($rows->firstWhere('user_id', $second->id)->status)->toBe(ApprovalStatus::Invalidated)
        ->and($rows->firstWhere('user_id', $third->id)->status)->toBe(ApprovalStatus::Invalidated);
});

it('lets an approver further down the queue veto without waiting their turn', function () {
    $first = requisitionUser();
    $last = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $last])->create();

    actingAs($last);
    app(ApprovalWorkflow::class)->reject($requisition->fresh()->load('approvals'), $last, 'Не согласен по существу.');

    $requisition = $requisition->fresh()->load('approvals');

    expect($requisition->status)->toBe(RequisitionStatus::Rejected)
        ->and($requisition->approvals->firstWhere('user_id', $last->id)->status)->toBe(ApprovalStatus::Rejected);
});

it('keeps the rejected verdict readable after the author edits and it goes round again', function () {
    $author = requisitionUser();
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create(['author_id' => $author->id]);

    actingAs($approver);
    app(ApprovalWorkflow::class)->reject($requisition->fresh()->load('approvals'), $approver, 'Уточните смету.');

    actingAs($author);

    Livewire::test(EditRequisition::class, ['record' => $requisition->id])
        ->fillForm(['title' => 'Канцелярия, исправлено', 'approver_ids' => [$approver->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $requisition = $requisition->fresh()->load('approvals');
    $rounds = $requisition->approvals->groupBy('round');

    expect($requisition->status)->toBe(RequisitionStatus::Draft)
        ->and($rounds)->toHaveCount(2)

        ->and($rounds->get(1)->first()->isVoided())->toBeTrue()
        ->and($rounds->get(1)->first()->displayStatus())->toBe(ApprovalStatus::Rejected)
        ->and($rounds->get(1)->first()->comment)->toBe('Уточните смету.')

        ->and($rounds->get(2)->first()->status)->toBe(ApprovalStatus::Queued);
});

it('restarts a live round from the top when the author edits mid-review', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create(['author_id' => $author->id]);

    actingAs($first);
    app(ApprovalWorkflow::class)->approve($requisition->fresh()->load('approvals'), $first, 'Ок.');

    actingAs($author);
    app(ApprovalWorkflow::class)->restartAfterEdit($requisition->fresh()->load('approvals'));

    $requisition = $requisition->fresh()->load('approvals');
    $live = $requisition->activeApprovals();

    expect($requisition->status)->toBe(RequisitionStatus::InReview)
        ->and($live)->toHaveCount(2)

        ->and($live->firstWhere('user_id', $first->id)->status)->toBe(ApprovalStatus::Pending)
        ->and($live->firstWhere('user_id', $second->id)->status)->toBe(ApprovalStatus::Queued);
});

it('recalls a requisition back to draft and voids the open steps', function () {
    $author = requisitionUser();
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create(['author_id' => $author->id]);

    actingAs($author);
    app(ApprovalWorkflow::class)->recall($requisition->fresh()->load('approvals'));

    $requisition = $requisition->fresh()->load('approvals');

    expect($requisition->status)->toBe(RequisitionStatus::Draft)
        ->and($requisition->submitted_at)->toBeNull()
        ->and($requisition->activeApprovals())->toHaveCount(0)
        ->and($requisition->approvals->first()->isVoided())->toBeTrue();
});

it('refuses to submit a requisition with nobody on the chain', function () {
    $author = requisitionUser();
    $requisition = Requisition::factory()->create(['author_id' => $author->id]);

    actingAs($author);

    expect(fn () => app(ApprovalWorkflow::class)->submit($requisition->fresh()))
        ->toThrow(RuntimeException::class, __('app.approval.error.no_approvers'));
});

it('never lets the same person decide twice', function () {
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create();

    actingAs($approver);
    $workflow = app(ApprovalWorkflow::class);
    $workflow->approve($requisition->fresh()->load('approvals'), $approver, 'Ок');

    expect(fn () => $workflow->reject($requisition->fresh()->load('approvals'), $approver, 'Передумал'))
        ->toThrow(RuntimeException::class);

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Approved);
});

it('freezes a requisition while it is under approval', function () {
    $author = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['author_id' => $author->id]);

    actingAs($author);

    expect($requisition->fresh()->canBeEditedBy())->toBeFalse();

    Livewire::test(EditRequisition::class, ['record' => $requisition->id])
        ->assertForbidden();
});

it('shows only the requisitions a user is part of', function () {
    $author = requisitionUser();
    $approver = requisitionUser();
    $mine = Requisition::factory()->create(['author_id' => $author->id]);
    $toReview = Requisition::factory()->withChain([$author])->create();
    $stranger = Requisition::factory()->create();

    actingAs($author);

    Livewire::test(ListRequisitions::class)
        ->assertCanSeeTableRecords([$mine, $toReview])
        ->assertCanNotSeeTableRecords([$stranger]);
});

it('offers the review actions only to whoever the chain is asking', function () {
    $author = requisitionUser();
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create(['author_id' => $author->id]);

    actingAs($author);
    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionHidden('approve')
        ->assertActionVisible('recall');

    actingAs($approver);
    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionVisible('approve')
        ->assertActionVisible('reject');
});

it('demands a reason before a rejection goes through', function () {
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create();

    actingAs($approver);

    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->callAction(TestAction::make('reject'), ['comment' => null])
        ->assertHasActionErrors(['comment' => 'required']);

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::InReview);
});

it('rejects through the page action and shows the reason back on the record', function () {
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create();

    actingAs($approver);

    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->callAction(TestAction::make('reject'), ['comment' => 'Смета не приложена.'])
        ->assertNotified();

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Rejected);

    expect(Livewire::test(ViewRequisition::class, ['record' => $requisition->id])->html())
        ->toContain('Смета не приложена.');
});

it('renders the chain on the index with each approver and the progress', function () {
    $author = requisitionUser(['view_any_requisition', 'view_requisition', 'view_all_requisitions']);
    $first = requisitionUser();
    $second = requisitionUser();
    Requisition::factory()->inReview([$first, $second])->create();

    actingAs($author);

    $html = Livewire::test(ListRequisitions::class)->html();

    expect($html)->toContain('fi-approvers-cell')
        ->toContain('fi-state-pill')
        ->toContain(e($first->name))
        ->toContain(e($second->name))
        ->toContain('0/2');
});

it('lays the view page out as designed cards, not a bare field list', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create([
        'author_id' => $author->id,
        'title' => 'Закупка канцелярии',
        'description' => "Бумага А4 — 20 пачек.\nКартриджи — 4 шт.",
    ]);

    actingAs($author);

    $html = Livewire::test(ViewRequisition::class, ['record' => $requisition->id])->html();

    expect($html)

        ->toContain('rq-progress__bar')
        ->toContain('0/2')
        ->toContain($first->name)

        ->toContain('ow-card')
        ->toContain('ow-dets')
        ->toContain('Закупка канцелярии')
        ->toContain('Бумага А4');
});

it('carries the same tabs the contract page does', function () {
    $author = requisitionUser();
    $requisition = Requisition::factory()->inReview()->create(['author_id' => $author->id]);

    actingAs($author);

    $html = Livewire::test(ViewRequisition::class, ['record' => $requisition->id])->html();

    expect($html)
        ->toContain('rec-tabs-row')
        ->toContain(__('app.label.basic_information'))
        ->toContain(__('app.approval.section'))
        ->toContain(__('app.label.history'));
});

it('writes the requisition into the activity log its history reads', function () {
    $author = requisitionUser();
    actingAs($author);

    Livewire::test(CreateRequisition::class)
        ->fillForm([
            'title' => 'Стенд на выставке',
            'description' => 'Аренда площади.',
            'approver_ids' => [requisitionUser()->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $requisition = Requisition::firstWhere('title', 'Стенд на выставке');

    expect(Activity::query()
        ->where('subject_type', $requisition->getMorphClass())
        ->where('subject_id', $requisition->getKey())
        ->exists())->toBeTrue();
});

it('leads the view page with the reason it came back', function () {
    $author = requisitionUser();
    $approver = requisitionUser();
    $requisition = Requisition::factory()->inReview([$approver])->create(['author_id' => $author->id]);

    actingAs($approver);
    app(ApprovalWorkflow::class)
        ->reject($requisition->fresh()->load('approvals'), $approver, 'Смета не приложена.');

    actingAs($author);

    expect(Livewire::test(ViewRequisition::class, ['record' => $requisition->id])->html())
        ->toContain('rq-reject')
        ->toContain('Смета не приложена.');
});

it('marks an unmet step as overdue', function () {
    $onTime = Requisition::factory()->inReview()->create();
    $late = Requisition::factory()->overdue()->create();
    $settled = Requisition::factory()->approved()->create();

    expect($onTime->fresh()->load('approvals')->isOverdue())->toBeFalse()
        ->and($late->fresh()->load('approvals')->isOverdue())->toBeTrue()
        ->and($settled->fresh()->load('approvals')->isOverdue())->toBeFalse();
});

it('lists each approver once in the chain table and keeps earlier rounds behind the eye', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create(['author_id' => $author->id]);

    actingAs($first);
    app(ApprovalWorkflow::class)->reject($requisition->fresh()->load('approvals'), $first, 'Уточните смету.');

    actingAs($author);
    Livewire::test(EditRequisition::class, ['record' => $requisition->id])
        ->fillForm(['approver_ids' => [$first->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $approvals = $requisition->fresh()->approvals;
    $firstNow = $approvals->where('round', 2)->firstWhere('user_id', $first->id);
    $firstBefore = $approvals->where('round', 1)->firstWhere('user_id', $first->id);
    $secondBefore = $approvals->where('round', 1)->firstWhere('user_id', $second->id);

    actingAs(requisitionUser(['view_any_requisition', 'view_requisition', 'view_all_requisitions', 'view_approvals_timeline_widget']));

    Livewire::test(ApprovalsTimelineWidget::class, ['requisitionId' => $requisition->id])
        ->assertCountTableRecords(2)
        ->assertCanSeeTableRecords([$firstNow, $secondBefore])
        ->assertCanNotSeeTableRecords([$firstBefore])
        ->assertSee(__('app.approval.cancelled_after_edit'))
        ->mountAction(TestAction::make('approverDetails')->table($firstNow))
        ->assertActionMounted(TestAction::make('approverDetails')->table($firstNow));

    $history = view('filament.approvals.approver-details', [
        'approval' => $firstNow,
        'attempts' => $approvals->where('user_id', $first->id)->sortByDesc('id')->values(),
        'total' => 1,
    ])->render();

    expect($history)
        ->toContain('Уточните смету.')
        ->toContain(ApprovalStatus::Rejected->label())
        ->toContain(ApprovalStatus::Queued->label())
        ->toContain(__('app.approval.cancelled_after_edit'));
});

it('returns a rejected requisition to the author with the same chain queued again', function () {
    $author = requisitionUser();
    $first = requisitionUser();
    $second = requisitionUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create(['author_id' => $author->id]);

    actingAs($first);
    app(ApprovalWorkflow::class)->reject($requisition->fresh()->load('approvals'), $first, 'Уточните смету.');

    actingAs($author);
    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionVisible('returnToWork')
        ->assertActionHidden('recall')
        ->callAction('returnToWork')
        ->assertNotified(__('app.approval.message.returned_to_work'));

    $requisition = $requisition->fresh()->load('approvals');
    $live = $requisition->activeApprovals();

    expect($requisition->status)->toBe(RequisitionStatus::Draft)
        ->and($requisition->submitted_at)->toBeNull()
        ->and($live->pluck('user_id')->all())->toBe([$first->id, $second->id])
        ->and($live->pluck('status')->unique()->all())->toBe([ApprovalStatus::Queued])
        ->and($live->first()->round)->toBe(2)
        ->and($requisition->approvals->where('round', 1)->firstWhere('user_id', $first->id)->displayStatus())->toBe(ApprovalStatus::Rejected);

    expect(Livewire::test(ViewRequisition::class, ['record' => $requisition->id])->html())
        ->toContain('Уточните смету.');
});

it('lets only holders of approve_requisitions approve, reject or see the awaiting queue', function () {
    $author = requisitionUser();
    $approver = requisitionUser(['view_any_requisition', 'view_requisition']);
    $requisition = Requisition::factory()->inReview([$approver])->create(['author_id' => $author->id]);

    actingAs($approver);

    expect(Requisition::query()->awaiting($approver)->count())->toBe(0)
        ->and($requisition->fresh()->load('approvals')->awaitsApprovalFrom($approver))->toBeFalse()
        ->and(fn () => app(ApprovalWorkflow::class)->approve($requisition->fresh()->load('approvals'), $approver))
        ->toThrow(RuntimeException::class, __('app.approval.error.not_allowed'));

    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');

    $approver->givePermissionTo(Permission::findOrCreate('approve_requisitions', 'web'));
    actingAs($approver->fresh());

    expect(Requisition::query()->awaiting($approver->fresh())->count())->toBe(1);

    Livewire::test(ViewRequisition::class, ['record' => $requisition->id])
        ->assertActionVisible('approve')
        ->assertActionVisible('reject');
});
