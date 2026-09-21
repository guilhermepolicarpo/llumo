<?php

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\BrazilianState;
use App\Enums\TeamRole;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('members can open the appointment pages', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->get(route('appointments.index', ['current_team' => $team]))->assertOk();
    $this->get(route('appointments.create', ['current_team' => $team]))->assertOk();
    $this->get(route('appointments.edit', ['current_team' => $team, 'appointment' => $appointment]))->assertOk();
});

test('members can schedule an appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointmentType = AppointmentType::factory()->for($team)->create();
    $assistedPerson = AssistedPerson::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->assertSet('scheduledOn', today()->toDateString())
        ->set('appointmentTypeId', (string) $appointmentType->id)
        ->set('mode', AppointmentMode::Remote->value)
        ->call('selectAssistedPerson', $assistedPerson->id)
        ->set('scheduledOn', '2026-10-01')
        ->set('notes', '  Trazer exames  ')
        ->call('createAppointment')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('appointments.index');

    $appointment = $team->appointments()->sole();

    expect($appointment->appointment_type_id)->toBe($appointmentType->id)
        ->and($appointment->assisted_person_id)->toBe($assistedPerson->id)
        ->and($appointment->mode)->toBe(AppointmentMode::Remote)
        ->and($appointment->scheduled_on->toDateString())->toBe('2026-10-01')
        ->and($appointment->notes)->toBe('Trazer exames')
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

test('the appointment fields are validated', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->set('mode', 'teleport')
        ->set('scheduledOn', '')
        ->call('createAppointment')
        ->assertHasErrors([
            'appointmentTypeId' => 'required',
            'assistedPersonId' => 'required',
            'mode',
            'scheduledOn' => 'required',
        ]);

    expect(Appointment::count())->toBe(0);
});

test('an appointment type or assisted person from another team is rejected', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy(User::factory()->create());

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->set('appointmentTypeId', (string) AppointmentType::factory()->for($otherTeam)->create()->id)
        ->set('assistedPersonId', AssistedPerson::factory()->for($otherTeam)->create()->id)
        ->call('createAppointment')
        ->assertHasErrors(['appointmentTypeId', 'assistedPersonId']);

    expect(Appointment::count())->toBe(0);
});

test('a deleted assisted person cannot be scheduled', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->set('appointmentTypeId', (string) AppointmentType::factory()->for($team)->create()->id)
        ->set('assistedPersonId', AssistedPerson::factory()->for($team)->trashed()->create()->id)
        ->call('createAppointment')
        ->assertHasErrors(['assistedPersonId']);
});

test('assisted person suggestions only list matching people from the current team', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy(User::factory()->create());

    AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']);
    AssistedPerson::factory()->for($team)->create(['name' => 'João Souza']);
    AssistedPerson::factory()->for($otherTeam)->create(['name' => 'Maria Oliveira']);

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::appointments.create')
        ->set('assistedPersonSearch', 'M');

    expect($component->get('assistedPersonSuggestions'))->toBeEmpty();

    $component->set('assistedPersonSearch', 'Mar')
        ->assertSee('Maria Silva')
        ->assertDontSee('João Souza')
        ->assertDontSee('Maria Oliveira');
});

test('an assisted person from another team cannot be selected', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherPerson = AssistedPerson::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('pages::appointments.create')
        ->call('selectAssistedPerson', $otherPerson->id);
});

test('the form selects records created from the quick-create modals', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointmentType = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);
    $assistedPerson = AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.create')
        ->dispatch('appointment-type-created', appointmentTypeId: $appointmentType->id)
        ->dispatch('assisted-person-created', assistedPersonId: $assistedPerson->id)
        ->assertSet('appointmentTypeId', (string) $appointmentType->id)
        ->assertSet('assistedPersonId', $assistedPerson->id)
        ->assertSee('Passe')
        ->assertSee('Maria Silva');
});

test('the assisted person suggestions and selection show their contact, age, and address', function () {
    $this->travelTo('2026-09-19 10:00:00');

    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $assistedPerson = AssistedPerson::factory()->for($team)->create([
        'name' => 'Maria Silva',
        'phone' => '11987654321',
        'birth_date' => '1980-05-20',
        'street' => 'Rua das Flores',
        'number' => '12',
        'district' => 'Centro',
        'city' => 'Campinas',
        'state' => BrazilianState::SaoPaulo,
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $expectedDetails = [
        'Maria Silva',
        $assistedPerson->formatted_age,
        $assistedPerson->contact,
        'Rua das Flores, 12 - Centro, Campinas - SP',
    ];

    Livewire::test('pages::appointments.create')
        ->set('assistedPersonSearch', 'Maria')
        ->assertSeeInOrder($expectedDetails)
        ->call('selectAssistedPerson', $assistedPerson->id)
        ->assertSeeInOrder($expectedDetails);
});

test('members can update an appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inPerson()->withNotes()->create();
    $newType = AppointmentType::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $appointment])
        ->assertSet('assistedPersonId', $appointment->assisted_person_id)
        ->assertSet('mode', AppointmentMode::InPerson->value)
        ->set('appointmentTypeId', (string) $newType->id)
        ->set('mode', AppointmentMode::Remote->value)
        ->set('scheduledOn', '2026-12-24')
        ->set('notes', '')
        ->call('updateAppointment')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('appointments.index');

    $appointment->refresh();

    expect($appointment->appointment_type_id)->toBe($newType->id)
        ->and($appointment->mode)->toBe(AppointmentMode::Remote)
        ->and($appointment->scheduled_on->toDateString())->toBe('2026-12-24')
        ->and($appointment->notes)->toBeNull();
});

test('members cannot edit another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $appointment])
        ->assertForbidden();
});

test('an appointment cannot be edited under another team the user belongs to', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('appointments.edit', ['current_team' => $otherTeam, 'appointment' => $appointment]))
        ->assertNotFound();
});

test('the index lists the current team appointments filtered by name and date', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
        'scheduled_on' => today(),
    ]);
    Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'João Souza']),
        'scheduled_on' => today()->addDay(),
    ]);
    $otherTeam = teamOwnedBy(User::factory()->create());
    Appointment::factory()->for($otherTeam)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($otherTeam)->create(['name' => 'Ana Oliveira']),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSet('date', today()->toDateString())
        ->assertSee('Maria Silva')
        ->assertDontSee('João Souza')
        ->set('date', '')
        ->assertSee('Maria Silva')
        ->assertSee('João Souza')
        ->assertDontSee('Ana Oliveira')
        ->set('search', 'Maria')
        ->assertSee('Maria Silva')
        ->assertDontSee('João Souza')
        ->set('search', '')
        ->set('date', today()->addDay()->toDateString())
        ->assertSee('João Souza')
        ->assertDontSee('Maria Silva');
});

test('the index hides the date column while a date filter is applied', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
        'scheduled_on' => today(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertDontSeeHtml('appointments-date-column')
        ->assertSeeHtml('appointment-details-trigger')
        ->assertSee(today()->translatedFormat('l'))
        ->set('date', '')
        ->assertSeeHtml('appointments-date-column')
        ->assertSeeHtml('appointment-details-trigger');
});

test('deleting an appointment removes it from the index', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('appointments.delete-appointment-modal')
        ->call('confirmDeleteAppointment', $appointment->id, 'Maria Silva')
        ->call('deleteAppointment')
        ->assertDispatched('appointment-deleted');

    expect($appointment->fresh()->trashed())->toBeTrue();

    Livewire::test('pages::appointments.index')
        ->assertDontSee('Maria Silva');
});

test('members cannot delete another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('appointments.delete-appointment-modal')
        ->call('confirmDeleteAppointment', $appointment->id, 'Someone')
        ->call('deleteAppointment');
});

test('the index lists the waiting queue by arrival only when waiting is the sole status', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = fn (string $name) => AssistedPerson::factory()->for($team)->create(['name' => $name]);

    Appointment::factory()->for($team)->waiting()->create(['received_at' => now()->subMinutes(40), 'assisted_person_id' => $person('Ana Oliveira')]);
    Appointment::factory()->for($team)->waiting()->create(['received_at' => now()->subMinutes(5), 'assisted_person_id' => $person('Carlos Souza')]);
    Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person('Bruno Lima')]);
    Appointment::factory()->for($team)->create(['scheduled_on' => today(), 'assisted_person_id' => $person('Maria Silva')]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->set('statuses', [AppointmentStatus::Waiting->value])
        ->assertSeeInOrder(['Ana Oliveira', 'Carlos Souza'])
        ->assertDontSee('Maria Silva')
        ->set('statuses', [AppointmentStatus::Waiting->value, AppointmentStatus::Completed->value])
        ->assertSeeInOrder(['Bruno Lima', 'Carlos Souza', 'Ana Oliveira'])
        ->assertDontSee('Maria Silva');
});

test('the index filters appointments by mode and appointment type', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $passe = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);
    $palestra = AppointmentType::factory()->for($team)->create(['name' => 'Palestra']);
    $person = fn (string $name) => AssistedPerson::factory()->for($team)->create(['name' => $name]);

    $appointment = fn (AppointmentType $type, string $state, string $name) => Appointment::factory()
        ->for($team)
        ->{$state}()
        ->create([
            'scheduled_on' => today(),
            'appointment_type_id' => $type->id,
            'assisted_person_id' => $person($name),
        ]);

    $appointment($passe, 'inPerson', 'Ana Oliveira');
    $appointment($passe, 'remote', 'Bruno Lima');
    $appointment($palestra, 'inPerson', 'Carlos Souza');
    $appointment($palestra, 'remote', 'Diana Rocha');

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->set('modes', [AppointmentMode::InPerson->value])
        ->assertSee(['Ana Oliveira', 'Carlos Souza'])
        ->assertDontSee('Bruno Lima')
        ->assertDontSee('Diana Rocha')
        ->set('modes', [])
        ->set('appointmentTypeIds', [(string) $passe->id])
        ->assertSee(['Ana Oliveira', 'Bruno Lima'])
        ->assertDontSee('Carlos Souza')
        ->assertDontSee('Diana Rocha')
        ->set('modes', [AppointmentMode::InPerson->value])
        ->assertSee('Ana Oliveira')
        ->assertDontSee('Bruno Lima')
        ->assertDontSee('Carlos Souza')
        ->assertDontSee('Diana Rocha');
});

test('the index filters appointments by attendant', function () {
    $user = User::factory()->create(['name' => 'Joana Attendant']);
    $team = teamOwnedBy($user);
    $colleague = User::factory()->create(['name' => 'Pedro Attendant']);
    $team->members()->attach($colleague, ['role' => TeamRole::Member->value]);
    $person = fn (string $name) => AssistedPerson::factory()->for($team)->create(['name' => $name]);

    Appointment::factory()->for($team)->completed()->create(['attendant_id' => $user->id, 'assisted_person_id' => $person('Ana Oliveira')]);
    Appointment::factory()->for($team)->completed()->create(['attendant_id' => $colleague->id, 'assisted_person_id' => $person('Bruno Lima')]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSee(__('All attendants'))
        ->set('attendantIds', [(string) $user->id])
        ->assertSee('Ana Oliveira')
        ->assertDontSee('Bruno Lima')
        ->assertSee('Joana Attendant')
        ->set('attendantIds', [(string) $user->id, (string) $colleague->id])
        ->assertSee(__(':count selected', ['count' => 2]))
        ->assertSee(['Ana Oliveira', 'Bruno Lima']);
});

test('the index shows removable chips for the active filters', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    Appointment::factory()->for($team)->waiting()->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertDontSeeHtml('appointments-active-filters')
        ->set('statuses', [AppointmentStatus::Waiting->value, AppointmentStatus::Completed->value])
        ->set('modes', [AppointmentMode::InPerson->value])
        ->assertSeeHtml('appointments-active-filters')
        ->assertSeeHtml('appointments-filters-count')
        ->call('removeFilter', 'statuses', AppointmentStatus::Completed->value)
        ->assertSet('statuses', [AppointmentStatus::Waiting->value])
        ->assertSet('modes', [AppointmentMode::InPerson->value])
        ->call('clearFilters')
        ->assertSet('statuses', [])
        ->assertSet('modes', [])
        ->assertDontSeeHtml('appointments-active-filters')
        ->assertSee('Maria Silva');
});

test('the index ignores a filter removal for an unknown group', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->set('statuses', [AppointmentStatus::Waiting->value])
        ->call('removeFilter', 'perPage', AppointmentStatus::Waiting->value)
        ->assertSet('statuses', [AppointmentStatus::Waiting->value])
        ->assertSet('perPage', 5);
});

test('the details flyout shows the assisted person, their last visit, and the appointment', function () {
    $this->travelTo('2026-09-21 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $assistedPerson = AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva', 'phone' => '16999998888']);
    $appointment = Appointment::factory()->for($team)->for($assistedPerson)->create([
        'scheduled_on' => '2026-09-21',
        'notes' => 'Trazer exames',
    ]);
    Appointment::factory()->for($team)->for($assistedPerson)->completed()->create(['scheduled_on' => '2026-09-10']);
    Appointment::factory()->for($team)->for($assistedPerson)->canceled()->create(['scheduled_on' => '2026-09-15']);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertDontSeeHtml('data-test="appointment-details"')
        ->call('showAppointment', $appointment->id)
        ->assertSet('selectedAppointmentId', $appointment->id)
        ->assertDispatched('modal-show', name: 'appointment-details')
        ->assertSeeHtml('data-test="appointment-details"')
        ->assertSee($assistedPerson->formatted_phone)
        ->assertSee('Trazer exames')
        ->assertSeeHtml('data-test="appointment-edit-button"')
        ->assertSee('10/09/2026')
        ->assertDontSee('15/09/2026');
});

test('the details flyout says when it is the assisted person first visit', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('showAppointment', $appointment->id)
        ->assertSee(__('First visit'));
});

test('the details flyout cannot open another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('showAppointment', $appointment->id)
        ->assertNotFound();
});

test('the details flyout opens the record only for completed appointments that use one', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $withRecord = Appointment::factory()->for($team)->completed()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    $withoutRecord = Appointment::factory()->for($team)->completed()->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('showAppointment', $withRecord->id)
        ->assertSeeHtml('data-test="appointment-details-record-button"')
        ->assertDontSeeHtml('data-test="appointment-edit-button"')
        ->call('showAppointment', $withoutRecord->id)
        ->assertDontSeeHtml('data-test="appointment-details-record-button"');
});

test('deleting the appointment closes the details flyout', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('showAppointment', $appointment->id)
        ->call('appointmentDeleted')
        ->assertSet('selectedAppointmentId', null)
        ->assertDispatched('modal-close', name: 'appointment-details')
        ->assertDontSeeHtml('data-test="appointment-details"');
});
