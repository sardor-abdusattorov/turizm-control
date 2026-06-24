<?php

use App\Enums\PaymentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Widgets\ContractStatsWidget;
use App\Filament\Widgets\ContractsTrendChartWidget;
use App\Filament\Widgets\Dashboard\ApprovalHealthWidget;
use App\Filament\Widgets\Dashboard\DashboardHeaderWidget;
use App\Filament\Widgets\Dashboard\MyApprovalQueueWidget;
use App\Filament\Widgets\Dashboard\MyContractsInReviewWidget;
use App\Filament\Widgets\Dashboard\OutstandingPaymentsWidget;
use App\Filament\Widgets\LatestPaymentsWidget;
use App\Filament\Widgets\PaymentStatsWidget;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Dashboard\DashboardContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function dashboardUser(): User
{
    $user = User::factory()->create();

    foreach (['view_any_contract', 'view_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $user->givePermissionTo($ability);
    }

    return $user;
}

it('hides the finance widgets from a user without their permissions', function () {
    actingAs(User::factory()->create());

    expect(PaymentStatsWidget::canView())->toBeFalse()
        ->and(OutstandingPaymentsWidget::canView())->toBeFalse();
});

it('shows the finance widgets to a user granted their permissions', function () {
    actingAs(userWithPermission('view_payment_stats_widget', 'view_outstanding_payments_widget'));

    expect(PaymentStatsWidget::canView())->toBeTrue()
        ->and(OutstandingPaymentsWidget::canView())->toBeTrue();
});

it('offers a create-contract action in the header to users who may create', function () {
    $manager = User::factory()->create();
    Permission::findOrCreate('create_contract', 'web');
    $manager->givePermissionTo('create_contract');
    actingAs($manager->fresh());

    Livewire::test(DashboardHeaderWidget::class)
        ->assertSee(__('app.action.create_contract'));
});

it('hides the create-contract action from users who cannot create', function () {
    actingAs(dashboardUser());

    Livewire::test(DashboardHeaderWidget::class)
        ->assertDontSee(__('app.action.create_contract'));
});

it('greets the user as the dashboard page heading', function () {
    actingAs(dashboardUser());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('app.dashboard.greeting'));
});

it('shows the author their own in-review contracts, not drafts or others', function () {
    $manager = dashboardUser();
    $manager->givePermissionTo(Permission::findOrCreate('view_my_contracts_in_review_widget', 'web'));

    $inReview = Contract::factory()->inReview()->create(['responsible_id' => $manager->id]);
    $draft = Contract::factory()->create(['responsible_id' => $manager->id]);
    $someoneElses = Contract::factory()->inReview()->create();

    actingAs($manager);

    expect(MyContractsInReviewWidget::canView())->toBeTrue();

    Livewire::test(MyContractsInReviewWidget::class)
        ->assertCanSeeTableRecords([$inReview])
        ->assertCanNotSeeTableRecords([$draft, $someoneElses]);
});

it('lists approved-but-unpaid contracts in the outstanding widget for finance', function () {
    $user = dashboardUser();
    $user->assignRole(Role::findOrCreate('accountant', 'web'));
    foreach (['view_all_contracts', 'view_outstanding_payments_widget'] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    $unpaid = Contract::factory()->approved()->create(['amount' => 1000, 'paid_percent' => 0]);
    $paid = Contract::factory()->approved()->create([
        'amount' => 1000,
        'paid_percent' => 100,
        'payment_status' => PaymentStatus::FullyPaid->value,
    ]);

    actingAs($user->fresh());

    expect(OutstandingPaymentsWidget::canView())->toBeTrue();

    Livewire::test(OutstandingPaymentsWidget::class)
        ->assertCanSeeTableRecords([$unpaid])
        ->assertCanNotSeeTableRecords([$paid]);
});

it('paginates each finance table under its own query-string key', function () {
    $user = dashboardUser();
    $user->assignRole(Role::findOrCreate('accountant', 'web'));
    actingAs($user->fresh());

    $outstanding = Livewire::test(OutstandingPaymentsWidget::class)->instance()->getTablePaginationPageName();
    $latest = Livewire::test(LatestPaymentsWidget::class)->instance()->getTablePaginationPageName();

    // Distinct names mean paging one table no longer moves the other.
    expect($outstanding)->toBe('outstandingPaymentsPage')
        ->and($latest)->toBe('latestPaymentsPage')
        ->and($outstanding)->not->toBe($latest);
});

it('paginates each manager dashboard table under its own query-string key', function () {
    actingAs(dashboardUser());

    $inReview = Livewire::test(MyContractsInReviewWidget::class)->instance()->getTablePaginationPageName();
    $queue = Livewire::test(MyApprovalQueueWidget::class)->instance()->getTablePaginationPageName();

    // Each manager table pages independently of the other and of the finance tables.
    expect($inReview)->toBe('myContractsInReviewPage')
        ->and($queue)->toBe('myApprovalQueuePage')
        ->and($inReview)->not->toBe($queue);
});

it('renders the live SLA badge column without erroring', function () {
    $user = dashboardUser();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $user->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_PENDING,
        'due_at' => now()->addDay(),
    ]);

    actingAs($user);

    // The "Due" column renders the sla-countdown partial (which registers its
    // Alpine helper via @assets) — make sure that compiles and renders.
    Livewire::test(MyApprovalQueueWidget::class)
        ->assertOk()
        ->assertSee($contract->number);
});

it('renders the approval health widget for the director', function () {
    $user = dashboardUser();
    $user->assignRole(Role::findOrCreate('director', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('view_approval_health_widget', 'web'));

    actingAs($user->fresh());

    expect(ApprovalHealthWidget::canView())->toBeTrue();

    Livewire::test(ApprovalHealthWidget::class)->assertOk();
});

it('reports an on-time rate once decisions carry due dates', function () {
    Cache::forget('dashboard.approval_health');

    $user = dashboardUser();
    $user->assignRole(Role::findOrCreate('director', 'web'));

    $contract = Contract::factory()->approved()->create();
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $user->id,
        'order' => 1,
        'status' => ContractApprover::STATUS_APPROVED,
        'acted_at' => now()->subDays(3),
        'due_at' => now()->subDays(2), // acted before due → on-time
    ]);

    actingAs($user->fresh());

    Livewire::test(ApprovalHealthWidget::class)
        ->assertOk()
        ->assertSee('100%');
});

it('builds an eight-week per-status sparkline series for the manager strip', function () {
    $manager = dashboardUser();

    Contract::factory()->inReview()->create(['responsible_id' => $manager->id]);
    Contract::factory()->create(['responsible_id' => $manager->id]); // draft
    Contract::factory()->inReview()->create(['responsible_id' => $manager->id, 'updated_at' => now()->subMonths(6)]);

    actingAs($manager);

    $trends = app(DashboardContext::class)->managerStatusTrends();

    expect($trends['in_review'])->toHaveCount(8)
        ->and($trends['drafts'])->toHaveCount(8)
        ->and($trends['rejected'])->toHaveCount(8)
        ->and($trends['in_review'][7])->toBe(1)  // this week
        ->and($trends['drafts'][7])->toBe(1)
        ->and(array_sum($trends['in_review']))->toBe(1) // the 6-month-old row is outside the window
        ->and(array_sum($trends['rejected']))->toBe(0);
});

it('renders the contract stats widget with a count of contracts awaiting the user', function () {
    $user = dashboardUser();
    $contract = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => $user->id,
        'order' => 1,
        'due_at' => now()->subDay(),
    ]);

    actingAs($user);

    Livewire::test(ContractStatsWidget::class)->assertOk();
});

it('renders the contracts trend chart with grouped monthly aggregates', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    Contract::factory()->count(2)->create(['created_at' => now()->subMonth()]);
    Contract::factory()->approved()->create();

    actingAs($user->fresh());

    Livewire::test(ContractsTrendChartWidget::class)->assertOk();
});

it('renders the list page tabs and filters to contracts awaiting the user', function () {
    $user = dashboardUser();

    $mine = Contract::factory()->create(['status' => Contract::STATUS_IN_REVIEW]);
    ContractApprover::factory()->create([
        'contract_id' => $mine->id,
        'user_id' => $user->id,
        'order' => 1,
    ]);

    $other = Contract::factory()->create(['status' => Contract::STATUS_DRAFT]);

    actingAs($user);

    Livewire::test(ListContracts::class, ['activeTab' => 'awaiting_me'])
        ->assertOk()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});
