<?php

namespace App\Models;

use App\Enums\InfiltrationRemovalPlace;
use Carbon\CarbonInterface;
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
 * @property Carbon|null $infiltration_remove_at
 * @property InfiltrationRemovalPlace|null $infiltration_removal_place
 * @property int|null $infiltration_removal_appointment_id
 * @property Carbon|null $return_at
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
 * @property-read string $pass_summary
 * @property-read string $infiltration_summary
 * @property-read string|null $return_summary
 */
#[Fillable([
    'appointment_id',
    'mentor_id',
    'fluid_instructions',
    'infiltration_site',
    'infiltration_remove_at',
    'infiltration_removal_place',
    'infiltration_removal_appointment_id',
    'return_at',
    'return_appointment_id',
    'observations',
])]
class AppointmentRecord extends Model
{
    /** @use HasFactory<AppointmentRecordFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The usual intervals, in days after the appointment, offered for the return: as shortcuts on the record form and
     * as boxes on the printed attendance sheet.
     *
     * @var list<int>
     */
    public const array RETURN_INTERVALS_DAYS = [15, 20, 30, 60];

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
            ->map(fn (Guidance $guidance): string => ($detail = $guidance->pivot->getAttribute('detail')) ? "{$guidance->name} ({$detail})" : $guidance->name)
            ->implode(', '));
    }

    /**
     * Get the passes prescribed as a display string, each with its quantity and mode.
     *
     * @return Attribute<string, never>
     */
    protected function passSummary(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->passPrescriptions->pluck('label')->implode(', '));
    }

    /**
     * Get the infiltration as a display string: its site, when it is removed and where.
     *
     * @return Attribute<string, never>
     */
    protected function infiltrationSummary(): Attribute
    {
        return Attribute::make(get: fn (): string => collect([
            $this->infiltration_site,
            $this->infiltration_remove_at ? __('remove on :date', ['date' => self::dateWithTime($this->infiltration_remove_at)]) : null,
            $this->infiltration_removal_place?->label(),
        ])->filter()->implode(' · '));
    }

    /**
     * Get the return as a display string: its date and, when set, the time, as in "20/11/2026 at 19:15".
     *
     * @return Attribute<string|null, never>
     */
    protected function returnSummary(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->return_at ? self::dateWithTime($this->return_at) : null);
    }

    /**
     * Get the time of a follow-up as "H:i", or null when none was given: no center attends at midnight, so a
     * follow-up saved at midnight only has its day.
     */
    public static function timeOf(?CarbonInterface $at): ?string
    {
        $time = $at?->format('H:i');

        return $time === '00:00' ? null : $time;
    }

    /**
     * Format a follow-up with its time when there is one, as in "20/11/2026 at 19:15".
     */
    private static function dateWithTime(CarbonInterface $at): string
    {
        $time = self::timeOf($at);

        return $time === null
            ? $at->format('d/m/Y')
            : __(':date at :time', ['date' => $at->format('d/m/Y'), 'time' => $time]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'infiltration_remove_at' => 'datetime',
            'infiltration_removal_place' => InfiltrationRemovalPlace::class,
            'return_at' => 'datetime',
        ];
    }
}
