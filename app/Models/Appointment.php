<?php

namespace App\Models;

use App\Enums\AppointmentAction;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
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
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read AppointmentType $appointmentType
 * @property-read AssistedPerson $assistedPerson
 * @property-read User|null $attendant
 * @property-read AppointmentRecord|null $record
 * @property-read AppointmentRecordDraft|null $recordDraft
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
     * Determine whether this appointment's type is attended by filling a record.
     */
    public function usesRecord(): bool
    {
        return $this->appointmentType->requires_record;
    }

    /**
     * Determine whether this appointment can be attended, either starting it or continuing it.
     */
    public function isAttendable(): bool
    {
        return $this->status === AppointmentStatus::InProgress || AppointmentAction::Start->isAvailableFor($this);
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
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Waiting, AppointmentStatus::InProgress]);
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
