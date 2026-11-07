<?php

use App\Enums\AppointmentMode;
use App\Enums\Catalog;
use App\Enums\TeamRole;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\Mentor;
use App\Models\PassPrescription;
use App\Models\PassSession;
use App\Models\PassType;
use App\Models\Team;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Open the record of an appointment on Monday, 09/11/2026, attended by its team's owner, with a mentor already picked.
 *
 * @return array{0: Testable, 1: Team, 2: Appointment}
 */
function attendRemoteAppointment(): array
{
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $appointment = Appointment::factory()->for($team)->remote()->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'scheduled_on' => '2026-11-09',
        'attendant_id' => $user,
    ]);

    test()->actingAs($user);
    $user->switchTeam($team);

    $component = Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->set('form.mentorId', (string) Mentor::factory()->for($team)->create()->id);

    return [$component, $team, $appointment];
}

test('saving the record keeps the dates chosen for each remote pass, in date order', function () {
    [$component, $team, $appointment] = attendRemoteAppointment();
    $passType = PassType::factory()->for($team)->create();

    $component
        ->call('addPassPrescription')
        ->set('form.passPrescriptions.0.pass_type_id', (string) $passType->id)
        ->set('form.passPrescriptions.0.quantity', 3)
        ->set('form.passPrescriptions.0.mode', AppointmentMode::Remote->value)
        ->set('form.passPrescriptions.0.dates', ['2026-11-18', '2026-11-11', ''])
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->record->passPrescriptions->sole()->sessions->map->scheduled_on->map->toDateString()->all())
        ->toBe(['2026-11-11', '2026-11-18']);

    $component
        ->assertSet('form.passPrescriptions.0.dates', ['2026-11-11', '2026-11-18'])
        ->set('form.passPrescriptions.0.dates', ['2026-11-25'])
        ->call('save')
        ->assertHasNoErrors();

    expect(PassSession::query()->pluck('scheduled_on')->map->toDateString()->all())->toBe(['2026-11-25']);
});

test('the dates of a pass given in person are not kept', function () {
    [$component, $team, $appointment] = attendRemoteAppointment();

    $component
        ->call('addPassPrescription')
        ->set('form.passPrescriptions.0.pass_type_id', (string) PassType::factory()->for($team)->create()->id)
        ->set('form.passPrescriptions.0.mode', AppointmentMode::InPerson->value)
        ->set('form.passPrescriptions.0.dates', ['2026-11-01', 'not a date'])
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->record->passPrescriptions->sole()->sessions)->toBeEmpty();
});

test('the remote pass dates are validated before the sheet is printed', function (array $dates, string $error, ?string $rule) {
    [$component, $team, $appointment] = attendRemoteAppointment();

    $component
        ->call('addPassPrescription')
        ->set('form.passPrescriptions.0.pass_type_id', (string) PassType::factory()->for($team)->create()->id)
        ->set('form.passPrescriptions.0.quantity', 2)
        ->set('form.passPrescriptions.0.mode', AppointmentMode::Remote->value)
        ->set('form.passPrescriptions.0.dates', $dates)
        ->call('saveRemotePassDates')
        ->assertHasErrors($rule === null ? [$error] : [$error => $rule]);

    expect($appointment->recordDraft()->exists())->toBeFalse();
})->with([
    'more dates than passes' => [['2026-11-11', '2026-11-18', '2026-11-25'], 'form.passPrescriptions.0.dates', null],
    'the same date twice' => [['2026-11-11', '2026-11-11'], 'form.passPrescriptions.0.dates', null],
    'on the appointment day' => [['2026-11-09'], 'form.passPrescriptions.0.dates.0', 'after'],
    'not a date' => [['11/11/2026'], 'form.passPrescriptions.0.dates.0', 'date_format'],
]);

test('the remote pass sheet is printed from the form as it stands, before the mentor is picked', function () {
    $this->travelTo('2026-11-09 19:30:00');
    [$component, $team, $appointment] = attendRemoteAppointment();
    $passType = PassType::factory()->for($team)->create(['name' => 'Passe com todos']);

    $component
        ->set('form.mentorId', '')
        ->call('addPassPrescription')
        ->set('form.passPrescriptions.0.pass_type_id', (string) $passType->id)
        ->set('form.passPrescriptions.0.quantity', 2)
        ->set('form.passPrescriptions.0.mode', AppointmentMode::Remote->value)
        ->set('form.passPrescriptions.0.dates', ['2026-11-18', '2026-11-11'])
        ->call('saveRemotePassDates')
        ->assertHasNoErrors()
        ->assertReturned(__('Draft saved at :time', ['time' => '19:30']))
        ->assertSeeHtml('data-test="appointment-record-print-remote-passes-button"');

    expect($appointment->record()->exists())->toBeFalse();

    Pdf::fake();

    $this->get(route('appointments.remote-pass-sheet', ['current_team' => $team, 'appointment' => $appointment]))->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => str_contains($pdf->getHtml(), 'Passe com todos')
        && preg_match('/11\/11\/2026.*18\/11\/2026/s', $pdf->getHtml()) === 1);
});

test('members print the remote pass sheet with a card per pass type and the assisted person\'s schedule', function () {
    $this->travelTo('2026-11-09 19:00:00');
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $team->update(['legal_name' => 'Casa Espírita Exemplo']);
    $appointment = Appointment::factory()->for($team)->remote()->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
        'assisted_person_id' => AssistedPerson::factory()->for($team)->create([
            'name' => 'Maria Aparecida Souza',
            'birth_date' => '1963-05-10',
            'street' => 'Rua das Acácias',
            'number' => '120',
            'district' => 'Centro',
            'city' => 'Ribeirão Preto',
            'state' => 'SP',
        ]),
        'scheduled_on' => '2026-11-09',
    ]);
    $record = AppointmentRecord::factory()->for($appointment)->create();
    $everyone = PassType::factory()->for($team)->create([
        'name' => 'Passe com todos',
        'remote_weekdays' => [Weekday::Wednesday],
        'remote_starts_at' => '20:00',
        'remote_ends_at' => '20:30',
    ]);
    $threeMediums = PassType::factory()->for($team)->create(['name' => 'Passe com 3 médiuns']);
    PassPrescription::factory()->for($record)->for($everyone)->create(['quantity' => 2, 'mode' => AppointmentMode::Remote])
        ->sessions()->createMany([['scheduled_on' => '2026-11-11'], ['scheduled_on' => '2026-11-18']]);
    PassPrescription::factory()->for($record)->for($threeMediums)->create(['quantity' => 1, 'mode' => AppointmentMode::Remote])
        ->sessions()->create(['scheduled_on' => '2026-11-16']);
    PassPrescription::factory()->for($record)->for(PassType::factory()->for($team)->create(['name' => 'Hidroterapia']))->create(['mode' => AppointmentMode::InPerson]);

    Pdf::fake();

    $this->get(route('appointments.remote-pass-sheet', ['current_team' => $team, 'appointment' => $appointment]))->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        expect($pdf->isInline())->toBeTrue()
            ->and($pdf->format)->toBe('a4')
            ->and($pdf->downloadName)->toBe('passes-a-distancia-maria-aparecida-souza.pdf')
            ->and($html)->toContain(
                'Casa Espírita Exemplo',
                'Maria Aparecida Souza',
                '63 anos',
                'Rua das Acácias, 120',
                'Ribeirão Preto - SP',
                'Passe com todos',
                'Quartas-feiras · 20h às 20h30',
                'das 20h às 20h30',
                'Passe com 3 médiuns',
            )
            ->and($html)->not->toContain('Hidroterapia')
            ->and(substr_count($html, 'data-test="remote-pass-card"'))->toBe(2)
            ->and($html)->toMatch('/Quarta<\/strong>, 11 de novembro.*Segunda<\/strong>, 16 de novembro.*Quarta<\/strong>, 18 de novembro/s');

        return true;
    });
});

test('the remote pass sheet is printable only for a saved record with a remote pass', function (Closure $prescribe, int $status) {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $appointment = Appointment::factory()->for($team)->remote()->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);
    $prescribe($appointment);

    $this->get(route('appointments.remote-pass-sheet', ['current_team' => $team, 'appointment' => $appointment]))
        ->assertStatus($status);
})->with([
    'without a record' => [fn (Appointment $appointment) => null, 403],
    'with in person passes only' => [fn (Appointment $appointment) => PassPrescription::factory()->for(AppointmentRecord::factory()->for($appointment)->create(), 'appointmentRecord')->create(['mode' => AppointmentMode::InPerson]), 403],
    'with a remote pass' => [fn (Appointment $appointment) => PassPrescription::factory()->for(AppointmentRecord::factory()->for($appointment)->create(), 'appointmentRecord')->create(['mode' => AppointmentMode::Remote]), 200],
    'with a remote pass only in the draft' => [fn (Appointment $appointment) => $appointment->recordDraft()->create([
        'data' => ['passPrescriptions' => [['pass_type_id' => (string) PassType::factory()->for($appointment->team)->create()->id, 'quantity' => 2, 'mode' => AppointmentMode::Remote->value, 'dates' => []]]],
    ]), 200],
    'with a remote pass of another team in the draft' => [fn (Appointment $appointment) => $appointment->recordDraft()->create([
        'data' => ['passPrescriptions' => [['pass_type_id' => (string) PassType::factory()->create()->id, 'quantity' => 2, 'mode' => AppointmentMode::Remote->value]]],
    ]), 403],
]);

test('the remote pass sheet of another team\'s appointment cannot be printed', function () {
    [, $team] = actingAsTeamMember(TeamRole::Owner);
    $appointment = Appointment::factory()->for(teamOwnedBy(User::factory()->create()))->remote()->inProgress()->create();
    PassPrescription::factory()->for(AppointmentRecord::factory()->for($appointment)->create(), 'appointmentRecord')->create(['mode' => AppointmentMode::Remote]);

    $this->get(route('appointments.remote-pass-sheet', ['current_team' => $team, 'appointment' => $appointment]))
        ->assertNotFound();
});

test('admins set the days and the rest time of a pass type\'s remote passes', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $component = Livewire::test('pages::catalogs.index', ['catalog' => Catalog::PassType])
        ->set('name', 'Passe com todos')
        ->set('remoteWeekdays', [(string) Weekday::Wednesday->value, (string) Weekday::Monday->value])
        ->set('remoteStartsAt', '20:00')
        ->set('remoteEndsAt', '20:30')
        ->call('saveEntry')
        ->assertHasNoErrors();

    $passType = $team->passTypes()->sole();

    expect($passType->remoteWeekdayNumbers())->toBe([Weekday::Monday->value, Weekday::Wednesday->value])
        ->and($passType->remoteScheduleLabel())->toBe('Segundas-feiras e quartas-feiras, das 20h às 20h30');

    $component
        ->call('editEntry', $passType->id)
        ->assertSet('remoteWeekdays', ['1', '3'])
        ->assertSet('remoteStartsAt', '20:00')
        ->set('remoteEndsAt', '19:00')
        ->call('saveEntry')
        ->assertHasErrors(['remoteEndsAt' => 'after'])
        ->set('remoteEndsAt', '')
        ->call('saveEntry')
        ->assertHasErrors(['remoteEndsAt' => 'required_with']);
});

test('the record offers the remote pass sheet only once a remote pass is saved', function () {
    [$component, $team, $appointment] = attendRemoteAppointment();

    $component->assertDontSeeHtml('data-test="appointment-record-print-remote-passes-button"')
        ->call('addPassPrescription')
        ->set('form.passPrescriptions.0.pass_type_id', (string) PassType::factory()->for($team)->create()->id)
        ->set('form.passPrescriptions.0.mode', AppointmentMode::Remote->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-test="appointment-record-print-remote-passes-button"')
        ->assertSeeHtml(route('appointments.remote-pass-sheet', ['current_team' => $team, 'appointment' => $appointment]));
});

test('only those who manage the catalogs get the link to the pass type configuration', function (TeamRole $role, bool $seesLink) {
    [, $team] = actingAsTeamMember($role);
    $appointment = Appointment::factory()->for($team)->remote()->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($team)->withRecord(),
    ]);

    $component = Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->assertSeeText('Os dias e horários vêm da')
        ->assertSeeText('configuração');

    $seesLink
        ? $component->assertSeeHtml('data-test="appointment-record-pass-type-settings-link"')
        : $component->assertDontSeeHtml('data-test="appointment-record-pass-type-settings-link"');
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
]);
