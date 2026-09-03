<?php

use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Sponsors\Pages\ViewSponsor;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Project;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the contact view with all its data', function () {
    actingAs(userWithPermission(
        'view_any_contact',
        'view_contact',
        'view_all_contracts',
        'view_contact_bank_accounts_table_widget',
        'view_counterparty_contracts_table_widget',
        'view_counterparty_projects_table_widget',
    ));

    $contact = Contact::factory()->create([
        'type' => Contact::TYPE_LEGAL,
        'name' => ['ru' => 'ZAMIN DMC', 'uz' => 'ZAMIN DMC', 'en' => 'ZAMIN DMC'],
        'inn' => '311343097',
    ]);

    BankAccount::factory()->create([
        'contact_id' => $contact->id,
        'account_number' => '20208000107076480001',
    ]);

    $project = Project::factory()->international()->create(['name' => 'ZAMIN-EXPO']);

    Contract::factory()->create([
        'contact_id' => $contact->id,
        'project_id' => $project->id,
        'contract_type_id' => ContractType::factory()->income(),
        'number' => 'ZAMIN-CONTRACT-1',
    ])->forceFill(['status' => Contract::STATUS_APPROVED])->saveQuietly();

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertOk()
        ->assertSee('311343097')
        ->assertSee('20208000107076480001')
        ->assertSee('ZAMIN-CONTRACT-1')
        ->assertSee('ZAMIN-EXPO');
});

it('shows a manager only their own contracts on the contact view', function () {
    $project = Project::factory()->international()->create();
    $contact = Contact::factory()->create(['type' => Contact::TYPE_LEGAL]);

    $manager = userWithPermission(
        'view_any_contact',
        'view_contact',
        'view_counterparty_contracts_table_widget',
        'view_counterparty_projects_table_widget',
    );

    Contract::factory()->create(['contact_id' => $contact->id, 'responsible_id' => $manager->id, 'number' => 'MINE-CT']);
    Contract::factory()->create(['contact_id' => $contact->id, 'responsible_id' => User::factory()->create()->id, 'number' => 'FOREIGN-CT']);

    actingAs($manager);

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertSee('MINE-CT')
        ->assertDontSee('FOREIGN-CT');
});

it('renders the sponsor view with its requisites', function () {
    actingAs(userWithPermission('view_any_sponsor', 'view_sponsor'));

    $sponsor = Sponsor::factory()->create([
        'name' => 'Uzbekistan Airways',
        'inn' => '200123456',
        'description' => 'Официальный авиаперевозчик',
    ]);

    Livewire::test(ViewSponsor::class, ['record' => $sponsor->id])
        ->assertOk()
        ->assertSee('Uzbekistan Airways')
        ->assertSee('200123456')
        ->assertSee('Официальный авиаперевозчик');
});
