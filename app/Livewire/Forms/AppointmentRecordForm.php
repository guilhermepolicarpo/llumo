<?php

namespace App\Livewire\Forms;

use App\Concerns\NormalizesBlankStrings;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\Guidance;
use App\Models\PassPrescription;
use App\Models\PassSession;
use App\Rules\AppointmentRecordRules;
use Carbon\CarbonInterface;
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
     * Each row's dates are the days the assisted person receives the passes remotely, as 'Y-m-d' strings or blank.
     *
     * @var list<array{pass_type_id: string, quantity: int|string, mode: string, dates: list<string>}>
     */
    public array $passPrescriptions = [];

    public string $infiltrationSite = '';

    /**
     * When the infiltration is removed, as 'Y-m-d' or, with a time, 'Y-m-d H:i'.
     */
    public string $infiltrationRemoveAt = '';

    public string $infiltrationRemovalPlace = '';

    public string $removalAppointmentTypeId = '';

    /**
     * When the assisted person returns, as 'Y-m-d' or, with a time, 'Y-m-d H:i'.
     */
    public string $returnAt = '';

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
            'dates' => array_values($prescription->sessions->map(fn (PassSession $session): string => $session->scheduled_on->toDateString())->all()),
        ])->all());
        $this->infiltrationSite = $record->infiltration_site ?? '';
        $this->infiltrationRemoveAt = $this->followUpValue($record->infiltration_remove_at);
        $this->infiltrationRemovalPlace = $record->infiltration_removal_place->value ?? '';
        $this->returnAt = $this->followUpValue($record->return_at);
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
        $data['infiltrationRemoveAt'] ??= $data['infiltrationRemoveOn'] ?? null;
        $data['returnAt'] ??= $data['returnOn'] ?? null;

        foreach ($this->all() as $property => $current) {
            if (! array_key_exists($property, $data)) {
                continue;
            }

            $value = $data[$property];

            $this->{$property} = match (true) {
                $property === 'passPrescriptions' && is_array($value) => $this->draftPassPrescriptions($value),
                is_array($current) && is_array($value) => array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value),
                is_string($current) && is_scalar($value) => (string) $value,
                default => $current,
            };
        }

        $this->fluidicRemedyIds = array_values(array_filter($this->fluidicRemedyIds, fn (string $id): bool => $id !== ''));
        $this->guidanceIds = array_values(array_filter($this->guidanceIds, fn (string $id): bool => $id !== ''));
    }

    /**
     * Validate the form for the given appointment and map it to the attribute shape expected by the save action.
     *
     * @return array{mentor_id: ?int, fluidic_remedy_ids: array<int, int>, fluid_instructions: ?string, guidances: array<int, array{guidance_id: int, detail: ?string}>, pass_prescriptions: array<int, array{pass_type_id: int, quantity: int, mode: string, dates: list<string>}>, infiltration_site: ?string, infiltration_remove_at: ?string, infiltration_removal_place: ?string, removal_appointment_type_id: ?int, return_at: ?string, return_appointment_type_id: ?int, observations: ?string}
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
                'dates' => $this->filledDates($prescription['dates'] ?? []),
            ], $validated['passPrescriptions']),
            'infiltration_site' => $validated['infiltrationSite'],
            'infiltration_remove_at' => $validated['infiltrationRemoveAt'],
            'infiltration_removal_place' => $validated['infiltrationRemovalPlace'],
            'removal_appointment_type_id' => $this->blankToNullInteger($validated['removalAppointmentTypeId'] ?? null),
            'return_at' => $validated['returnAt'],
            'return_appointment_type_id' => $this->blankToNullInteger($validated['returnAppointmentTypeId'] ?? null),
            'observations' => $validated['observations'],
        ];
    }

    /**
     * Validate only the pass prescriptions, so the remote pass sheet can be printed before the rest of the record is
     * filled in.
     */
    public function validatePassPrescriptions(Appointment $appointment): void
    {
        $this->validate(AppointmentRecordRules::passPrescriptions($appointment));
    }

    /**
     * Rebuild the pass prescription rows of a draft, dropping rows that do not have the expected shape.
     *
     * Drafts kept before remote pass dates existed have no dates, so their rows start without any.
     *
     * @param  array<mixed>  $rows
     * @return list<array{pass_type_id: string, quantity: int|string, mode: string, dates: list<string>}>
     */
    private function draftPassPrescriptions(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'pass_type_id' => (string) $row['pass_type_id'],
            'quantity' => is_int($row['quantity']) ? $row['quantity'] : (string) $row['quantity'],
            'mode' => (string) $row['mode'],
            'dates' => is_array($row['dates'] ?? null) ? array_values(array_map(fn (mixed $date): string => is_scalar($date) ? (string) $date : '', $row['dates'])) : [],
        ], array_filter($rows, fn ($row): bool => is_array($row)
            && is_scalar($row['pass_type_id'] ?? null)
            && is_scalar($row['quantity'] ?? null)
            && is_scalar($row['mode'] ?? null))));
    }

    /**
     * Keep only the dates actually chosen for a remote prescription, in date order.
     *
     * @param  array<int, ?string>  $dates
     * @return list<string>
     */
    private function filledDates(array $dates): array
    {
        $filled = array_filter($dates);
        sort($filled);

        return $filled;
    }

    /**
     * Split a follow-up as the form holds it, 'Y-m-d' or 'Y-m-d H:i', into its day and its time, either of them blank.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitFollowUp(string $value): array
    {
        return explode(' ', $value, 2) + ['', ''];
    }

    /**
     * Join a day and a time, which may be blank, into a follow-up as the form holds it.
     */
    public static function joinFollowUp(string $day, ?string $time): string
    {
        return $day !== '' && filled($time) ? $day.' '.$time : $day;
    }

    /**
     * Get a saved follow-up as the form holds it, where midnight stands for no time.
     */
    private function followUpValue(?CarbonInterface $at): string
    {
        return $at === null ? '' : self::joinFollowUp($at->toDateString(), AppointmentRecord::timeOf($at));
    }
}
