<?php

use App\Enums\TeamRole;
use App\Enums\Weekday;
use App\Models\AppointmentType;
use App\Models\User;
use Livewire\Livewire;

test('admins can create, edit and delete appointment types', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.appointment-types')
        ->call('createAppointmentType')
        ->set('name', '  Desobsessão  ')
        ->set('requiresRecord', true)
        ->set('dailyLimit', '20')
        ->call('saveAppointmentType')
        ->assertHasNoErrors();

    $appointmentType = $team->appointmentTypes()->sole();

    expect($appointmentType)
        ->name->toBe('Desobsessão')
        ->requires_record->toBeTrue()
        ->daily_limit->toBe(20);

    $component
        ->call('editAppointmentType', $appointmentType->id)
        ->assertSet('name', 'Desobsessão')
        ->assertSet('requiresRecord', true)
        ->assertSet('dailyLimit', '20')
        ->set('name', 'Desobsessão coletiva')
        ->set('requiresRecord', false)
        ->set('dailyLimit', '')
        ->call('saveAppointmentType')
        ->assertHasNoErrors();

    expect($appointmentType->fresh())
        ->name->toBe('Desobsessão coletiva')
        ->requires_record->toBeFalse()
        ->daily_limit->toBeNull();

    $component
        ->call('confirmDeleteAppointmentType', $appointmentType->id)
        ->call('deleteAppointmentType');

    $this->assertSoftDeleted($appointmentType);
});

test('appointment type names must be unique within the team, except for the type being edited', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $existing = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);
    $other = AppointmentType::factory()->for($team)->create(['name' => 'Palestra']);

    Livewire::test('pages::catalogs.appointment-types')
        ->call('editAppointmentType', $other->id)
        ->set('name', 'Passe')
        ->call('saveAppointmentType')
        ->assertHasErrors(['name' => 'unique'])
        ->call('editAppointmentType', $existing->id)
        ->set('dailyLimit', '1000')
        ->call('saveAppointmentType')
        ->assertHasErrors(['dailyLimit' => 'between'])
        ->set('dailyLimit', '10')
        ->call('saveAppointmentType')
        ->assertHasNoErrors();

    expect($existing->fresh()->daily_limit)->toBe(10)
        ->and($other->fresh()->name)->toBe('Palestra');
});

test('admins can restrict an appointment type to the weekdays it takes place', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.appointment-types')
        ->call('createAppointmentType')
        ->set('name', 'Tratamento de cura')
        ->set('weekdays', ['3', '1'])
        ->call('saveAppointmentType')
        ->assertHasNoErrors()
        ->assertSee('Seg, Qua');

    $appointmentType = $team->appointmentTypes()->sole();

    expect($appointmentType->weekdays->all())->toBe([Weekday::Monday, Weekday::Wednesday]);

    $component
        ->call('editAppointmentType', $appointmentType->id)
        ->assertSet('weekdays', ['1', '3'])
        ->set('weekdays', ['8'])
        ->call('saveAppointmentType')
        ->assertHasErrors('weekdays.0')
        ->set('weekdays', [])
        ->call('saveAppointmentType')
        ->assertHasNoErrors()
        ->assertSee('Qualquer dia');

    expect($appointmentType->fresh()->weekdays)->toBeNull();
});

test('members can view appointment types but cannot change them', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $appointmentType = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);

    $this->get(route('catalogs.appointment-types', ['current_team' => $team]))
        ->assertOk()
        ->assertSee('Passe')
        ->assertDontSee('data-test="appointment-type-new-button"', false)
        ->assertDontSee('data-test="appointment-type-actions-trigger"', false)
        ->assertDontSee('data-test="appointment-type-edit-row"', false);

    $component = fn () => Livewire::test('pages::catalogs.appointment-types');

    $component()->call('createAppointmentType')->assertForbidden();
    $component()->set('name', 'Novo')->call('saveAppointmentType')->assertForbidden();
    $component()->call('editAppointmentType', $appointmentType->id)->assertForbidden();
    $component()->call('confirmDeleteAppointmentType', $appointmentType->id)->assertForbidden();

    expect($team->appointmentTypes()->pluck('name')->all())->toBe(['Passe']);
});

test('appointment types of another team cannot be edited', function () {
    actingAsTeamMember(TeamRole::Owner);
    $foreignType = AppointmentType::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    Livewire::test('pages::catalogs.appointment-types')
        ->call('editAppointmentType', $foreignType->id)
        ->assertNotFound();
});
