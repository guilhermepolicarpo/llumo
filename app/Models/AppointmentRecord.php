<?php

namespace App\Models;

use App\Enums\InfiltrationRemovalPlace;
use Database\Factories\AppointmentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $appointment_id
 * @property int|null $mentor_id
 * @property string|null $fluid_instructions
 * @property string|null $infiltration_site
 * @property Carbon|null $infiltration_remove_on
 * @property InfiltrationRemovalPlace|null $infiltration_removal_place
 * @property int|null $infiltration_removal_appointment_id
 * @property Carbon|null $return_on
 * @property int|null $return_appointment_id
 * @property string|null $observations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Appointment $appointment
 * @property-read Mentor|null $mentor
 * @property-read Collection<int, FluidicRemedy> $fluidicRemedies
 * @property-read Collection<int, Guidance> $guidances
 * @property-read Collection<int, PassPrescription> $passPrescriptions
 * @property-read Appointment|null $infiltrationRemovalAppointment
 * @property-read Appointment|null $returnAppointment
 * @property-read string $guidance_summary
 */
#[Fillable([
    'appointment_id',
    'mentor_id',
    'fluid_instructions',
    'infiltration_site',
    'infiltration_remove_on',
    'infiltration_removal_place',
    'infiltration_removal_appointment_id',
    'return_on',
    'return_appointment_id',
    'observations',
])]
class AppointmentRecord extends Model
{
    /** @use HasFactory<AppointmentRecordFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the appointment this record was filled for.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the mentor who attended, including deleted mentors.
     *
     * @return BelongsTo<Mentor, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class)->withTrashed();
    }

    /**
     * Get the fluidic remedies prescribed, including deleted remedies, in the order they were picked.
     *
     * @return BelongsToMany<FluidicRemedy, $this>
     */
    public function fluidicRemedies(): BelongsToMany
    {
        return $this->belongsToMany(FluidicRemedy::class)->withPivot('position')->orderByPivot('position')->withTrashed();
    }

    /**
     * Get the guidances given, including deleted guidances, with their optional detail.
     *
     * @return BelongsToMany<Guidance, $this>
     */
    public function guidances(): BelongsToMany
    {
        return $this->belongsToMany(Guidance::class)->withPivot('detail')->withTrashed();
    }

    /**
     * Get the passes prescribed.
     *
     * @return HasMany<PassPrescription, $this>
     */
    public function passPrescriptions(): HasMany
    {
        return $this->hasMany(PassPrescription::class);
    }

    /**
     * Get the appointment scheduled to remove the infiltration.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function infiltrationRemovalAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'infiltration_removal_appointment_id');
    }

    /**
     * Get the return appointment.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function returnAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'return_appointment_id');
    }

    /**
     * Get the guidances given as a display string, each followed by its detail when there is one.
     *
     * @return Attribute<string, never>
     */
    protected function guidanceSummary(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->guidances
            ->map(fn (Guidance $guidance): string => $guidance->pivot->detail ? "{$guidance->name} ({$guidance->pivot->detail})" : $guidance->name)
            ->implode(', '));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'infiltration_remove_on' => 'date',
            'infiltration_removal_place' => InfiltrationRemovalPlace::class,
            'return_on' => 'date',
        ];
    }
}
