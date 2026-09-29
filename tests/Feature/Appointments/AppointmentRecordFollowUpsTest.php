<?php

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\InfiltrationRemovalPlace;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Mentor;
use App\Models\Team;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Open the record screen of an in-progress appointment whose type is attended with a record, with its mentor chosen.
 *
 * @return array{0: Testable, 1: Appointment, 2: Team}
 */
function attendRecordAppointment(array $attributes = []): array
{
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'scheduled_on' => '2026-09-20',
        'attendant_id' => $user,
    ] + $attributes);

    test()->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('mentorId', (string) Mentor::factory()->for($team)->create()->id);

    return [$component, $appointment, $team];
}

/**
 * Get the appointments of the team other than the attended one.
 */
function followUpsOf(Appointment $appointment)
{
    return Appointment::query()->where('team_id', $appointment->team_id)->whereKeyNot($appointment->id);
}

beforeEach(function () {
    $this->travelTo('2026-09-20 19:30:00');
});

test('the return checkbox schedules the return with the same type and mode by default', function () {
    [$component, $appointment] = attendRecordAppointment(['mode' => AppointmentMode::Remote]);

    $component
        ->assertSet('returnAppointmentTypeId', (string) $appointment->appointment_type_id)
        ->set('returnOn', '2026-10-11')
        ->set('schedulesReturn', true)
        ->call('save')
        ->assertHasNoErrors();

    $return = followUpsOf($appointment)->sole();

    expect($return)
        ->appointment_type_id->toBe($appointment->appointment_type_id)
        ->assisted_person_id->toBe($appointment->assisted_person_id)
        ->mode->toBe(AppointmentMode::Remote)
        ->status->toBe(AppointmentStatus::Scheduled)
        ->creator_id->toBe($appointment->attendant_id)
        ->and($return->scheduled_on->toDateString())->toBe('2026-10-11')
        ->and($appointment->record->return_appointment_id)->toBe($return->id);
});

test('an infiltration removed at the center schedules an in-person removal', function () {
    [$component, $appointment, $team] = attendRecordAppointment(['mode' => AppointmentMode::Remote]);
    $removalType = AppointmentType::factory()->for($team)->create();

    $component
        ->set('infiltrationSite', 'Braço direito')
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtTheCenter->value)
        ->set('removalAppointmentTypeId', (string) $removalType->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->sole())
        ->appointment_type_id->toBe($removalType->id)
        ->mode->toBe(AppointmentMode::InPerson)
        ->scheduled_on->toDateString()->toBe('2026-09-23');
});

test('the removal type is required only when the infiltration is removed at the center', function () {
    [$component] = attendRecordAppointment();

    $component
        ->set('infiltrationSite', 'Braço direito')
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtTheCenter->value)
        ->set('removalAppointmentTypeId', '')
        ->call('save')
        ->assertHasErrors(['removalAppointmentTypeId' => 'required_if'])
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtHome->value)
        ->call('save')
        ->assertHasNoErrors();
});

test('saving again updates the follow-ups instead of duplicating them', function () {
    [$component, $appointment] = attendRecordAppointment();

    $component
        ->set('returnOn', '2026-10-11')
        ->set('schedulesReturn', true)
        ->call('save')
        ->call('save')
        ->set('returnOn', '2026-10-18')
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->sole()->scheduled_on->toDateString())->toBe('2026-10-18');
});

test('unchecking the return removes the return still scheduled', function () {
    [$component, $appointment] = attendRecordAppointment();

    $component
        ->set('returnOn', '2026-10-11')
        ->set('schedulesReturn', true)
        ->call('save')
        ->set('schedulesReturn', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->exists())->toBeFalse()
        ->and(followUpsOf($appointment)->onlyTrashed()->count())->toBe(1)
        ->and($appointment->record->fresh())
        ->return_appointment_id->toBeNull()
        ->return_on->toDateString()->toBe('2026-10-11');
});

test('a follow-up already moved through the queue is left untouched', function () {
    [$component, $appointment] = attendRecordAppointment();

    $component
        ->set('returnOn', '2026-10-11')
        ->set('schedulesReturn', true)
        ->call('save');

    $return = followUpsOf($appointment)->sole();
    $return->update(['status' => AppointmentStatus::Canceled]);

    $component
        ->set('returnOn', '2026-10-18')
        ->call('save')
        ->set('schedulesReturn', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($return->fresh())
        ->trashed()->toBeFalse()
        ->status->toBe(AppointmentStatus::Canceled)
        ->scheduled_on->toDateString()->toBe('2026-10-11');
});

test('an infiltration removed elsewhere keeps its date and place without scheduling anything', function (InfiltrationRemovalPlace $place) {
    [$component, $appointment] = attendRecordAppointment();

    $component
        ->set('infiltrationSite', 'Perna esquerda')
        ->set('infiltrationRemovalPlace', $place->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->exists())->toBeFalse()
        ->and($appointment->record)
        ->infiltration_removal_place->toBe($place)
        ->infiltration_remove_on->toDateString()->toBe('2026-09-23');
})->with([
    'at another center' => [InfiltrationRemovalPlace::AnotherCenter],
    'at home' => [InfiltrationRemovalPlace::AtHome],
]);

test('correcting the removal place to elsewhere removes the removal scheduled by mistake', function () {
    [$component, $appointment, $team] = attendRecordAppointment();

    $component
        ->set('infiltrationSite', 'Braço direito')
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtTheCenter->value)
        ->set('removalAppointmentTypeId', (string) AppointmentType::factory()->for($team)->create()->id)
        ->call('save');

    expect(followUpsOf($appointment)->count())->toBe(1);

    $component
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtHome->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->exists())->toBeFalse()
        ->and($appointment->record->fresh())
        ->infiltration_removal_appointment_id->toBeNull()
        ->infiltration_remove_on->toDateString()->toBe('2026-09-23');
});

test('the removal type is learned from the last removal the team scheduled', function () {
    [$first, $appointment, $team] = attendRecordAppointment();
    $removalType = AppointmentType::factory()->for($team)->create();

    $first
        ->assertSet('removalAppointmentTypeId', '')
        ->set('infiltrationSite', 'Braço direito')
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtTheCenter->value)
        ->set('removalAppointmentTypeId', (string) $removalType->id)
        ->call('save');

    $next = Appointment::factory()->for($team)->inProgress()->create([
        'appointment_type_id' => $appointment->appointment_type_id,
        'scheduled_on' => '2026-09-20',
    ]);

    Livewire::test('pages::appointments.attend', ['appointment' => $next])
        ->assertSet('removalAppointmentTypeId', (string) $removalType->id);
});

test('the return date shows how many appointments of the return type it already has and the places left', function () {
    [$component, $appointment, $team] = attendRecordAppointment();
    $appointment->appointmentType->update(['name' => 'Tratamento de Cura', 'daily_limit' => 3]);
    $returnType = ['appointment_type_id' => $appointment->appointment_type_id];

    Appointment::factory()->for($team)->count(2)->create($returnType + ['scheduled_on' => '2026-10-11']);
    Appointment::factory()->for($team)->canceled()->create($returnType + ['scheduled_on' => '2026-10-11']);
    Appointment::factory()->for($team)->create(['scheduled_on' => '2026-10-11', 'appointment_type_id' => AppointmentType::factory()->for($team)]);
    Appointment::factory()->create(['scheduled_on' => '2026-10-11']);
    Appointment::factory()->for($team)->create($returnType + ['scheduled_on' => '2026-10-12']);

    $component
        ->set('returnOn', '2026-10-11')
        ->assertDontSeeHtml('data-test="appointment-record-return-day-occupancy"')
        ->set('schedulesReturn', true)
        ->assertSeeHtml('data-test="appointment-record-return-day-occupancy"')
        ->assertSee('2 agendamentos de Tratamento de Cura em 11/10/2026')
        ->assertSee('1 vaga')
        ->assertDontSeeHtml('data-test="appointment-record-return-day-full"');
});

test('the return already scheduled by the record does not take a place on its date', function () {
    [$component, $appointment, $team] = attendRecordAppointment();
    Appointment::factory()->for($team)->create(['appointment_type_id' => $appointment->appointment_type_id, 'scheduled_on' => '2026-10-11']);

    $component
        ->set('returnOn', '2026-10-11')
        ->set('schedulesReturn', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->count())->toBe(2)
        ->and($component->instance()->returnDayOccupancy['count'])->toBe(1);
});

test('the return shortcuts count the days from the appointment', function () {
    [$component] = attendRecordAppointment();

    $component
        ->call('setReturnInDays', 30)
        ->assertSet('returnOn', '2026-10-20')
        ->call('setReturnInDays', 7)
        ->assertSet('returnOn', '2026-09-27');
});

test('a return date that reached the daily limit warns but still schedules the return', function () {
    [$component, $appointment, $team] = attendRecordAppointment();
    $appointment->appointmentType->update(['name' => 'Tratamento de Cura', 'daily_limit' => 1]);
    Appointment::factory()->for($team)->create(['appointment_type_id' => $appointment->appointment_type_id, 'scheduled_on' => '2026-10-11']);

    $component
        ->set('schedulesReturn', true)
        ->set('returnOn', '2026-10-12')
        ->assertSee('Nenhum agendamento de Tratamento de Cura em 12/10/2026')
        ->assertDontSeeHtml('data-test="appointment-record-return-day-full"')
        ->set('returnOn', '2026-10-11')
        ->assertSeeHtml('data-test="appointment-record-return-day-full"')
        ->assertSee('Limite atingido: já existem 1 de 1 agendamentos de Tratamento de Cura em 11/10/2026.')
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->where('scheduled_on', '>=', '2026-10-11')->count())->toBe(2);
});

test('choosing another return type counts the appointments of that type, without places when it has no limit', function () {
    [$component, $appointment, $team] = attendRecordAppointment();
    $appointment->appointmentType->update(['daily_limit' => 1]);
    $otherType = AppointmentType::factory()->for($team)->create(['name' => 'Hidroterapia']);
    Appointment::factory()->for($team)->create(['appointment_type_id' => $appointment->appointment_type_id, 'scheduled_on' => '2026-10-11']);
    Appointment::factory()->for($team)->count(2)->create(['appointment_type_id' => $otherType->id, 'scheduled_on' => '2026-10-11']);

    $component
        ->set('schedulesReturn', true)
        ->set('returnOn', '2026-10-11')
        ->set('returnAppointmentTypeId', (string) $otherType->id)
        ->assertSee('2 agendamentos de Hidroterapia em 11/10/2026')
        ->assertDontSee('vaga')
        ->assertDontSeeHtml('data-test="appointment-record-return-day-full"');
});

test('a return on a weekday its type does not take place warns but is still scheduled', function () {
    [$component, $appointment] = attendRecordAppointment();
    $appointment->appointmentType->update(['name' => 'Tratamento de cura', 'weekdays' => [Weekday::Monday, Weekday::Wednesday]]);

    $component
        ->set('schedulesReturn', true)
        ->set('returnOn', '2026-09-30')
        ->assertDontSeeHtml('data-test="appointment-record-return-day-not-offered"')
        ->set('returnOn', '2026-10-01')
        ->assertSeeHtml('data-test="appointment-record-return-day-not-offered"')
        ->assertSee('Tratamento de cura acontece somente em segundas-feiras e quartas-feiras.')
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->sole()->scheduled_on->toDateString())->toBe('2026-10-01');
});

test('an infiltration removal at the center on a weekday its type does not take place warns but is still scheduled', function () {
    [$component, $appointment, $team] = attendRecordAppointment();
    $removalType = AppointmentType::factory()->for($team)->onWeekdays(Weekday::Wednesday, Weekday::Friday)->create(['name' => 'Retirada de infiltração']);

    $component
        ->set('infiltrationSite', 'Braço direito')
        ->set('infiltrationRemoveOn', '2026-09-24')
        ->set('removalAppointmentTypeId', (string) $removalType->id)
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtHome->value)
        ->assertDontSeeHtml('data-test="appointment-record-removal-day-not-offered"')
        ->set('infiltrationRemovalPlace', InfiltrationRemovalPlace::AtTheCenter->value)
        ->assertSeeHtml('data-test="appointment-record-removal-day-not-offered"')
        ->assertSee('Retirada de infiltração acontece somente em quartas-feiras e sextas-feiras.')
        ->set('infiltrationRemoveOn', '2026-09-23')
        ->assertDontSeeHtml('data-test="appointment-record-removal-day-not-offered"')
        ->set('infiltrationRemoveOn', '2026-09-24')
        ->call('save')
        ->assertHasNoErrors();

    expect(followUpsOf($appointment)->sole()->scheduled_on->toDateString())->toBe('2026-09-24');
});

test('the return shortcuts and the removal date move to the next day their type takes place', function () {
    [$component, $appointment, $team] = attendRecordAppointment();
    $appointment->appointmentType->update(['weekdays' => [Weekday::Monday, Weekday::Wednesday]]);
    $removalType = AppointmentType::factory()->for($team)->onWeekdays(Weekday::Thursday)->create();

    $component
        ->call('setReturnInDays', 7)
        ->assertSet('returnOn', '2026-09-28')
        ->call('setReturnInDays', 30)
        ->assertSet('returnOn', '2026-10-21')
        ->set('removalAppointmentTypeId', (string) $removalType->id)
        ->set('infiltrationSite', 'Braço direito')
        ->assertSet('infiltrationRemoveOn', '2026-09-24');
});
