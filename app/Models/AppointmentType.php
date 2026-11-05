<?php

namespace App\Models;

use App\Enums\Weekday;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property bool $requires_record
 * @property int|null $daily_limit
 * @property SupportCollection<int, Weekday>|null $weekdays
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, Appointment> $appointments
 */
#[Fillable([
    'team_id',
    'name',
    'requires_record',
    'daily_limit',
    'weekdays',
])]
class AppointmentType extends Model
{
    /** @use HasFactory<AppointmentTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team this appointment type belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the appointments of this type.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Determine whether appointments of this type only take place on some weekdays.
     */
    public function hasWeekdays(): bool
    {
        return filled($this->weekdays);
    }

    /**
     * Determine whether appointments of this type take place on the given date's weekday.
     *
     * A type without weekdays takes place on any day.
     */
    public function isOfferedOn(CarbonInterface $date): bool
    {
        return ! $this->hasWeekdays() || $this->weekdays->contains(Weekday::of($date));
    }

    /**
     * Get the first date, starting from the given one, on which appointments of this type take place.
     */
    public function nextOfferedDayFrom(CarbonInterface $date): CarbonInterface
    {
        for ($day = 0; $day < 7; $day++) {
            if ($this->isOfferedOn($date->addDays($day))) {
                return $date->addDays($day);
            }
        }

        return $date;
    }

    /**
     * Get the weekdays on which appointments of this type take place, as in "Mondays and Wednesdays".
     */
    public function weekdaysLabel(): string
    {
        return Arr::join($this->sortedWeekdays()->map(fn (Weekday $weekday): string => $weekday->pluralLabel())->all(), ', ', ' '.__('and').' ');
    }

    /**
     * Get the abbreviated weekdays on which appointments of this type take place, as in "Mon, Wed", or "Any day".
     */
    public function weekdaysShortLabel(): string
    {
        return $this->hasWeekdays()
            ? $this->sortedWeekdays()->map(fn (Weekday $weekday): string => $weekday->shortLabel())->implode(', ')
            : __('Any day');
    }

    /**
     * Get the warning shown when an appointment of this type falls on a day it does not take place.
     */
    public function notOfferedWarning(): string
    {
        return __(':type appointments only take place on :weekdays.', ['type' => $this->name, 'weekdays' => $this->weekdaysLabel()]);
    }

    /**
     * Get how many appointments of this type the given day already has, and how many places are left.
     *
     * Canceled appointments do not take a place, and neither does the given appointment. Filtering by team lets the
     * count use the team and date index instead of reading the type's whole history, so the type must be loaded with team_id.
     *
     * @return array{count: int, limit: ?int, remaining: ?int, type: string, date: string}
     */
    public function occupancyOn(CarbonInterface $day, ?int $exceptAppointmentId = null): array
    {
        $count = $this->appointments()
            ->where('team_id', $this->team_id)
            ->scheduledOn($day)
            ->takingPlace()
            ->when($exceptAppointmentId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->count();

        return [
            'count' => $count,
            'limit' => $this->daily_limit,
            'remaining' => $this->daily_limit === null ? null : max(0, $this->daily_limit - $count),
            'type' => $this->name,
            'date' => $day->format('d/m/Y'),
        ];
    }

    /**
     * @return SupportCollection<int, Weekday>
     */
    private function sortedWeekdays(): SupportCollection
    {
        return ($this->weekdays ?? collect())->sortBy(fn (Weekday $weekday): int => $weekday->value)->values();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_record' => 'boolean',
            'daily_limit' => 'integer',
            'weekdays' => AsEnumCollection::of(Weekday::class),
        ];
    }
}
