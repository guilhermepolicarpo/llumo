<?php

namespace App\Concerns;

use App\Actions\Appointments\SaveAppointmentRecordDraft;
use App\Actions\AppointmentTypes\CreateAppointmentType;
use App\Actions\Catalogs\CreateCatalogEntry;
use App\Enums\AppointmentMode;
use App\Enums\Catalog;
use App\Enums\InfiltrationRemovalPlace;
use App\Livewire\Forms\AppointmentRecordForm;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentType;
use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Mentor;
use App\Models\PassType;
use App\Rules\AppointmentTypeRules;
use App\Rules\CatalogRules;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
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
    public AppointmentRecordForm $form;

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
        return $this->appointment->team->appointmentTypes()->orderBy('name')->get(['id', 'name', 'daily_limit', 'weekdays']);
    }

    /**
     * Get how many appointments of the return type the chosen return date already has, and how many places are left.
     *
     * Also carries the warning shown when the return type does not take place on that weekday.
     *
     * @return array{count: int, limit: ?int, remaining: ?int, type: string, date: string, warning: ?string}|null
     */
    #[Computed]
    public function returnDayOccupancy(): ?array
    {
        $returnOn = $this->dateFrom($this->form->returnOn);
        $returnType = $this->appointmentTypes->firstWhere('id', (int) $this->form->returnAppointmentTypeId);

        if ($returnOn === null || $returnType === null) {
            return null;
        }

        $count = $this->returnAppointmentCountOn($returnType, $returnOn);
        $limit = $returnType->daily_limit;

        return [
            'count' => $count,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $count),
            'type' => $returnType->name,
            'date' => $returnOn->format('d/m/Y'),
            'warning' => $returnType->isOfferedOn($returnOn) ? null : $returnType->notOfferedWarning(),
        ];
    }

    /**
     * Get the warning shown when the infiltration removal falls on a weekday its type does not take place.
     *
     * It only warns: the removal can still be scheduled on another day as an exception.
     */
    #[Computed]
    public function removalDayWarning(): ?string
    {
        if (! InfiltrationRemovalPlace::tryFrom($this->form->infiltrationRemovalPlace)?->schedulesRemoval()) {
            return null;
        }

        $removeOn = $this->dateFrom($this->form->infiltrationRemoveOn);
        $removalType = $this->appointmentTypes->firstWhere('id', (int) $this->form->removalAppointmentTypeId);

        if ($removeOn === null || $removalType === null || $removalType->isOfferedOn($removeOn)) {
            return null;
        }

        return $removalType->notOfferedWarning();
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
     * Pre-fill the removal date three days after the appointment once an infiltration site is given,
     * moved to the next day the removal type takes place.
     */
    public function updatedFormInfiltrationSite(): void
    {
        if (trim($this->form->infiltrationSite) !== '' && $this->form->infiltrationRemoveOn === '') {
            $this->form->infiltrationRemoveOn = $this->nextOfferedDayFrom($this->form->removalAppointmentTypeId, $this->appointment->scheduled_on->addDays(3))->toDateString();
        }
    }

    /**
     * Set the return date the given number of days after the appointment, moved to the next day the return type takes place.
     */
    public function setReturnInDays(int $days): void
    {
        $this->form->returnOn = $this->nextOfferedDayFrom($this->form->returnAppointmentTypeId, $this->appointment->scheduled_on->addDays($days))->toDateString();
    }

    public function addPassPrescription(): void
    {
        $this->form->passPrescriptions[] = [
            'pass_type_id' => '',
            'quantity' => 1,
            'mode' => $this->appointment->mode->value,
        ];
    }

    public function removePassPrescription(int $index): void
    {
        unset($this->form->passPrescriptions[$index]);

        $this->form->passPrescriptions = array_values($this->form->passPrescriptions);
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
            Catalog::Mentor => $this->form->mentorId = $entryId,
            Catalog::FluidicRemedy => $this->form->fluidicRemedyIds[] = $entryId,
            Catalog::Guidance => $this->form->guidanceIds[] = $entryId,
            Catalog::PassType => $index !== null && isset($this->form->passPrescriptions[$index])
                ? $this->form->passPrescriptions[$index]['pass_type_id'] = $entryId
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
        $this->form->{$target} = (string) $appointmentType->id;

        unset($this->appointmentTypes);
        $this->resetValidation([$errorKey, 'form.'.$target]);

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

        $draft = $saveAppointmentRecordDraft->handle($this->appointment, Auth::user(), $this->form->all());

        return __('Draft saved at :time', ['time' => $draft->updated_at->format('H:i')]);
    }

    /**
     * Throw away the draft and go back to the saved record.
     */
    public function discardDraft(): void
    {
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $this->appointment->recordDraft()->delete();

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

        $this->form->fillFromDraft($draft->data);

        $this->restoredDraft = [
            'time' => $draft->updated_at->format('d/m/Y H:i'),
            'name' => $draft->user?->name,
        ];
    }

    /**
     * Hydrate the form with the appointment's saved record, or with the defaults for a new one.
     */
    protected function fillRecordForm(?AppointmentRecord $record): void
    {
        $this->form->reset();
        $this->form->returnAppointmentTypeId = (string) $this->appointment->appointment_type_id;
        $this->form->removalAppointmentTypeId = (string) ($this->lastRemovalAppointmentTypeId() ?? '');

        if ($record !== null) {
            $this->form->fillFromRecord($record);
        }
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
     * Get the first day, starting from the given one, on which the chosen follow-up type takes place.
     */
    private function nextOfferedDayFrom(string $appointmentTypeId, CarbonInterface $date): CarbonInterface
    {
        return $this->appointmentTypes->firstWhere('id', (int) $appointmentTypeId)?->nextOfferedDayFrom($date) ?? $date;
    }

    private function dateFrom(string $value): ?CarbonInterface
    {
        return $value === '' ? null : rescue(fn () => Date::createFromFormat('!Y-m-d', $value), report: false);
    }

    /**
     * Count the appointments of the given type already scheduled on the given day.
     *
     * The return this record already scheduled does not take a place.
     */
    private function returnAppointmentCountOn(AppointmentType $appointmentType, CarbonInterface $day): int
    {
        $linkedReturnId = $this->appointment->record()->value('return_appointment_id');

        return $this->appointment->team->appointments()
            ->where('appointment_type_id', $appointmentType->id)
            ->scheduledOn($day)
            ->takingPlace()
            ->when($linkedReturnId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->count();
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
}
