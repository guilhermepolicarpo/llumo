<?php

namespace App\Actions\Appointments;

use App\Concerns\NormalizesBlankStrings;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveAppointmentRecord
{
    use NormalizesBlankStrings;

    public function __construct(
        private SyncAppointmentRecordFollowUps $syncAppointmentRecordFollowUps,
        private TakeOverAppointment $takeOverAppointment,
    ) {}

    /**
     * Save the record filled while the assisted person was attended, scheduling the follow-ups it requests and keeping
     * the days of its remote passes, and discard the draft kept while it was being filled. Whoever saves the record of
     * an appointment someone else is attending takes it over, from the moment they opened the record.
     *
     * @param  array{
     *     mentor_id: ?int,
     *     fluidic_remedy_ids: array<int, int>,
     *     fluid_instructions: ?string,
     *     guidances: array<int, array{guidance_id: int, detail: ?string}>,
     *     pass_prescriptions: array<int, array{pass_type_id: int, quantity: int, mode: string, dates?: list<string>}>,
     *     infiltration_site: ?string,
     *     infiltration_remove_on: ?string,
     *     infiltration_removal_place: ?string,
     *     removal_appointment_type_id: ?int,
     *     return_on: ?string,
     *     return_appointment_type_id: ?int,
     *     observations: ?string,
     * }  $attributes
     */
    public function handle(Appointment $appointment, User $user, array $attributes, CarbonImmutable $openedAt): AppointmentRecord
    {
        return DB::transaction(function () use ($appointment, $user, $attributes, $openedAt): AppointmentRecord {
            $record = $appointment->record()->firstOrNew();

            $record->fill([
                'mentor_id' => $attributes['mentor_id'],
                'fluid_instructions' => $this->blankToNull($attributes['fluid_instructions']),
                'infiltration_site' => $this->blankToNull($attributes['infiltration_site']),
                'infiltration_remove_on' => $this->blankToNull($attributes['infiltration_remove_on']),
                'infiltration_removal_place' => $this->blankToNull($attributes['infiltration_removal_place']),
                'return_on' => $this->blankToNull($attributes['return_on']),
                'observations' => $this->blankToNull($attributes['observations']),
            ]);

            $this->syncAppointmentRecordFollowUps->handle($appointment, $record, $user, $attributes);

            $record->save();

            $record->fluidicRemedies()->sync(collect($attributes['fluidic_remedy_ids'])->values()->mapWithKeys(fn (int $remedyId, int $position): array => [
                $remedyId => ['position' => $position],
            ]));

            $record->guidances()->sync(collect($attributes['guidances'])->mapWithKeys(fn (array $guidance): array => [
                $guidance['guidance_id'] => ['detail' => $this->blankToNull($guidance['detail'])],
            ]));

            // Replacing the prescriptions also drops their remote pass days, which go with them on delete.
            $record->passPrescriptions()->delete();

            foreach ($attributes['pass_prescriptions'] as $prescription) {
                $record->passPrescriptions()
                    ->create(Arr::except($prescription, 'dates'))
                    ->sessions()
                    ->createMany(array_map(fn (string $date): array => ['scheduled_on' => $date], $prescription['dates'] ?? []));
            }

            $appointment->recordDraft()->delete();

            $this->takeOverAppointment->handle($appointment, $user, $openedAt);

            return $record;
        });
    }
}
