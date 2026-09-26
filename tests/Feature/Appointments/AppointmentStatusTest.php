<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AssistedPerson;
use App\Models\User;
use Database\Factories\AppointmentFactory;
use Livewire\Livewire;

test('members move an appointment through the attendance flow', function () {
    $this->travelTo('2026-09-19 19:30:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create(['scheduled_on' => '2026-09-19']);

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::appointments.index')
        ->call('perform', $appointment->id, 'receive')
        ->assertDispatched('toast-show');

    expect($appointment->fresh())
        ->status->toBe(AppointmentStatus::Waiting)
        ->received_at->toDateTimeString()->toBe('2026-09-19 19:30:00');

    $this->travelTo('2026-09-19 19:45:00');
    $component->call('perform', $appointment->id, 'start');

    expect($appointment->fresh())
        ->status->toBe(AppointmentStatus::InProgress)
        ->started_at->toDateTimeString()->toBe('2026-09-19 19:45:00')
        ->attendant_id->toBe($user->id);

    $this->travelTo('2026-09-19 20:10:00');
    $component->call('perform', $appointment->id, 'complete');

    expect($appointment->fresh())
        ->status->toBe(AppointmentStatus::Completed)
        ->received_at->toDateTimeString()->toBe('2026-09-19 19:30:00')
        ->started_at->toDateTimeString()->toBe('2026-09-19 19:45:00')
        ->finished_at->toDateTimeString()->toBe('2026-09-19 20:10:00')
        ->attendant_id->toBe($user->id);
});

test('each action moves the appointment to its next status', function (Closure $state, string $action, AppointmentStatus $expectedStatus, array $expectedAttributes) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = $state(Appointment::factory()->for($team))->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('perform', $appointment->id, $action);

    $appointment->refresh();

    expect($appointment->status)->toBe($expectedStatus)
        ->and($appointment->only(array_keys($expectedAttributes)))->toBe($expectedAttributes);
})->with([
    'undo reception' => [fn (AppointmentFactory $factory) => $factory->waiting(), 'undo_reception', AppointmentStatus::Scheduled, ['received_at' => null]],
    'return to queue' => [fn (AppointmentFactory $factory) => $factory->inProgress(), 'return_to_queue', AppointmentStatus::Waiting, ['started_at' => null, 'attendant_id' => null]],
    'mark as no-show' => [fn (AppointmentFactory $factory) => $factory, 'mark_as_no_show', AppointmentStatus::NoShow, []],
    'cancel' => [fn (AppointmentFactory $factory) => $factory, 'cancel', AppointmentStatus::Canceled, []],
    'reopen a no-show' => [fn (AppointmentFactory $factory) => $factory->noShow(), 'reopen', AppointmentStatus::Scheduled, []],
    'reopen a canceled appointment' => [fn (AppointmentFactory $factory) => $factory->canceled(), 'reopen', AppointmentStatus::Scheduled, []],
]);

test('actions that need confirmation open the modal instead of running right away', function (string $action, AppointmentStatus $expectedStatus) {
    $this->travelTo('2026-09-19 19:30:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create([
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']),
        'scheduled_on' => '2026-09-19',
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $index = Livewire::test('pages::appointments.index')
        ->assertSeeHtml("\$dispatch('confirm-appointment-action', { appointmentId: {$appointment->id}, action: '{$action}', appointmentDescription: 'Maria Silva (19\\/09\\/2026)' })");

    Livewire::test('appointments.confirm-appointment-action-modal')
        ->call('confirmAppointmentAction', $appointment->id, $action, 'Maria Silva (19/09/2026)')
        ->assertSee('Maria Silva (19/09/2026)')
        ->call('confirm')
        ->assertDispatched('appointment-action-confirmed', appointmentId: $appointment->id, action: $action);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);

    $index->dispatch('appointment-action-confirmed', appointmentId: $appointment->id, action: $action);

    expect($appointment->fresh()->status)->toBe($expectedStatus);
})->with([
    'mark as no-show' => ['mark_as_no_show', AppointmentStatus::NoShow],
    'cancel' => ['cancel', AppointmentStatus::Canceled],
]);

test('an action that does not apply to the current status is rejected', function (Closure $state, string $action) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = $state(Appointment::factory()->for($team))->create();
    $originalStatus = $appointment->status;

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('perform', $appointment->id, $action)
        ->assertDispatched('toast-show');

    expect($appointment->fresh()->status)->toBe($originalStatus);
})->with([
    'complete a scheduled appointment' => [fn (AppointmentFactory $factory) => $factory->state(['scheduled_on' => today()]), 'complete'],
    'receive on another day' => [fn (AppointmentFactory $factory) => $factory->state(['scheduled_on' => today()->addDay()]), 'receive'],
    'reopen a completed appointment' => [fn (AppointmentFactory $factory) => $factory->completed(), 'reopen'],
]);

test('an unknown action is not found', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('perform', $appointment->id, 'teleport')
        ->assertNotFound();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

test('members cannot move another team appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->create(['scheduled_on' => today()]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->call('perform', $appointment->id, 'receive')
        ->assertNotFound();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

test('appointments can only be edited or deleted while scheduled', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->waiting()->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    $this->get(route('appointments.edit', ['current_team' => $team, 'appointment' => $appointment]))
        ->assertForbidden();

    Livewire::test('appointments.delete-appointment-modal')
        ->call('confirmDeleteAppointment', $appointment->id, 'Someone')
        ->call('deleteAppointment')
        ->assertForbidden();

    expect($appointment->fresh()->trashed())->toBeFalse();
});

test('the index filters appointments by status and lists the waiting queue by arrival', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = fn (string $name) => AssistedPerson::factory()->for($team)->create(['name' => $name]);

    Appointment::factory()->for($team)->create(['scheduled_on' => today(), 'assisted_person_id' => $person('Maria Silva')]);
    Appointment::factory()->for($team)->waiting()->create(['received_at' => now()->subMinutes(40), 'assisted_person_id' => $person('Ana Oliveira')]);
    Appointment::factory()->for($team)->waiting()->create(['received_at' => now()->subMinutes(5), 'assisted_person_id' => $person('Carlos Souza')]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSee(['Maria Silva', 'Carlos Souza', 'Ana Oliveira'])
        ->set('statuses', [AppointmentStatus::Waiting->value])
        ->assertDontSee('Maria Silva')
        ->assertSeeInOrder(['Ana Oliveira', 'Carlos Souza']);
});

test('the index offers only the actions available for each appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    Appointment::factory()->for($team)->completed()->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSeeHtml('data-test="appointment-status-badge"')
        ->assertDontSeeHtml('data-test="appointment-actions-trigger"')
        ->assertDontSeeHtml('data-test="appointment-edit-menu-item"');
});

test('the index describes where each appointment stands below its status', function () {
    $this->travelTo('2026-09-19 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    Appointment::factory()->for($team)->create(['scheduled_on' => today()]);
    Appointment::factory()->for($team)->waiting()->create(['received_at' => '2026-09-19 09:42:00']);
    Appointment::factory()->for($team)->inProgress()->create(['attendant_id' => User::factory()->create(['name' => 'Joana Lima'])]);
    Appointment::factory()->for($team)->completed()->create(['attendant_id' => User::factory()->create(['name' => 'Pedro Alves'])]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.index')
        ->assertSee(__('Not arrived yet'))
        ->assertSee(__('Arrived at :time', ['time' => '09:42']).' · '.now()->subMinutes(18)->diffForHumans(short: true))
        ->assertSee(__('with :name', ['name' => 'Joana Lima']))
        ->assertSee(__('by :name', ['name' => 'Pedro Alves']));
});

test('the edit page offers the actions available for the appointment', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $today = Appointment::factory()->for($team)->create(['scheduled_on' => today()]);
    $tomorrow = Appointment::factory()->for($team)->create(['scheduled_on' => today()->addDay()]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $today])
        ->assertSeeHtml('data-test="appointment-action-receive"')
        ->assertSeeHtml('data-test="appointment-action-mark_as_no_show"')
        ->assertSeeHtml('data-test="appointment-action-cancel"')
        ->assertSeeHtml('data-test="appointment-delete-menu-item"');

    Livewire::test('pages::appointments.edit', ['appointment' => $tomorrow])
        ->assertDontSeeHtml('data-test="appointment-action-receive"')
        ->assertSeeHtml('data-test="appointment-action-cancel"');
});

test('performing an action from the edit page moves the appointment and returns to the index', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $received = Appointment::factory()->for($team)->create(['scheduled_on' => today()]);
    $canceled = Appointment::factory()->for($team)->create(['scheduled_on' => today()]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $received])
        ->call('perform', $received->id, 'receive')
        ->assertRedirect(route('appointments.index'));

    Livewire::test('pages::appointments.edit', ['appointment' => $canceled])
        ->dispatch('appointment-action-confirmed', appointmentId: $received->id, action: 'cancel')
        ->assertNotFound();

    Livewire::test('pages::appointments.edit', ['appointment' => $canceled])
        ->dispatch('appointment-action-confirmed', appointmentId: $canceled->id, action: 'cancel')
        ->assertRedirect(route('appointments.index'));

    expect($received->fresh()->status)->toBe(AppointmentStatus::Waiting)
        ->and($canceled->fresh()->status)->toBe(AppointmentStatus::Canceled);
});

test('deleting from the edit page returns to the index', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->create();

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::appointments.edit', ['appointment' => $appointment])
        ->call('appointmentDeleted')
        ->assertRedirect(route('appointments.index'));
});

test('appointments never received are marked as no-show after their day', function () {
    $this->travelTo('2026-09-20 00:05:00');
    $team = teamOwnedBy(User::factory()->create());

    $missed = Appointment::factory()->for($team)->create(['scheduled_on' => '2026-09-19']);
    $scheduledForToday = Appointment::factory()->for($team)->create(['scheduled_on' => '2026-09-20']);
    $leftWaiting = Appointment::factory()->for($team)->waiting()->create(['scheduled_on' => '2026-09-19']);
    $canceled = Appointment::factory()->for($team)->canceled()->create(['scheduled_on' => '2026-09-19']);

    $this->artisan('schedule:run')->assertSuccessful();

    expect($missed->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and($scheduledForToday->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($leftWaiting->fresh()->status)->toBe(AppointmentStatus::Waiting)
        ->and($canceled->fresh()->status)->toBe(AppointmentStatus::Canceled);
});
