<?php

namespace App\Concerns;

use App\Actions\Appointments\SaveAppointmentRecordDraft;
use App\Actions\AppointmentTypes\CreateAppointmentType;
use App\Actions\Catalogs\CreateCatalogEntry;
use App\Enums\AppointmentMode;
use App\Enums\Catalog;
use App\Enums\InfiltrationRemovalPlace;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentRecordDraft;
use App\Models\AppointmentType;
use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Mentor;
use App\Models\PassPrescription;
use App\Models\PassType;
use App\Rules\AppointmentTypeRules;
use App\Rules\CatalogRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Json;
use Livewire\Attributes\Renderless;

/**
 * @property-read Appointment $appointment
 */
trait InteractsWithAppointmentRecordForm
{
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
     * @var array<string, string>
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
     * When and by whom the draft restored on mount was last changed, shown until it is saved or discarded.
     *
     * @var array{time: string, name: ?string}|null
     */
    public ?array $restoredDraft = null;

    /**
     * @return Collection<int, Mentor>
     */
    #[Computed]
    public function mentors(): Collection
    {
        return $this->catalogEntries(Catalog::Mentor);
    }

    /**
     * @return Collection<int, FluidicRemedy>
     */
    #[Computed]
    public function fluidicRemedies(): Collection
    {
        return $this->catalogEntries(Catalog::FluidicRemedy);
    }

    /**
     * @return Collection<int, Guidance>
     */
    #[Computed]
    public function guidances(): Collection
    {
        return $this->catalogEntries(Catalog::Guidance);
    }

    /**
     * @return Collection<int, PassType>
     */
    #[Computed]
    public function passTypes(): Collection
    {
        return $this->catalogEntries(Catalog::PassType);
    }

    /**
     * Get the team's appointment types, offered for the follow-up appointments.
     *
     * @return Collection<int, AppointmentType>
     */
    #[Computed]
    public function appointmentTypes(): Collection
    {
        return $this->appointment->team->appointmentTypes()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function modes(): array
    {
        return AppointmentMode::options();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function removalPlaces(): array
    {
        return InfiltrationRemovalPlace::options();
    }

    /**
     * Pre-fill the removal date three days after the appointment once an infiltration site is given.
     */
    public function updatedInfiltrationSite(): void
    {
        if (trim($this->infiltrationSite) !== '' && $this->infiltrationRemoveOn === '') {
            $this->infiltrationRemoveOn = $this->appointment->scheduled_on->addDays(3)->toDateString();
        }
    }

    public function addPassPrescription(): void
    {
        $this->passPrescriptions[] = [
            'pass_type_id' => '',
            'quantity' => 1,
            'mode' => $this->appointment->mode->value,
        ];
    }

    public function removePassPrescription(int $index): void
    {
        unset($this->passPrescriptions[$index]);

        $this->passPrescriptions = array_values($this->passPrescriptions);
        $this->resetValidation();
    }

    /**
     * Get every entry the record's pickers offer, loaded once in the background right after the page opens.
     *
     * Keyed by the pickers' cache key: each catalog's value, plus the appointment types of the follow-ups.
     *
     * @return array<string, list<array{id: string, name: string}>>
     */
    #[Json]
    public function pickerOptions(): array
    {
        Gate::authorize('view', [AppointmentRecord::class, $this->appointment]);

        $options = [];

        foreach (Catalog::cases() as $catalog) {
            $options[$catalog->value] = $this->toPickerOptions($this->catalogEntries($catalog));
        }

        $options['appointment_types'] = $this->toPickerOptions($this->appointmentTypes);

        return $options;
    }

    /**
     * Create an entry in one of the team's catalogs from a picker and select it right away.
     *
     * The index identifies the pass prescription row the new pass type is selected in.
     *
     * @return array{id: string, name: string}
     */
    public function createCatalogEntry(CreateCatalogEntry $createCatalogEntry, Catalog $catalog, string $name, ?int $index = null): array
    {
        $team = $this->appointment->team;

        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);
        Gate::authorize('create', [$catalog->modelClass(), $team]);

        $errorKey = 'new_'.$catalog->value;

        $validated = Validator::make([$errorKey => trim($name)], [$errorKey => CatalogRules::name($team, $catalog)], [], [$errorKey => __('name')])->validate();

        $entry = $createCatalogEntry->handle($team, $catalog, $validated[$errorKey]);
        $entryId = (string) $entry->getKey();

        match ($catalog) {
            Catalog::Mentor => $this->mentorId = $entryId,
            Catalog::FluidicRemedy => $this->fluidicRemedyIds[] = $entryId,
            Catalog::Guidance => $this->guidanceIds[] = $entryId,
            Catalog::PassType => $index !== null && isset($this->passPrescriptions[$index])
                ? $this->passPrescriptions[$index]['pass_type_id'] = $entryId
                : null,
        };

        unset($this->{$catalog->relationName()});
        $this->resetValidation($errorKey);

        Flux::toast(variant: 'success', text: $catalog->newEntryLabel());

        return ['id' => $entryId, 'name' => $entry->getAttribute('name')];
    }

    /**
     * Create an appointment type from a follow-up picker and select it right away.
     *
     * The target is the form property of the follow-up the new type is selected in.
     *
     * @return array{id: string, name: string}
     */
    public function createAppointmentType(CreateAppointmentType $createAppointmentType, string $name, string $target): array
    {
        $team = $this->appointment->team;

        abort_unless(in_array($target, ['removalAppointmentTypeId', 'returnAppointmentTypeId'], true), 404);

        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);
        Gate::authorize('create', [AppointmentType::class, $team]);

        $errorKey = 'new_'.$target;

        $validated = Validator::make([$errorKey => trim($name)], [$errorKey => AppointmentTypeRules::name($team)], [], [$errorKey => __('name')])->validate();

        $appointmentType = $createAppointmentType->handle($team, ['name' => $validated[$errorKey]]);
        $this->{$target} = (string) $appointmentType->id;

        unset($this->appointmentTypes);
        $this->resetValidation([$errorKey, $target]);

        Flux::toast(variant: 'success', text: __('Appointment type created.'));

        return ['id' => (string) $appointmentType->id, 'name' => $appointmentType->name];
    }

    /**
     * Keep the current, unsaved state of the form so it survives leaving the page.
     *
     * Nothing is validated or scheduled: that only happens when the record is really saved.
     */
    #[Renderless]
    public function saveDraft(SaveAppointmentRecordDraft $saveAppointmentRecordDraft): string
    {
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $draft = $saveAppointmentRecordDraft->handle($this->appointment, Auth::user(), $this->only($this->draftProperties()));

        return __('Draft saved at :time', ['time' => $draft->updated_at->format('H:i')]);
    }

    /**
     * Throw away the draft and go back to the saved record.
     */
    public function discardDraft(): void
    {
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $this->appointment->recordDraft()->delete();

        $this->reset($this->draftProperties());
        $this->restoredDraft = null;
        $this->resetValidation();
        $this->fillRecordForm($this->appointment->record()->first());
    }

    /**
     * Restore the appointment's draft when it is newer than the saved record, discarding it otherwise.
     */
    protected function restoreDraft(?AppointmentRecord $record): void
    {
        $draft = $this->appointment->recordDraft()->with('user')->first();

        if ($draft === null) {
            return;
        }

        if ($record !== null && $record->updated_at->greaterThanOrEqualTo($draft->updated_at)) {
            $draft->delete();

            return;
        }

        $this->fillFromDraft($draft);

        $this->restoredDraft = [
            'time' => $draft->updated_at->format('d/m/Y H:i'),
            'name' => $draft->user?->name,
        ];
    }

    /**
     * Apply the draft's values to the form, skipping unknown keys and values whose type no longer fits.
     */
    protected function fillFromDraft(AppointmentRecordDraft $draft): void
    {
        foreach ($this->draftProperties() as $property) {
            if (! array_key_exists($property, $draft->data)) {
                continue;
            }

            $value = $draft->data[$property];

            $this->{$property} = match (true) {
                $property === 'passPrescriptions' && is_array($value) => $this->draftPassPrescriptions($value),
                is_array($this->{$property}) && is_array($value) => array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value),
                is_string($this->{$property}) && is_scalar($value) => (string) $value,
                is_bool($this->{$property}) && is_bool($value) => $value,
                default => $this->{$property},
            };
        }

        $this->fluidicRemedyIds = array_values(array_filter($this->fluidicRemedyIds, fn (string $id): bool => $id !== ''));
        $this->guidanceIds = array_values(array_filter($this->guidanceIds, fn (string $id): bool => $id !== ''));
    }

    /**
     * The form properties kept in a draft.
     *
     * @return list<string>
     */
    protected function draftProperties(): array
    {
        return [
            'mentorId',
            'fluidicRemedyIds',
            'fluidInstructions',
            'guidanceIds',
            'guidanceDetails',
            'passPrescriptions',
            'infiltrationSite',
            'infiltrationRemoveOn',
            'infiltrationRemovalPlace',
            'removalAppointmentTypeId',
            'returnOn',
            'schedulesReturn',
            'returnAppointmentTypeId',
            'observations',
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

    /**
     * Hydrate the form with the appointment's saved record, or with the defaults for a new one.
     */
    protected function fillRecordForm(?AppointmentRecord $record): void
    {
        $this->returnAppointmentTypeId = (string) $this->appointment->appointment_type_id;
        $this->removalAppointmentTypeId = (string) ($this->lastRemovalAppointmentTypeId() ?? '');

        if ($record === null) {
            return;
        }

        $this->mentorId = (string) ($record->mentor_id ?? '');
        $this->fluidicRemedyIds = $record->fluidicRemedies->map(fn ($remedy): string => (string) $remedy->id)->values()->all();
        $this->fluidInstructions = $record->fluid_instructions ?? '';
        $this->guidanceIds = $record->guidances->map(fn (Guidance $guidance): string => (string) $guidance->id)->values()->all();
        $this->guidanceDetails = $record->guidances->mapWithKeys(fn (Guidance $guidance): array => [
            (string) $guidance->id => $guidance->pivot->detail ?? '',
        ])->all();
        $this->passPrescriptions = $record->passPrescriptions->map(fn (PassPrescription $prescription): array => [
            'pass_type_id' => (string) $prescription->pass_type_id,
            'quantity' => $prescription->quantity,
            'mode' => $prescription->mode->value,
        ])->values()->all();
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
     * Map validated form data to the attribute shape expected by the save action.
     *
     * @param  array<string, mixed>  $validated
     * @return array{mentor_id: ?int, fluidic_remedy_ids: array<int, int>, fluid_instructions: ?string, guidances: array<int, array{guidance_id: int, detail: ?string}>, pass_prescriptions: array<int, array{pass_type_id: int, quantity: int, mode: string}>, infiltration_site: ?string, infiltration_remove_on: ?string, infiltration_removal_place: ?string, removal_appointment_type_id: ?int, return_on: ?string, schedules_return: bool, return_appointment_type_id: ?int, observations: ?string}
     */
    protected function recordAttributes(array $validated): array
    {
        $guidanceIds = array_values(array_unique($validated['guidanceIds']));

        return [
            'mentor_id' => $this->nullableId($validated['mentorId']),
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
            'removal_appointment_type_id' => $this->nullableId($validated['removalAppointmentTypeId']),
            'return_on' => $validated['returnOn'],
            'schedules_return' => $validated['schedulesReturn'],
            'return_appointment_type_id' => $this->nullableId($validated['returnAppointmentTypeId']),
            'observations' => $validated['observations'],
        ];
    }

    /**
     * Get the type of the last infiltration removal this team scheduled, so the form learns the name the center uses.
     */
    protected function lastRemovalAppointmentTypeId(): ?int
    {
        return AppointmentRecord::query()
            ->join('appointments', 'appointments.id', '=', 'appointment_records.infiltration_removal_appointment_id')
            ->join('appointment_types', 'appointment_types.id', '=', 'appointments.appointment_type_id')
            ->where('appointments.team_id', $this->appointment->team_id)
            ->whereNull('appointments.deleted_at')
            ->whereNull('appointment_types.deleted_at')
            ->latest('appointments.id')
            ->value('appointments.appointment_type_id');
    }

    /**
     * Shape entries the way the catalog picker expects them.
     *
     * @param  Collection<int, Model>  $entries
     * @return list<array{id: string, name: string}>
     */
    private function toPickerOptions(Collection $entries): array
    {
        return $entries->map(fn (Model $entry): array => [
            'id' => (string) $entry->getKey(),
            'name' => $entry->getAttribute('name'),
        ])->values()->all();
    }

    /**
     * @return Collection<int, Model>
     */
    private function catalogEntries(Catalog $catalog): Collection
    {
        return $this->appointment->team->{$catalog->relationName()}()->orderBy('name')->get(['id', 'name']);
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
