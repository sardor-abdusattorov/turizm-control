<?php

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Telegram\BotMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function botUser(array $abilities = []): User
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach ($abilities as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user->fresh();
}

it('puts requisitions and projects on the main menu only when there is something there', function () {
    $menu = app(BotMenuBuilder::class);

    $stranger = botUser();
    $callbacks = collect($menu->mainMenu($stranger)['keyboard'])
        ->flatten(1)
        ->pluck('callback_data')
        ->filter();

    expect($callbacks)->not->toContain('rq:1')
        ->not->toContain('rqm:1')
        ->not->toContain('pj:1');

    $author = botUser(['view_any_project']);
    $approver = botUser();
    Requisition::factory()->inReview([$approver])->create(['author_id' => $author->id]);

    $authorCallbacks = collect($menu->mainMenu($author->fresh())['keyboard'])->flatten(1)->pluck('callback_data');
    $approverCallbacks = collect($menu->mainMenu($approver->fresh())['keyboard'])->flatten(1)->pluck('callback_data');

    expect($authorCallbacks)->toContain('rqm:1')->toContain('pj:1')
        ->and($approverCallbacks)->toContain('rq:1');
});

it('lists the requisitions awaiting a given approver and nobody else', function () {
    $approver = botUser();
    $other = botUser();

    $mine = Requisition::factory()->inReview([$approver])->create(['number' => 'ЗВ-2026-100']);
    Requisition::factory()->inReview([$other])->create(['number' => 'ЗВ-2026-200']);

    $list = app(BotMenuBuilder::class)->requisitionAwaitingList($approver, 1);

    expect($list['text'])->toContain('ЗВ-2026-100')->not->toContain('ЗВ-2026-200');

    $opens = collect($list['keyboard'])->flatten(1)->pluck('callback_data')->filter();

    expect($opens)->toContain('rqv:'.$mine->id);
});

it('renders a requisition card with the chain and the decision buttons', function () {
    $author = botUser();
    $first = botUser();
    $second = botUser();

    $requisition = Requisition::factory()->inReview([$first, $second])->create([
        'author_id' => $author->id,
        'number' => 'ЗВ-2026-007',
        'title' => 'Канцелярия',
    ]);

    $menu = app(BotMenuBuilder::class);
    $card = $menu->requisitionCard($requisition->fresh()->load('approvals'), $first);

    expect($card['text'])->toContain('ЗВ-2026-007')->toContain('Канцелярия')->toContain($first->name);

    $buttons = collect($card['keyboard'])->flatten(1)->pluck('callback_data')->filter();

    expect($buttons)->toContain('rqa:'.$requisition->id)->toContain('rqr:'.$requisition->id);
});

it('offers only a veto to an approver still waiting their turn', function () {
    $first = botUser();
    $second = botUser();
    $requisition = Requisition::factory()->inReview([$first, $second])->create();

    $card = app(BotMenuBuilder::class)->requisitionCard($requisition->fresh()->load('approvals'), $second);
    $buttons = collect($card['keyboard'])->flatten(1)->pluck('callback_data')->filter();

    expect($buttons)->toContain('rqr:'.$requisition->id)
        ->not->toContain('rqa:'.$requisition->id);
});

it('gives an outsider no decision buttons at all', function () {
    $stranger = botUser();
    $requisition = Requisition::factory()->inReview()->create();

    $card = app(BotMenuBuilder::class)->requisitionCard($requisition->fresh()->load('approvals'), $stranger);
    $buttons = collect($card['keyboard'])->flatten(1)->pluck('callback_data')->filter();

    expect($buttons)->not->toContain('rqa:'.$requisition->id)
        ->not->toContain('rqr:'.$requisition->id);
});

it('shows a settled requisition without decision buttons', function () {
    $approver = botUser();
    $requisition = Requisition::factory()->approved([$approver])->create();

    $card = app(BotMenuBuilder::class)->requisitionCard($requisition->fresh()->load('approvals'), $approver);
    $buttons = collect($card['keyboard'])->flatten(1)->pluck('callback_data')->filter();

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and($buttons)->not->toContain('rqa:'.$requisition->id);
});

it('carries an approver comment into the card', function () {
    $approver = botUser();
    $requisition = Requisition::factory()->inReview([$approver])->create();

    $requisition->fresh()->load('approvals')->approvals
        ->firstWhere('status', ApprovalStatus::Pending)
        ->approve('Согласовано, вопросов нет.');

    $card = app(BotMenuBuilder::class)->requisitionCard($requisition->fresh()->load('approvals'), $approver);

    expect($card['text'])->toContain('Согласовано, вопросов нет.');
});

it('lists active projects and opens a card for one', function () {
    $running = Project::factory()->international()->create([
        'name' => 'ITB Berlin 2026',
        'venue' => 'Messe Berlin',
        'status' => true,
    ]);
    Project::factory()->create(['name' => 'ЗАКРЫТЫЙ ПРОЕКТ', 'status' => false]);

    $menu = app(BotMenuBuilder::class);
    $list = $menu->projectList(1);

    expect($list['text'])->toContain('ITB Berlin 2026')->not->toContain('ЗАКРЫТЫЙ ПРОЕКТ');

    expect(collect($list['keyboard'])->flatten(1)->pluck('callback_data')->filter())
        ->toContain('pjv:'.$running->id);

    $card = $menu->projectCard($running);

    expect($card['text'])->toContain('ITB Berlin 2026')->toContain('Messe Berlin');
});

it('paginates a long register rather than sending one wall of text', function () {
    $approver = botUser();

    foreach (range(1, 7) as $i) {
        Requisition::factory()->inReview([$approver])->create(['number' => 'ЗВ-2026-'.(300 + $i)]);
    }

    $list = app(BotMenuBuilder::class)->requisitionAwaitingList($approver, 1);
    $callbacks = collect($list['keyboard'])->flatten(1)->pluck('callback_data')->filter();

    expect($callbacks)->toContain('rq:2');
});
