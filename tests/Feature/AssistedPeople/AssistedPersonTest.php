<?php

use App\Enums\BrazilianState;
use App\Enums\TeamRole;
use App\Models\AssistedPerson;
use Livewire\Livewire;

test('assisted people pages can be rendered by a team member', function () {
    [$user, $team] = memberOfTeam();

    $assistedPerson = AssistedPerson::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user);

    $this->get(route('assisted-people.index'))->assertOk();
    $this->get(route('assisted-people.create'))->assertOk();
    $this->get(route('assisted-people.edit', $assistedPerson))->assertOk();
});

test('the list only shows assisted people of the current team', function () {
    [$user, $team] = memberOfTeam();

    $ours = AssistedPerson::factory()->create(['team_id' => $team->id, 'name' => 'Ana Nossa']);
    $theirs = AssistedPerson::factory()->create(['name' => 'Bruno Alheio']);

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.index')
        ->assertSee($ours->name)
        ->assertDontSee($theirs->name);
});

test('the list can be searched by name, email and masked phone', function () {
    [$user, $team] = memberOfTeam();

    AssistedPerson::factory()->create([
        'team_id' => $team->id,
        'name' => 'Joana Ribeiro',
        'email' => 'joana@example.com',
        'phone' => '11987654321',
    ]);

    AssistedPerson::factory()->create([
        'team_id' => $team->id,
        'name' => 'Carlos Souza',
        'email' => 'carlos@example.com',
        'phone' => '11912345678',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::assisted-people.index');

    $component->set('search', 'Joana')
        ->assertSee('Joana Ribeiro')
        ->assertDontSee('Carlos Souza');

    $component->set('search', 'carlos@example.com')
        ->assertSee('Carlos Souza')
        ->assertDontSee('Joana Ribeiro');

    $component->set('search', '(11) 98765-4321')
        ->assertSee('Joana Ribeiro')
        ->assertDontSee('Carlos Souza');
});

test('an assisted person can be created', function () {
    [$user, $team] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues())
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assisted_people', [
        'team_id' => $team->id,
        'name' => 'Maria da Silva',
        'email' => 'maria@example.com',
        'street' => 'Avenida Paulista',
        'number' => '1000',
        'complement' => 'Apto 42',
        'district' => 'Bela Vista',
        'state' => 'SP',
    ]);
});

test('creating an assisted person requires a name, birth date and phone', function () {
    [$user] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues(['name' => '', 'birth_date' => '', 'phone' => '']))
        ->call('save')
        ->assertHasErrors([
            'form.name' => 'required',
            'form.birth_date' => 'required',
            'form.phone' => 'required',
        ]);

    $this->assertDatabaseCount('assisted_people', 0);
});

test('the phone must hold ten or eleven digits', function () {
    [$user] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues(['phone' => '(11) 9876']))
        ->call('save')
        ->assertHasErrors('form.phone');
});

test('the phone and city are normalized before being stored', function () {
    [$user, $team] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues([
            'phone' => '(11) 98765-4321',
            'postal_code' => '01310-100',
            'city' => '  são   paulo ',
            'name' => '  Maria   da  Silva ',
        ]))
        ->call('save')
        ->assertHasNoErrors();

    $assistedPerson = AssistedPerson::where('team_id', $team->id)->sole();

    expect($assistedPerson->phone)->toBe('11987654321')
        ->and($assistedPerson->postal_code)->toBe('01310100')
        ->and($assistedPerson->city)->toBe('São Paulo')
        ->and($assistedPerson->name)->toBe('Maria da Silva');
});

test('an assisted person can be created without an address', function () {
    [$user, $team] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues([
            'postal_code' => '',
            'street' => '',
            'number' => '',
            'complement' => '',
            'district' => '',
            'city' => '',
            'state' => '',
        ]))
        ->call('save')
        ->assertHasNoErrors();

    $assistedPerson = AssistedPerson::where('team_id', $team->id)->sole();

    expect($assistedPerson->city)->toBeNull()
        ->and($assistedPerson->state)->toBeNull()
        ->and($assistedPerson->hasAddress())->toBeFalse();

    Livewire::test('pages::assisted-people.index')->assertSee('—');

    $this->get(route('assisted-people.edit', $assistedPerson))->assertOk();
});

test('an assisted person can be updated', function () {
    [$user, $team] = memberOfTeam();

    $assistedPerson = AssistedPerson::factory()->create([
        'team_id' => $team->id,
        'name' => 'Nome Antigo',
        'complement' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.edit', ['assistedPerson' => $assistedPerson])
        ->set('form.name', 'Nome Novo')
        ->set('form.complement', 'Fundos')
        ->set('form.state', BrazilianState::MinasGerais->value)
        ->call('save')
        ->assertHasNoErrors();

    $assistedPerson->refresh();

    expect($assistedPerson->name)->toBe('Nome Novo')
        ->and($assistedPerson->complement)->toBe('Fundos')
        ->and($assistedPerson->state)->toBe(BrazilianState::MinasGerais);
});

test('an assisted person can be soft deleted by an admin', function () {
    [$user, $team] = memberOfTeam(TeamRole::Admin);

    $assistedPerson = AssistedPerson::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.index')
        ->call('confirmDelete', $assistedPerson->id)
        ->call('deleteAssistedPerson')
        ->assertHasNoErrors();

    $this->assertSoftDeleted($assistedPerson);
});

test('the delete confirmation names the person being deleted', function () {
    [$user, $team] = memberOfTeam(TeamRole::Admin);

    AssistedPerson::factory()->create(['team_id' => $team->id, 'name' => 'Ana Primeira']);
    $target = AssistedPerson::factory()->create(['team_id' => $team->id, 'name' => 'Bruno Segundo']);

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.index')
        ->call('confirmDelete', $target->id)
        ->assertSee('This will remove "Bruno Segundo" from the list of assisted people.');
});

test('a member cannot delete an assisted person', function () {
    [$user, $team] = memberOfTeam();

    $assistedPerson = AssistedPerson::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.index')
        ->call('confirmDelete', $assistedPerson->id)
        ->call('deleteAssistedPerson')
        ->assertForbidden();

    expect($assistedPerson->fresh()->trashed())->toBeFalse();
});

test('an assisted person of another team cannot be edited', function () {
    [$user, $team] = memberOfTeam();

    $theirs = AssistedPerson::factory()->create();

    $this->actingAs($user)
        ->get(route('assisted-people.edit', [
            'current_team' => $team->slug,
            'assistedPerson' => $theirs->id,
        ]))
        ->assertForbidden();
});
