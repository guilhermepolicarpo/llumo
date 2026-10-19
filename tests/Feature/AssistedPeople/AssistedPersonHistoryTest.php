<?php

use App\Enums\AppointmentStatus;
use App\Enums\InfiltrationRemovalPlace;
use App\Enums\TeamRole;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\FluidicRemedy;
use App\Models\Mentor;
use App\Models\User;
use Livewire\Livewire;

test('the history splits the assisted person appointments into upcoming and previous, with what was recorded', function () {
    $this->travelTo('2026-09-27 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();
    $recordType = AppointmentType::factory()->for($team)->withRecord()->create();

    $noShow = Appointment::factory()->for($team)->noShow()->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-09-06']);
    $completed = Appointment::factory()->for($team)->completed()->create([
        'assisted_person_id' => $person,
        'appointment_type_id' => $recordType,
        'scheduled_on' => '2026-09-20',
    ]);
    $completedToday = Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person]);
    $nextMonth = Appointment::factory()->for($team)->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-10-11']);
    $nextWeek = Appointment::factory()->for($team)->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-10-04']);
    Appointment::factory()->for($team)->create(['scheduled_on' => '2026-09-13']);

    $record = AppointmentRecord::factory()->for($completed)->create([
        'mentor_id' => Mentor::factory()->for($team)->create(['name' => 'Irmã Clara']),
        'observations' => 'Dormir mais cedo',
    ]);
    $record->fluidicRemedies()->attach(FluidicRemedy::factory()->for($team)->create(['name' => 'Calmante']), ['position' => 0]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertSee(__('with :name', ['name' => 'Irmã Clara']))
        ->assertSee('Calmante')
        ->assertSee('Dormir mais cedo')
        ->assertSee(route('appointments.attend', ['appointment' => $completed]))
        ->assertSee(route('appointments.attendance-sheet', ['appointment' => $completed]))
        ->assertDontSee(route('appointments.attendance-sheet', ['appointment' => $completedToday]));

    expect($component->get('upcomingAppointments')->pluck('id')->all())->toBe([$nextWeek->id, $nextMonth->id])
        ->and($component->get('previousAppointments')->pluck('id')->all())->toBe([$completedToday->id, $completed->id, $noShow->id]);
});

test('the history filters appointments by type and status', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();
    $passe = AppointmentType::factory()->for($team)->create(['name' => 'Passe']);
    $palestra = AppointmentType::factory()->for($team)->create(['name' => 'Palestra']);

    $completedPasse = Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person, 'appointment_type_id' => $passe]);
    $missedPasse = Appointment::factory()->for($team)->noShow()->create(['assisted_person_id' => $person, 'appointment_type_id' => $passe, 'scheduled_on' => today()->subWeek()]);
    Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person, 'appointment_type_id' => $palestra]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->set('appointmentTypeId', (string) $passe->id);

    expect($component->get('previousAppointments')->pluck('id')->all())->toBe([$completedPasse->id, $missedPasse->id]);

    $component->set('status', AppointmentStatus::NoShow->value);

    expect($component->get('previousAppointments')->pluck('id')->all())->toBe([$missedPasse->id]);
});

test('previous appointments load in batches', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();

    Appointment::factory()->for($team)->completed()->count(12)
        ->sequence(fn ($sequence) => ['scheduled_on' => today()->subWeeks($sequence->index + 1)])
        ->create(['assisted_person_id' => $person]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertSee(__('Load more appointments'));

    expect($component->get('previousAppointments'))->toHaveCount(10);

    $component->call('loadMore')->assertDontSee(__('Load more appointments'));

    expect($component->get('previousAppointments'))->toHaveCount(12);
});

test('the summary counts the visits', function () {
    $this->travelTo('2026-09-27 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();

    Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person, 'scheduled_on' => '2025-03-10']);
    Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-09-20']);
    Appointment::factory()->for($team)->noShow()->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-09-06']);
    Appointment::factory()->for($team)->canceled()->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-10-01']);

    $this->actingAs($user);
    $user->switchTeam($team);

    expect(Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])->get('stats'))
        ->completed->toBe(2)
        ->no_shows->toBe(1)
        ->first_visit_on->toDateString()->toBe('2025-03-10')
        ->last_visit_on->toDateString()->toBe('2026-09-20');
});

/**
 * Create a completed appointment of the person with the given record.
 *
 * @param  array<string, mixed>  $attributes
 */
function completedRecordFor(AssistedPerson $person, string $scheduledOn, array $attributes = []): AppointmentRecord
{
    return AppointmentRecord::factory()
        ->for(Appointment::factory()->for($person->team)->completed()->create([
            'assisted_person_id' => $person,
            'appointment_type_id' => AppointmentType::factory()->for($person->team)->withRecord(),
            'scheduled_on' => $scheduledOn,
        ]))
        ->create($attributes);
}

test('the follow-ups of the latest record that are still unscheduled are flagged with a link to schedule them', function () {
    $this->travelTo('2026-09-27 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();

    completedRecordFor($person, '2026-09-06', ['return_on' => '2026-11-01']);
    completedRecordFor($person, '2026-09-20', [
        'mentor_id' => Mentor::factory()->for($team)->create(['name' => 'Irmã Clara']),
        'infiltration_site' => 'Coluna',
        'infiltration_remove_on' => '2026-09-30',
        'infiltration_removal_place' => InfiltrationRemovalPlace::AtTheCenter,
        'return_on' => '2026-10-04',
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    $followUps = Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])->get('pendingFollowUps');

    expect($followUps)->sequence(
        fn ($followUp) => $followUp
            ->message->toBe(__('The infiltration removal on :date has not been scheduled yet.', ['date' => '30/09/2026']))
            ->detail->toBe(__('Indicated by :mentor in the record of :date.', ['mentor' => 'Irmã Clara', 'date' => '20/09/2026']))
            ->url->toBe(route('appointments.create', ['assisted_person' => $person->id, 'date' => '2026-09-30'])),
        fn ($followUp) => $followUp
            ->message->toBe(__('The return indicated for :date has not been scheduled yet.', ['date' => '04/10/2026']))
            ->url->toBe(route('appointments.create', ['assisted_person' => $person->id, 'date' => '2026-10-04'])),
    );
});

test('follow-ups already on the schedule are not flagged', function () {
    $this->travelTo('2026-09-27 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();
    $removal = Appointment::factory()->for($team)->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-09-30']);

    completedRecordFor($person, '2026-09-20', [
        'infiltration_remove_on' => '2026-09-30',
        'infiltration_removal_place' => InfiltrationRemovalPlace::AtTheCenter,
        'infiltration_removal_appointment_id' => $removal,
        'return_on' => '2026-10-04',
    ]);
    Appointment::factory()->for($team)->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-10-05']);

    $this->actingAs($user);
    $user->switchTeam($team);

    expect(Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])->get('pendingFollowUps'))->toBe([]);
});

test('a return date gone by is flagged until the assisted person comes back', function () {
    $this->travelTo('2026-09-27 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();

    completedRecordFor($person, '2026-08-01', ['return_on' => '2026-08-15']);

    $this->actingAs($user);
    $user->switchTeam($team);

    expect(Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])->get('pendingFollowUps'))->sequence(
        fn ($followUp) => $followUp
            ->message->toBe(__('The return indicated for :date went by without the assisted person coming back.', ['date' => '15/08/2026']))
            ->url->toBe(route('appointments.create', ['assisted_person' => $person->id])),
    );

    Appointment::factory()->for($team)->completed()->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-08-20']);

    expect(Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])->get('pendingFollowUps'))->toBe([]);
});

test('an upcoming appointment scheduled by a record says so', function () {
    $this->travelTo('2026-09-27 10:00:00');
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();
    $return = Appointment::factory()->for($team)->create(['assisted_person_id' => $person, 'scheduled_on' => '2026-10-04']);

    completedRecordFor($person, '2026-09-20', ['return_on' => '2026-10-04', 'return_appointment_id' => $return]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertSee(__('return from the record of :date', ['date' => '20/09']));
});

test('the history header shows the full address', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create([
        'street' => 'Rua das Flores',
        'number' => '120',
        'complement' => 'Apto 12',
        'district' => 'Centro',
        'city' => 'Porto Alegre',
        'state' => 'RS',
        'postal_code' => '90010000',
    ]);

    $this->actingAs($user);
    $user->switchTeam($team);

    Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertSee('Rua das Flores, 120, Apto 12 - Centro, Porto Alegre - RS, 90010-000');
});

test('non members cannot view an assisted person history', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = teamOwnedBy($owner);
    $person = AssistedPerson::factory()->for($team)->create();

    $this->actingAs($outsider)
        ->get(route('assisted-people.show', ['current_team' => $team->slug, 'assistedPerson' => $person]))
        ->assertForbidden();
});

test('an assisted person history cannot be opened under another team the user belongs to', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $otherTeam = teamOwnedBy($user);
    $person = AssistedPerson::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('assisted-people.show', ['current_team' => $otherTeam, 'assistedPerson' => $person]))
        ->assertNotFound();
});

test('the back button returns to the page the history was opened from', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $person = AssistedPerson::factory()->for($team)->create();
    $loansPath = "/{$team->slug}/library/loans";

    Livewire::withQueryParams(['from' => $loansPath])
        ->test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertSeeHtml('href="'.$loansPath.'"')
        ->assertSeeHtml('href="'.route('assisted-people.edit', ['assistedPerson' => $person, 'from' => route('assisted-people.show', ['assistedPerson' => $person, 'from' => $loansPath], absolute: false)]).'"');
});

test('the back button ignores an origin outside the app', function (string $from) {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $person = AssistedPerson::factory()->for($team)->create();

    Livewire::withQueryParams(['from' => $from])
        ->test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertDontSeeHtml('href="'.e($from).'"')
        ->assertSeeHtml('aria-label="'.__('Back to assisted people').'"');
})->with([
    'absolute url' => 'https://evil.example/path',
    'protocol-relative url' => '//evil.example/path',
    'backslash' => '/\\evil.example/path',
    'tab' => "/\t/evil.example/path",
]);

test('editing the registration returns to the page it was opened from', function (string $from) {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $person = AssistedPerson::factory()->for($team)->create();
    $from = str_replace(['{team}', '{person}'], [$team->slug, $person->id], $from);

    Livewire::withQueryParams(['from' => $from])
        ->test('pages::assisted-people.edit', ['assistedPerson' => $person])
        ->assertSeeHtml('href="'.$from.'"')
        ->call('updateAssistedPerson')
        ->assertHasNoErrors()
        ->assertRedirect($from);
})->with([
    'the list' => '/{team}/assisted-people',
    'the history opened from the loans' => '/{team}/assisted-people/{person}?from=%2F{team}%2Flibrary%2Floans',
]);

test('the edit menu item of the list tells the edit form to return to the list', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $person = AssistedPerson::factory()->for($team)->create();

    Livewire::test('pages::assisted-people.index')
        ->assertSeeHtml('href="'.route('assisted-people.edit', ['assistedPerson' => $person, 'from' => "/{$team->slug}/assisted-people"]).'"');
});

test('the pages that link to the history tell it where to go back to', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $person = AssistedPerson::factory()->for($team)->create();
    BookLoan::factory()->for(BookCopy::factory()->for(Book::factory()->for($team)), 'copy')->for($person)->create();
    $appointment = Appointment::factory()->for($team)->create(['assisted_person_id' => $person]);

    Livewire::test('pages::library.loans.index')
        ->assertSeeHtml('href="'.route('assisted-people.show', ['assistedPerson' => $person, 'from' => "/{$team->slug}/library/loans"]).'"');

    Livewire::test('pages::appointments.index')
        ->call('showAppointment', $appointment->id)
        ->assertSeeHtml('href="'.route('assisted-people.show', ['assistedPerson' => $person, 'from' => "/{$team->slug}/appointments"]).'"');
});
