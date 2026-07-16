<?php

use App\Enums\PaymentStatus;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\ProjectPayment;
use App\Rules\ProjectPaymentWithinPledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function participantWithPledge(float $amount): ProjectParticipant
{
    return ProjectParticipant::factory()->create([
        'project_id' => Project::factory(),
        'amount' => $amount,
        'paid_amount' => 0,
    ]);
}

it('rolls installments up into the participant paid_amount', function () {
    $participant = participantWithPledge(40_000_000);

    ProjectPayment::factory()->create(['project_participant_id' => $participant->id, 'amount' => 10_000_000]);
    ProjectPayment::factory()->create(['project_participant_id' => $participant->id, 'amount' => 15_000_000]);

    $participant->refresh();

    expect((float) $participant->paid_amount)->toBe(25_000_000.0)
        ->and($participant->remainingAmount())->toBe(15_000_000.0)
        ->and($participant->isFullyPaid())->toBeFalse()
        ->and($participant->paymentStatus())->toBe(PaymentStatus::PartiallyPaid);
});

it('marks the participant fully paid once the pledge is met', function () {
    $participant = participantWithPledge(40_000_000);

    ProjectPayment::factory()->create(['project_participant_id' => $participant->id, 'amount' => 40_000_000]);
    $participant->refresh();

    expect($participant->isFullyPaid())->toBeTrue()
        ->and($participant->remainingAmount())->toBe(0.0)
        ->and($participant->paymentStatus())->toBe(PaymentStatus::FullyPaid);
});

it('treats an unpaid pledge as not paid and a zero pledge as settled', function () {
    $unpaid = participantWithPledge(10_000_000);
    expect($unpaid->paymentStatus())->toBe(PaymentStatus::NotPaid);

    $zeroPledge = participantWithPledge(0);
    expect($zeroPledge->paymentStatus())->toBe(PaymentStatus::FullyPaid)
        ->and($zeroPledge->isFullyPaid())->toBeTrue();
});

it('recomputes paid_amount when an installment is deleted', function () {
    $participant = participantWithPledge(40_000_000);

    $first = ProjectPayment::factory()->create(['project_participant_id' => $participant->id, 'amount' => 10_000_000]);
    ProjectPayment::factory()->create(['project_participant_id' => $participant->id, 'amount' => 15_000_000]);

    $first->delete();
    $participant->refresh();

    expect((float) $participant->paid_amount)->toBe(15_000_000.0);
});

it('deletes installments when the participant is removed', function () {
    $participant = participantWithPledge(40_000_000);
    ProjectPayment::factory()->count(2)->create(['project_participant_id' => $participant->id]);

    $participant->delete();

    expect(ProjectPayment::count())->toBe(0);
});

it('rejects an installment above the remaining pledge', function () {
    $participant = participantWithPledge(10_000_000);
    ProjectPayment::factory()->create(['project_participant_id' => $participant->id, 'amount' => 4_000_000]);
    $participant->refresh(); // remaining = 6M

    $rule = new ProjectPaymentWithinPledge($participant);

    $overFailed = false;
    $rule->validate('amount', 8_000_000, function () use (&$overFailed) {
        $overFailed = true;
    });

    $withinFailed = false;
    $rule->validate('amount', 6_000_000, function () use (&$withinFailed) {
        $withinFailed = true;
    });

    expect($overFailed)->toBeTrue()
        ->and($withinFailed)->toBeFalse();
});
