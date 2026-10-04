<?php

namespace App\Models;

use App\Enums\AppointmentAction;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
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
     * Determine whether this appointment can be attended, either starting it or continuing it.
     */
    public function isAttendable(): bool
    {
        return $this->status === AppointmentStatus::InProgress || AppointmentAction::Start->isAvailableFor($this);
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
        $query->where('scheduled_on', '>=', $day->toDateString())
            ->where('scheduled_on', '<', $day->addDay()->toDateString());
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
     * Get the assisted person's name and scheduled date, used to identify the appointment in confirmations.
     *
     * @return Attribute<string, never>
     */
    protected function description(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->assistedPerson->name.' ('.$this->scheduled_on->format('d/m/Y').')');
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
