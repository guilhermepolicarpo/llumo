<?php

namespace App\Models;

use App\Enums\AppointmentAction;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\TeamPermission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property int $team_id
 * @property int $appointment_type_id
 * @property int $assisted_person_id
 * @property AppointmentMode $mode
 * @property Carbon $scheduled_on
 * @property AppointmentStatus $status
 * @property Carbon|null $received_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $attendant_id
 * @property int|null $creator_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read AppointmentType $appointmentType
 * @property-read AssistedPerson $assistedPerson
 * @property-read User|null $attendant
 * @property-read User|null $creator
 * @property-read AppointmentRecord|null $record
 * @property-read AppointmentRecordDraft|null $recordDraft
 * @property-read AppointmentRecord|null $returnOfRecord
 * @property-read AppointmentRecord|null $infiltrationRemovalOfRecord
 * @property-read string $description
 * @property-read string|null $system_entry_summary
 */
#[Fillable([
    'team_id',
    'appointment_type_id',
    'assisted_person_id',
    'mode',
    'scheduled_on',
    'status',
    'received_at',
    'started_at',
    'finished_at',
    'attendant_id',
    'creator_id',
    'notes',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * How many days after its date an appointment's record can still be entered or corrected by any member.
     */
    public const int RECORD_EDITING_WINDOW_DAYS = 7;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'scheduled',
    ];

    /**
     * Get the team this appointment belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the type of this appointment, including deleted types.
     *
     * @return BelongsTo<AppointmentType, $this>
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class)->withTrashed();
    }

    /**
     * Get the assisted person scheduled for this appointment, including deleted people.
     *
     * @return BelongsTo<AssistedPerson, $this>
     */
    public function assistedPerson(): BelongsTo
    {
        return $this->belongsTo(AssistedPerson::class)->withTrashed();
    }

    /**
     * Get the user attending this appointment.
     *
     * @return BelongsTo<User, $this>
     */
    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    /**
     * Get the user who scheduled this appointment.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the record filled while the assisted person was attended.
     *
     * @return HasOne<AppointmentRecord, $this>
     */
    public function record(): HasOne
    {
        return $this->hasOne(AppointmentRecord::class);
    }

    /**
     * Get the unsaved draft of the record, kept while the record form is being filled.
     *
     * @return HasOne<AppointmentRecordDraft, $this>
     */
    public function recordDraft(): HasOne
    {
        return $this->hasOne(AppointmentRecordDraft::class);
    }

    /**
     * Get the record that scheduled this appointment as its return.
     *
     * @return HasOne<AppointmentRecord, $this>
     */
    public function returnOfRecord(): HasOne
    {
        return $this->hasOne(AppointmentRecord::class, 'return_appointment_id');
    }

    /**
     * Get the record that scheduled this appointment to remove its infiltration.
     *
     * @return HasOne<AppointmentRecord, $this>
     */
    public function infiltrationRemovalOfRecord(): HasOne
    {
        return $this->hasOne(AppointmentRecord::class, 'infiltration_removal_appointment_id');
    }

    /**
     * Determine whether this appointment's type is attended by filling a record.
     */
    public function usesRecord(): bool
    {
        return $this->appointmentType->requires_record;
    }

    /**
     * Determine whether this appointment is a return scheduled from the record of a previous attendance.
     */
    public function isReturn(): bool
    {
        return $this->returnOfRecord !== null;
    }

    /**
     * Determine whether the given user can attend this appointment, either starting it or continuing it.
     */
    public function isAttendableBy(User $user): bool
    {
        return $this->status === AppointmentStatus::InProgress
            || (AppointmentAction::Start->isAvailableFor($this) && AppointmentAction::Start->isPermittedFor($this, $user));
    }

    /**
     * Get the earliest date whose appointments any member can still enter or correct the record of.
     */
    public static function recordEditingCutoff(): CarbonImmutable
    {
        return today()->subDays(self::RECORD_EDITING_WINDOW_DAYS);
    }

    /**
     * Determine whether the appointment is recent enough for any member to enter or correct its record.
     */
    public function isWithinRecordEditingWindow(): bool
    {
        return $this->scheduled_on->greaterThanOrEqualTo(self::recordEditingCutoff());
    }

    /**
     * Determine whether the given user may enter or correct this appointment's record: anyone while it is recent,
     * and afterwards only those allowed to change past records.
     */
    public function acceptsRecordChangesFrom(User $user): bool
    {
        return $this->isWithinRecordEditingWindow()
            || $user->hasTeamPermission($this->team, TeamPermission::EditPastRecords);
    }

    /**
     * Determine whether an attendance sheet can be printed for this appointment: its type uses a record and the assisted person
     * has already arrived, or it was completed with a record, whose sheet is printed filled in.
     */
    public function hasPrintableAttendanceSheet(): bool
    {
        if (! $this->usesRecord()) {
            return false;
        }

        return in_array($this->status, [AppointmentStatus::Waiting, AppointmentStatus::InProgress], true)
            || $this->filledRecord() !== null;
    }

    /**
     * Determine whether the record of this appointment, as it stands, prescribes any pass given remotely.
     */
    public function hasRemotePasses(): bool
    {
        $standing = $this->standingRecord();

        return $standing instanceof AppointmentRecordDraft
            ? $this->remotePassesOfDraft($standing)->isNotEmpty()
            : $standing?->passPrescriptions()->where('mode', AppointmentMode::Remote)->exists() ?? false;
    }

    /**
     * Get the remote passes of the record as it stands, each with its pass type, quantity and the dates chosen, in date
     * order.
     *
     * The remote pass sheet is printed while the record is still being filled, before the mentor is picked, so an
     * unsaved draft newer than the saved record is what stands; its rows are read leniently, skipping what does not fit.
     *
     * @return SupportCollection<int, array{passType: PassType, quantity: int, dates: list<CarbonImmutable>}>
     */
    public function remotePasses(): SupportCollection
    {
        $standing = $this->standingRecord();

        if ($standing === null) {
            return collect();
        }

        if ($standing instanceof AppointmentRecordDraft) {
            return $this->remotePassesOfDraft($standing);
        }

        return $standing->passPrescriptions()
            ->where('mode', AppointmentMode::Remote)
            ->with(['passType', 'sessions'])
            ->get()
            ->map(fn (PassPrescription $prescription): array => [
                'passType' => $prescription->passType,
                'quantity' => $prescription->quantity,
                'dates' => array_values($prescription->sessions->map(fn (PassSession $session): CarbonImmutable => $session->scheduled_on->toImmutable())->all()),
            ]);
    }

    /**
     * Get the record as it stands: the unsaved draft when it is newer than the saved record, or else the saved record.
     */
    private function standingRecord(): AppointmentRecord|AppointmentRecordDraft|null
    {
        $record = $this->record()->first();
        $draft = $this->recordDraft()->first();

        return $draft !== null && ($record === null || $draft->updated_at->greaterThan($record->updated_at)) ? $draft : $record;
    }

    /**
     * Read the remote passes from the rows of a draft, keeping only the team's pass types, the quantities the form
     * accepts and real dates after the appointment, one per pass at most.
     *
     * @return SupportCollection<int, array{passType: PassType, quantity: int, dates: list<CarbonImmutable>}>
     */
    private function remotePassesOfDraft(AppointmentRecordDraft $draft): SupportCollection
    {
        $draftRows = $draft->data['passPrescriptions'] ?? [];

        $rows = collect(is_array($draftRows) ? $draftRows : [])
            ->filter(fn (mixed $row): bool => is_array($row)
                && ($row['mode'] ?? null) === AppointmentMode::Remote->value
                && is_numeric($row['pass_type_id'] ?? null)
                && is_numeric($row['quantity'] ?? null));

        $passTypes = $this->team->passTypes()->whereKey($rows->pluck('pass_type_id')->map(fn ($id): int => (int) $id)->all())->get()->keyBy('id');

        return $rows
            ->map(function (array $row) use ($passTypes): ?array {
                $passType = $passTypes->get((int) $row['pass_type_id']);

                if ($passType === null) {
                    return null;
                }

                $quantity = min(max((int) $row['quantity'], 1), 99);

                $dates = array_values(collect(is_array($row['dates'] ?? null) ? $row['dates'] : [])
                    ->filter(fn (mixed $date): bool => is_string($date)
                        && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1
                        && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))
                    ->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date))
                    ->filter(fn (CarbonImmutable $date): bool => $date->greaterThan($this->scheduled_on))
                    ->unique(fn (CarbonImmutable $date): string => $date->toDateString())
                    ->sortBy(fn (CarbonImmutable $date): string => $date->toDateString())
                    ->take($quantity)
                    ->all());

                return ['passType' => $passType, 'quantity' => $quantity, 'dates' => $dates];
            })
            ->filter()
            ->values();
    }

    /**
     * Get the record filled in when the appointment was completed, or null while it is still open.
     */
    public function filledRecord(): ?AppointmentRecord
    {
        return $this->status === AppointmentStatus::Completed ? $this->record : null;
    }

    /**
     * Scope the query to appointments waiting to be attended whose type uses a record.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function waitingForRecord(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Waiting)
            ->whereHas('appointmentType', fn (Builder $query) => $query->where('requires_record', true));
    }

    /**
     * Scope the query to appointments from previous days whose attendance was never entered in the system.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('scheduled_on', '<', today()->toDateString())
            ->whereIn('status', AppointmentStatus::open());
    }

    /**
     * Scope the query to appointments from today on whose attendance is not over yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('scheduled_on', '>=', today()->toDateString())
            ->whereIn('status', AppointmentStatus::open());
    }

    /**
     * Scope the query to appointments no longer ahead: those from previous days, and today's already finished ones.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('scheduled_on', '<', today()->toDateString())
            ->orWhereNotIn('status', AppointmentStatus::open()));
    }

    /**
     * Scope the query to appointments scheduled on the given day.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function scheduledOn(Builder $query, CarbonInterface $day): void
    {
        $this->scheduledBetween($query, $day, $day->addDay());
    }

    /**
     * Scope the query to appointments scheduled from the given day up to, but not including, the other one.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function scheduledBetween(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->where('scheduled_on', '>=', $from->toDateString())
            ->where('scheduled_on', '<', $until->toDateString());
    }

    /**
     * Scope the query to appointments that take a place in the day they are scheduled on, which every one but a canceled one does.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function takingPlace(Builder $query): void
    {
        $query->where('status', '!=', AppointmentStatus::Canceled);
    }

    /**
     * Scope the query to the appointments of the given type the assisted person has on the day, apart from canceled ones
     * and the given one, as a person may have only one of them.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function sameDayAs(Builder $query, int $assistedPersonId, int $appointmentTypeId, CarbonInterface $day, ?int $exceptAppointmentId = null): void
    {
        $query->where('assisted_person_id', $assistedPersonId)
            ->where('appointment_type_id', $appointmentTypeId)
            ->scheduledOn($day)
            ->takingPlace()
            ->when($exceptAppointmentId, fn (Builder $query, int $id) => $query->whereKeyNot($id));
    }

    /**
     * Order the query by the status, following the workflow order of the AppointmentStatus cases.
     *
     * @param  Builder<self>  $query
     * @param  'asc'|'desc'  $direction
     */
    #[Scope]
    protected function orderByStatus(Builder $query, string $direction = 'asc'): void
    {
        $positions = '';

        foreach (AppointmentStatus::cases() as $position => $status) {
            $positions .= " when '{$status->value}' then {$position}";
        }

        $query->orderByRaw("case status{$positions} end {$direction}");
    }

    /**
     * Get the assisted person's name and scheduled date, used to identify the appointment in confirmations.
     *
     * @return Attribute<string, never>
     */
    protected function description(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->assistedPerson->name.' ('.$this->scheduled_on->format('d/m/Y').')');
    }

    /**
     * Get who entered the concluded attendance in the system and when, or null while there is no such entry.
     *
     * @return Attribute<string|null, never>
     */
    protected function systemEntrySummary(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            if ($this->status !== AppointmentStatus::Completed || ! $this->started_at || ! $this->finished_at) {
                return null;
            }

            $start = $this->started_at->format('H:i');
            $end = $this->finished_at->format('H:i');
            $user = $this->attendant?->name;

            return __(match (true) {
                $user && $start === $end => 'Entered in the system by :user, at :start.',
                (bool) $user => 'Entered in the system by :user, from :start to :end.',
                $start === $end => 'Entered in the system at :start.',
                default => 'Entered in the system from :start to :end.',
            }, ['user' => $user, 'start' => $start, 'end' => $end]);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => AppointmentMode::class,
            'scheduled_on' => 'date',
            'status' => AppointmentStatus::class,
            'received_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
