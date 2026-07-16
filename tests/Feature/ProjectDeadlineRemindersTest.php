<?php

use App\Jobs\SendTelegramMessage;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'turizm_bot',
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);
});

function linkedDirector(string $chatId): User
{
    Role::findOrCreate(Contract::DIRECTOR_ROLE, 'web');

    $director = User::factory()->withTelegram($chatId)->create(['status' => User::STATUS_ACTIVE]);
    $director->assignRole(Contract::DIRECTOR_ROLE);

    return $director;
}

it('sends the director a summary and the manager their tail 14 days before start', function () {
    Queue::fake();

    $director = linkedDirector('8001');
    $manager = User::factory()->withTelegram('8002')->create();

    $project = Project::factory()->international()->create([
        'name' => 'ITB-CHINA-TEST',
        'starts_on' => today()->addDays(14),
        'status' => true,
    ]);

    Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => $manager->id,
        'number' => 'TAIL-001',
        'status' => Contract::STATUS_DRAFT,
    ]);

    artisan('projects:send-deadline-reminders')->assertSuccessful();

    Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === '8001'
        && str_contains($job->message, 'ITB-CHINA-TEST'));

    Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === '8002'
        && str_contains($job->message, 'TAIL-001'));
});

it('stays silent for projects outside the threshold marks', function () {
    Queue::fake();

    linkedDirector('8003');

    Project::factory()->international()->create([
        'starts_on' => today()->addDays(10),
        'status' => true,
    ]);

    artisan('projects:send-deadline-reminders')->assertSuccessful();

    Queue::assertNotPushed(SendTelegramMessage::class);
});

it('does not nag managers whose contracts are already approved', function () {
    Queue::fake();

    $manager = User::factory()->withTelegram('8004')->create();

    $project = Project::factory()->international()->create([
        'starts_on' => today()->addDays(7),
        'status' => true,
    ]);

    $contract = Contract::factory()->create([
        'project_id' => $project->id,
        'responsible_id' => $manager->id,
    ]);
    $contract->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

    artisan('projects:send-deadline-reminders')->assertSuccessful();

    Queue::assertNotPushed(
        SendTelegramMessage::class,
        fn (SendTelegramMessage $job) => $job->chatId === '8004',
    );
});
