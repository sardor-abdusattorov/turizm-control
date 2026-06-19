<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Department;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function fillableDocx(): string
{
    $path = tempnam(sys_get_temp_dir(), 'docx');

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(
        'word/document.xml',
        '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p><w:r><w:t>Contract {{number}}</w:t></w:r></w:p></w:body></w:document>'
    );
    $zip->close();

    return $path;
}

function contractCreatorActing(): User
{
    $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);

    foreach (['view_any_contract', 'create_contract'] as $ability) {
        Permission::findOrCreate($ability, 'web');
        $creator->givePermissionTo($ability);
    }

    actingAs($creator->fresh());

    return $creator;
}

it('renders the approval chain picker and a live numbered preview of the selected approvers', function () {
    $department = Department::factory()->create(['code' => 'legal']);
    $first = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);
    $second = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id]);

    contractCreatorActing();

    Livewire::test(CreateContract::class)
        ->assertFormFieldExists('approver_chain')
        ->fillForm(['approver_chain' => [$second->id, $first->id]])
        ->assertHasNoFormErrors(['approver_chain'])
        ->assertSee($first->name)
        ->assertSee($second->name);
});

it('creates approvers in the order they were picked', function () {
    Storage::fake('local');

    $legalDept = Department::factory()->create(['code' => 'legal']);
    $accountingDept = Department::factory()->create(['code' => 'accounting']);
    $first = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legalDept->id]);
    $second = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $accountingDept->id]);

    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create([
        'order_type_id' => $orderType->id,
        'status' => true,
        'language' => 'ru',
    ]);
    Storage::disk('local')->put($template->template_file, file_get_contents(fillableDocx()));

    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    contractCreatorActing();

    // Pick second-then-first to prove the chain follows the selection order.
    Livewire::test(CreateContract::class)
        ->fillForm([
            'number' => 'C-900',
            'order_type_id' => $orderType->id,
            'contract_template_id' => $template->id,
            'contact_id' => $contact->id,
            'title' => 'Picker order test',
            'amount' => 1000,
            'currency_id' => $currency->id,
            'approver_chain' => [$second->id, $first->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $approvers = Contract::where('number', 'C-900')->firstOrFail()
        ->approvers()->orderBy('order')->get();

    expect($approvers)->toHaveCount(2)
        ->and($approvers[0]->user_id)->toBe($second->id)
        ->and($approvers[0]->order)->toBe(1)
        ->and($approvers[1]->user_id)->toBe($first->id)
        ->and($approvers[1]->order)->toBe(2);
});

it('rejects creating a contract whose chain has no accounting approver', function () {
    Storage::fake('local');

    $legalDept = Department::factory()->create(['code' => 'legal']);
    $lawyer = User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $legalDept->id]);

    $orderType = OrderType::factory()->create();
    $template = ContractTemplate::factory()->create([
        'order_type_id' => $orderType->id, 'status' => true, 'language' => 'ru',
    ]);
    Storage::disk('local')->put($template->template_file, file_get_contents(fillableDocx()));
    $contact = Contact::factory()->create(['status' => true]);
    $currency = Currency::factory()->create(['status' => true]);

    contractCreatorActing();

    Livewire::test(CreateContract::class)
        ->fillForm([
            'number' => 'C-901',
            'order_type_id' => $orderType->id,
            'contract_template_id' => $template->id,
            'contact_id' => $contact->id,
            'title' => 'Missing accountant',
            'amount' => 1000,
            'currency_id' => $currency->id,
            'approver_chain' => [$lawyer->id],
        ])
        ->call('create')
        ->assertHasFormErrors(['approver_chain']);

    expect(Contract::where('number', 'C-901')->exists())->toBeFalse();
});

it('rejects creating a contract with an empty chain', function () {
    contractCreatorActing();

    Livewire::test(CreateContract::class)
        ->fillForm(['approver_chain' => []])
        ->call('create')
        ->assertHasFormErrors(['approver_chain']);
});
