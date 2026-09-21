<?php

namespace App\Actions\Appointments;

use App\Concerns\NormalizesBlankStrings;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use Illuminate\Support\Facades\DB;

class SaveAppointmentRecord
{
    use NormalizesBlankStrings;

    public function __construct(private SyncAppointmentRecordFollowUps $syncAppointmentRecordFollowUps) {}

    /**
     * Save the record filled while the assisted person was attended, scheduling the follow-ups it requests
     * and discarding the draft kept while it was being filled.
     *
     * @param  array{
     *     mentor_id: ?int,
     *     fluidic_remedy_ids: array<int, int>,
     *     fluid_instructions: ?string,
     *     guidances: array<int, array{guidance_id: int, detail: ?string}>,
     *     pass_prescriptions: array<int, array{pass_type_id: int, quantity: int, mode: string}>,
     *     infiltration_site: ?string,
     *     infiltration_remove_on: ?string,
     *     infiltration_removal_place: ?string,
     *     removal_appointment_type_id: ?int,
     *     return_on: ?string,
     *     schedules_return: bool,
     *     return_appointment_type_id: ?int,
     *     observations: ?string,
     * }  $attributes
     */
    public function handle(Appointment $appointment, array $attributes): AppointmentRecord
    {
        return DB::transaction(function () use ($appointment, $attributes): AppointmentRecord {
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

            $this->syncAppointmentRecordFollowUps->handle($appointment, $record, $attributes);

            $record->save();

            $record->fluidicRemedies()->sync(collect($attributes['fluidic_remedy_ids'])->values()->mapWithKeys(fn (int $remedyId, int $position): array => [
                $remedyId => ['position' => $position],
            ]));

            $record->guidances()->sync(collect($attributes['guidances'])->mapWithKeys(fn (array $guidance): array => [
                $guidance['guidance_id'] => ['detail' => $this->blankToNull($guidance['detail'])],
            ]));

            $record->passPrescriptions()->delete();
            $record->passPrescriptions()->createMany($attributes['pass_prescriptions']);

            $appointment->recordDraft()->delete();

            return $record;
        });
    }
}
