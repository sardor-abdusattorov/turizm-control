<?php

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractItem;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\HtmlString;

uses(RefreshDatabase::class);

/**
 * Template rendering is the bridge between the editor (admin-side)
 * and the PDF (output). It must escape user content but emit
 * system-generated HTML blocks raw — these tests pin that boundary
 * down, plus the system placeholder contract.
 */

it('escapes plain scalar values when substituting placeholders', function (): void {
    $template = ContractTemplate::factory()->create([
        'content' => [
            'ru' => 'Имя: {{name}}',
            'uz' => 'Имя: {{name}}',
            'en' => 'Имя: {{name}}',
        ],
    ]);

    $html = $template->render(['name' => '<script>x</script>'], 'ru');

    expect($html)->toContain('&lt;script&gt;x&lt;/script&gt;');
    expect($html)->not->toContain('<script>x</script>');
});

it('emits HtmlString placeholder values raw without escaping', function (): void {
    $template = ContractTemplate::factory()->create([
        'content' => [
            'ru' => '<div>{{block}}</div>',
            'uz' => '<div>{{block}}</div>',
            'en' => '<div>{{block}}</div>',
        ],
    ]);

    $html = $template->render(['block' => new HtmlString('<p>safe</p>')], 'ru');

    expect($html)->toBe('<div><p>safe</p></div>');
});

it('substitutes nothing when a placeholder is missing', function (): void {
    $template = ContractTemplate::factory()->create([
        'content' => [
            'ru' => '{{missing}}',
            'uz' => '{{missing}}',
            'en' => '{{missing}}',
        ],
    ]);

    expect($template->render([], 'ru'))->toBe('{{missing}}');
});

it('systemPlaceholders contains every key the template editor advertises', function (): void {
    $contract = Contract::factory()->create();

    $keys = array_keys($contract->systemPlaceholders('ru'));

    expect($keys)->toContain(
        'logo',
        'organization_name',
        'contract_number',
        'amount',
        'currency_code',
        'currency_name',
        'contact_name',
        'contact_inn',
        'contact_pinfl',
        'contact_address',
        'contact_phone',
        'contact_email',
        'contact_person',
        'manager_name',
        'manager_position',
        'deadline',
        'signed_at',
        'created_at',
        'today',
        'approvers_block',
        'purchase_items',
    );
});

it('renders the approvers block as a table once approvers exist', function (): void {
    $contract = Contract::factory()->create();
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => User::factory()->create(['name' => 'Test Approver'])->id,
        'order' => 1,
    ]);

    $html = $contract->renderApproversBlock('ru');

    expect($html)->toContain('<table');
    expect($html)->toContain('Test Approver');
});

it('renders the items table once items exist', function (): void {
    $contract = Contract::factory()->create();
    ContractItem::factory()->create([
        'contract_id' => $contract->id,
        'specification' => ['ru' => 'Бумага А4', 'uz' => 'A4 qog\'oz', 'en' => 'A4 paper'],
        'amount' => 12345.67,
    ]);

    $html = $contract->renderPurchaseItems('ru');

    expect($html)->toContain('<table');
    expect($html)->toContain('Бумага А4');
    expect($html)->toContain('12 345.67');
});

it('returns empty approvers block when no approvers exist', function (): void {
    $contract = Contract::factory()->create();

    expect($contract->renderApproversBlock('ru'))->toBe('');
});

it('returns empty items table when no items exist', function (): void {
    $contract = Contract::factory()->create();

    expect($contract->renderPurchaseItems('ru'))->toBe('');
});

it('amount placeholder is formatted with two decimals and thousands separator', function (): void {
    $contract = Contract::factory()->create(['amount' => 1234567.5]);

    $placeholders = $contract->systemPlaceholders('ru');

    expect($placeholders['amount'])->toBe('1 234 567.50');
});

it('approvers_block placeholder is wrapped in HtmlString so render emits raw', function (): void {
    $contract = Contract::factory()->create();
    ContractApprover::factory()->create([
        'contract_id' => $contract->id,
        'user_id' => User::factory()->create()->id,
        'order' => 1,
    ]);

    $placeholders = $contract->fresh()->systemPlaceholders('ru');

    expect($placeholders['approvers_block'])->toBeInstanceOf(HtmlString::class);
});
