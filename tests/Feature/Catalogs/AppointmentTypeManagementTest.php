<?php

use App\Enums\TeamRole;
use App\Enums\Weekday;
use App\Models\AppointmentType;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('admins can create, edit and delete appointment types', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.appointment-types')
        ->set('form.name', '  Desobsessão  ')
        ->set('form.requiresRecord', true)
        ->set('form.dailyLimit', '20')
        ->call('saveAppointmentType')
        ->assertHasNoErrors();

    $appointmentType = $team->appointmentTypes()->sole();

    expect($appointmentType)
        ->name->toBe('Desobsessão')
        ->requires_record->toBeTrue()
        ->daily_limit->toBe(20);

    $component
        ->call('editAppointmentType', $appointmentType->id)
        ->assertSet('form.name', 'Desobsessão')
        ->assertSet('form.requiresRecord', true)
        ->assertSet('form.dailyLimit', '20')
        ->set('form.name', 'Desobsessão coletiva')
        ->set('form.requiresRecord', false)
        ->set('form.dailyLimit', '')
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
        ->set('form.name', 'Passe')
        ->call('saveAppointmentType')
        ->assertHasErrors(['form.name' => 'unique'])
        ->call('editAppointmentType', $existing->id)
        ->set('form.dailyLimit', '1000')
        ->call('saveAppointmentType')
        ->assertHasErrors(['form.dailyLimit' => 'between'])
        ->set('form.dailyLimit', '10')
        ->call('saveAppointmentType')
        ->assertHasNoErrors();

    expect($existing->fresh()->daily_limit)->toBe(10)
        ->and($other->fresh()->name)->toBe('Palestra');
});

test('admins can restrict an appointment type to the weekdays it takes place', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.appointment-types')
        ->set('form.name', 'Tratamento de cura')
        ->set('form.weekdays', ['3', '1'])
        ->call('saveAppointmentType')
        ->assertHasNoErrors()
        ->assertSee('Seg, Qua');

    $appointmentType = $team->appointmentTypes()->sole();

    expect($appointmentType->weekdays->all())->toBe([Weekday::Monday, Weekday::Wednesday]);

    $component
        ->call('editAppointmentType', $appointmentType->id)
        ->assertSet('form.weekdays', ['1', '3'])
        ->set('form.weekdays', ['8'])
        ->call('saveAppointmentType')
        ->assertHasErrors('form.weekdays.0')
        ->set('form.weekdays', [])
        ->call('saveAppointmentType')
        ->assertHasNoErrors()
        ->assertSee('Qualquer dia');

    expect($appointmentType->fresh()->weekdays)->toBeNull();
});

test('members cannot open the appointment types', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::catalogs.appointment-types')->assertForbidden();
});

test('appointment types of another team cannot be edited', function () {
    actingAsTeamMember(TeamRole::Owner);
    $foreignType = AppointmentType::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    Livewire::test('pages::catalogs.appointment-types')
        ->call('editAppointmentType', $foreignType->id)
        ->assertNotFound();
});

test('closing the appointment type flyout clears the form so it opens empty for a new type', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    $appointmentType = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);

    Livewire::test('pages::catalogs.appointment-types')
        ->call('editAppointmentType', $appointmentType->id)
        ->set('form.dailyLimit', '1000')
        ->call('saveAppointmentType')
        ->assertHasErrors('form.dailyLimit')
        ->call('closeAppointmentType')
        ->assertSet('form.editingId', null)
        ->assertSet('form.name', '')
        ->assertSet('form.dailyLimit', '')
        ->assertHasNoErrors();
});

test('closing an untouched appointment type flyout skips the re-render', function () {
    actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.appointment-types')->call('closeAppointmentType');

    expect($component->effects)->not->toHaveKey('html');

    $component->set('form.name', 'Passe')->call('closeAppointmentType');

    expect($component->effects)->toHaveKey('html');
});

test('daily limit errors name the field by its translated attribute', function () {
    actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.appointment-types')
        ->set('form.name', 'Passe')
        ->set('form.dailyLimit', '1000')
        ->call('saveAppointmentType');

    expect($component->errors()->first('form.dailyLimit'))->toContain('limite diário de agendamentos');
});

test('the browser cannot choose which appointment type the form edits', function () {
    actingAsTeamMember(TeamRole::Admin);

    Livewire::test('pages::catalogs.appointment-types')
        ->set('form.editingId', 1);
})->throws(CannotUpdateLockedPropertyException::class);
