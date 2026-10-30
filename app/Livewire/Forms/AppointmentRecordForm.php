<?php

namespace App\Livewire\Forms;

use App\Concerns\NormalizesBlankStrings;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\Guidance;
use App\Models\PassPrescription;
use App\Rules\AppointmentRecordRules;
use Livewire\Form;

class AppointmentRecordForm extends Form
{
    use NormalizesBlankStrings;

    public string $mentorId = '';

    /**
     * @var list<string>
     */
    public array $fluidicRemedyIds = [];

    public string $fluidInstructions = '';

    /**
     * @var list<string>
     */
    public array $guidanceIds = [];

    /**
     * The optional detail of each selected guidance, keyed by guidance id.
     *
     * @var array<int, string>
     */
    public array $guidanceDetails = [];

    /**
     * @var list<array{pass_type_id: string, quantity: int|string, mode: string}>
     */
    public array $passPrescriptions = [];

    public string $infiltrationSite = '';

    public string $infiltrationRemoveOn = '';

    public string $infiltrationRemovalPlace = '';

    public string $removalAppointmentTypeId = '';

    public string $returnOn = '';

    public bool $schedulesReturn = false;

    public string $returnAppointmentTypeId = '';

    public string $observations = '';

    /**
     * Fill the form with what the given record holds, including the types of the follow-ups it scheduled.
     */
    public function fillFromRecord(AppointmentRecord $record): void
    {
        $this->mentorId = (string) ($record->mentor_id ?? '');
        $this->fluidicRemedyIds = array_values($record->fluidicRemedies->map(fn ($remedy): string => (string) $remedy->id)->all());
        $this->fluidInstructions = $record->fluid_instructions ?? '';
        $this->guidanceIds = array_values($record->guidances->map(fn (Guidance $guidance): string => (string) $guidance->id)->all());
        $this->guidanceDetails = $record->guidances->mapWithKeys(fn (Guidance $guidance): array => [
            $guidance->id => (string) $guidance->pivot->getAttribute('detail'),
        ])->all();
        $this->passPrescriptions = array_values($record->passPrescriptions->map(fn (PassPrescription $prescription): array => [
            'pass_type_id' => (string) $prescription->pass_type_id,
            'quantity' => $prescription->quantity,
            'mode' => $prescription->mode->value,
        ])->all());
        $this->infiltrationSite = $record->infiltration_site ?? '';
        $this->infiltrationRemoveOn = $record->infiltration_remove_on?->toDateString() ?? '';
        $this->infiltrationRemovalPlace = $record->infiltration_removal_place->value ?? '';
        $this->returnOn = $record->return_on?->toDateString() ?? '';
        $this->schedulesReturn = $record->return_appointment_id !== null;
        $this->observations = $record->observations ?? '';

        if ($record->infiltrationRemovalAppointment) {
            $this->removalAppointmentTypeId = (string) $record->infiltrationRemovalAppointment->appointment_type_id;
        }

        if ($record->returnAppointment) {
            $this->returnAppointmentTypeId = (string) $record->returnAppointment->appointment_type_id;
        }
    }

    /**
     * Apply a draft's values to the form, skipping unknown keys and values whose type no longer fits.
     *
     * @param  array<string, mixed>  $data
     */
    public function fillFromDraft(array $data): void
    {
        foreach ($this->all() as $property => $current) {
            if (! array_key_exists($property, $data)) {
                continue;
            }

            $value = $data[$property];

            $this->{$property} = match (true) {
                $property === 'passPrescriptions' && is_array($value) => $this->draftPassPrescriptions($value),
                is_array($current) && is_array($value) => array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value),
                is_string($current) && is_scalar($value) => (string) $value,
                is_bool($current) && is_bool($value) => $value,
                default => $current,
            };
        }

        $this->fluidicRemedyIds = array_values(array_filter($this->fluidicRemedyIds, fn (string $id): bool => $id !== ''));
        $this->guidanceIds = array_values(array_filter($this->guidanceIds, fn (string $id): bool => $id !== ''));
    }

    /**
     * Validate the form for the given appointment and map it to the attribute shape expected by the save action.
     *
     * @return array{mentor_id: ?int, fluidic_remedy_ids: array<int, int>, fluid_instructions: ?string, guidances: array<int, array{guidance_id: int, detail: ?string}>, pass_prescriptions: array<int, array{pass_type_id: int, quantity: int, mode: string}>, infiltration_site: ?string, infiltration_remove_on: ?string, infiltration_removal_place: ?string, removal_appointment_type_id: ?int, return_on: ?string, schedules_return: bool, return_appointment_type_id: ?int, observations: ?string}
     */
    public function validatedAttributes(Appointment $appointment): array
    {
        $validated = $this->validate(AppointmentRecordRules::all($appointment));

        $guidanceIds = array_values(array_unique($validated['guidanceIds']));

        return [
            'mentor_id' => $this->blankToNullInteger($validated['mentorId']),
            'fluidic_remedy_ids' => array_values(array_unique(array_map('intval', $validated['fluidicRemedyIds']))),
            'fluid_instructions' => $validated['fluidInstructions'],
            'guidances' => array_map(fn (string $guidanceId): array => [
                'guidance_id' => (int) $guidanceId,
                'detail' => $validated['guidanceDetails'][$guidanceId] ?? null,
            ], $guidanceIds),
            'pass_prescriptions' => array_map(fn (array $prescription): array => [
                'pass_type_id' => (int) $prescription['pass_type_id'],
                'quantity' => (int) $prescription['quantity'],
                'mode' => $prescription['mode'],
            ], $validated['passPrescriptions']),
            'infiltration_site' => $validated['infiltrationSite'],
            'infiltration_remove_on' => $validated['infiltrationRemoveOn'],
            'infiltration_removal_place' => $validated['infiltrationRemovalPlace'],
            'removal_appointment_type_id' => $this->blankToNullInteger($validated['removalAppointmentTypeId']),
            'return_on' => $validated['returnOn'],
            'schedules_return' => $validated['schedulesReturn'],
            'return_appointment_type_id' => $this->blankToNullInteger($validated['returnAppointmentTypeId']),
            'observations' => $validated['observations'],
        ];
    }

    /**
     * Rebuild the pass prescription rows of a draft, dropping rows that do not have the expected shape.
     *
     * @param  array<mixed>  $rows
     * @return list<array{pass_type_id: string, quantity: int|string, mode: string}>
     */
    private function draftPassPrescriptions(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'pass_type_id' => (string) $row['pass_type_id'],
            'quantity' => is_int($row['quantity']) ? $row['quantity'] : (string) $row['quantity'],
            'mode' => (string) $row['mode'],
        ], array_filter($rows, fn ($row): bool => is_array($row)
            && is_scalar($row['pass_type_id'] ?? null)
            && is_scalar($row['quantity'] ?? null)
            && is_scalar($row['mode'] ?? null))));
    }
}
