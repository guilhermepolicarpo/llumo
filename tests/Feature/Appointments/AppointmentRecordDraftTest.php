<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentRecordDraft;
use App\Models\AppointmentType;
use App\Models\FluidicRemedy;
use App\Models\Mentor;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-09-21 19:30:00');
    $this->user = User::factory()->create(['name' => 'Ana Atendente']);
    $this->team = teamOwnedBy($this->user);
    $this->appointment = Appointment::factory()->for($this->team)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($this->team)->withRecord(),
        'scheduled_on' => '2026-09-21',
        'attendant_id' => $this->user,
    ]);

    $this->mentor = Mentor::factory()->for($this->team)->create();

    $this->actingAs($this->user);
    $this->user->switchTeam($this->team);
});

test('saving a draft keeps the form state without validating or scheduling anything', function () {
    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->set('observations', 'Em andamento')
        ->set('infiltrationSite', 'Braço')
        ->set('infiltrationRemovalPlace', '')
        ->set('returnOn', '2026-10-11')
        ->set('schedulesReturn', true)
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertReturned(__('Draft saved at :time', ['time' => '19:30']));

    $draft = $this->appointment->recordDraft()->sole();

    expect($draft->user_id)->toBe($this->user->id)
        ->and($draft->data)->toMatchArray([
            'observations' => 'Em andamento',
            'infiltrationSite' => 'Braço',
            'returnOn' => '2026-10-11',
            'schedulesReturn' => true,
        ])
        ->and($this->appointment->record()->exists())->toBeFalse()
        ->and(Appointment::query()->count())->toBe(1);
});

test('reopening the record restores the draft', function () {
    $remedy = FluidicRemedy::factory()->for($this->team)->create();

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->set('observations', 'Em andamento')
        ->set('fluidicRemedyIds', [(string) $remedy->id])
        ->call('saveDraft');

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->assertSet('observations', 'Em andamento')
        ->assertSet('fluidicRemedyIds', [(string) $remedy->id])
        ->assertSeeHtml('data-test="appointment-record-restored-draft"')
        ->assertSee('Ana Atendente');
});

test('a draft older than the saved record is discarded', function () {
    AppointmentRecordDraft::factory()->for($this->appointment)->create([
        'data' => ['observations' => 'Rascunho antigo'],
        'updated_at' => now()->subHour(),
    ]);
    AppointmentRecord::factory()->for($this->appointment)->create(['observations' => 'Ficha salva']);

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->assertSet('observations', 'Ficha salva')
        ->assertDontSeeHtml('data-test="appointment-record-restored-draft"');

    expect($this->appointment->recordDraft()->exists())->toBeFalse();
});

test('saving or completing the record discards the draft', function (string $method) {
    AppointmentRecordDraft::factory()->for($this->appointment)->create();

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->set('mentorId', (string) $this->mentor->id)
        ->set('observations', 'Final')
        ->call($method)
        ->assertHasNoErrors();

    expect($this->appointment->recordDraft()->exists())->toBeFalse()
        ->and($this->appointment->record->observations)->toBe('Final');
})->with(['save', 'complete']);

test('the draft left in the queue is restored for whoever attends next', function () {
    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->set('observations', 'Parou aqui')
        ->call('returnToQueue');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Waiting);

    $colleague = User::factory()->create();
    $this->team->members()->attach($colleague, ['role' => 'member']);
    $this->actingAs($colleague);
    $colleague->switchTeam($this->team);

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment->fresh()])
        ->assertSet('observations', 'Parou aqui');

    expect($this->appointment->fresh())
        ->status->toBe(AppointmentStatus::InProgress)
        ->attendant_id->toBe($colleague->id);
});

test('discarding the draft goes back to the saved record', function () {
    AppointmentRecord::factory()->for($this->appointment)->create(['observations' => 'Ficha salva', 'updated_at' => now()->subHour()]);
    AppointmentRecordDraft::factory()->for($this->appointment)->create(['data' => ['observations' => 'Rascunho']]);

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->assertSet('observations', 'Rascunho')
        ->call('discardDraft')
        ->assertSet('observations', 'Ficha salva')
        ->assertSet('restoredDraft', null)
        ->assertDontSeeHtml('data-test="appointment-record-restored-draft"');

    expect($this->appointment->recordDraft()->exists())->toBeFalse();
});

test('a draft with unknown keys or mismatched types does not break the record', function () {
    AppointmentRecordDraft::factory()->for($this->appointment)->create(['data' => [
        'observations' => ['not', 'a', 'string'],
        'schedulesReturn' => 'yes',
        'passPrescriptions' => [['pass_type_id' => '1', 'quantity' => 2, 'mode' => 'in_person'], 'broken', ['quantity' => 3]],
        'appointment' => 'hijack',
        'fluidInstructions' => '3x ao dia',
    ]]);

    Livewire::test('pages::appointments.attend', ['appointment' => $this->appointment])
        ->assertOk()
        ->assertSet('observations', '')
        ->assertSet('schedulesReturn', false)
        ->assertSet('passPrescriptions', [['pass_type_id' => '1', 'quantity' => 2, 'mode' => 'in_person']])
        ->assertSet('fluidInstructions', '3x ao dia')
        ->assertSet('appointment.id', $this->appointment->id);
});

test('members cannot save a draft of another team appointment', function () {
    $otherTeam = teamOwnedBy(User::factory()->create());
    $appointment = Appointment::factory()->for($otherTeam)->inProgress()->create([
        'appointment_type_id' => AppointmentType::factory()->for($otherTeam)->withRecord(),
    ]);

    Livewire::test('pages::appointments.attend', ['appointment' => $appointment])
        ->assertForbidden();

    expect(AppointmentRecordDraft::query()->exists())->toBeFalse();
});
