<?php

namespace App\Models;

use App\Enums\Weekday;
use Database\Factories\PassTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property SupportCollection<int, Weekday>|null $remote_weekdays
 * @property string|null $remote_starts_at
 * @property string|null $remote_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'remote_weekdays',
    'remote_starts_at',
    'remote_ends_at',
])]
class PassType extends Model
{
    /** @use HasFactory<PassTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team this pass type belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the weekdays on which the center gives this pass remotely, in week order.
     *
     * @return list<int>
     */
    public function remoteWeekdayNumbers(): array
    {
        return ($this->remote_weekdays ?? collect())
            ->map(fn (Weekday $weekday): int => $weekday->value)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get the time the assisted person rests to receive this pass remotely, as in "from 8pm to 8:30pm", or null when
     * the center has not set it.
     */
    public function remoteTimeLabel(): ?string
    {
        $hours = $this->remoteHours();

        if ($hours === null) {
            return null;
        }

        return __('from :start to :end', $hours);
    }

    /**
     * Get when the center gives this pass remotely, as in "Wednesdays, from 8pm to 8:30pm", or null when it has not
     * set the days nor the time.
     */
    public function remoteScheduleLabel(): ?string
    {
        return $this->joinRemoteSchedule(', ', $this->remoteTimeLabel());
    }

    /**
     * Get when the center gives this pass remotely the short way the pass room cards show it, as in
     * "Wednesdays · 8pm to 8:30pm", or null when it has not set the days nor the time.
     */
    public function remoteCardScheduleLabel(): ?string
    {
        $hours = $this->remoteHours();

        return $this->joinRemoteSchedule(' · ', $hours === null ? null : __(':start to :end', $hours));
    }

    /**
     * Get both ends of the rest time, formatted, or null when the center has not set it.
     *
     * @return array{start: string, end: string}|null
     */
    private function remoteHours(): ?array
    {
        if ($this->remote_starts_at === null || $this->remote_ends_at === null) {
            return null;
        }

        return [
            'start' => $this->formatTime($this->remote_starts_at),
            'end' => $this->formatTime($this->remote_ends_at),
        ];
    }

    /**
     * Format a time the way the center writes it, as in "20h" or "20h30".
     */
    private function formatTime(string $time): string
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours.'h'.($minutes === 0 ? '' : str_pad((string) $minutes, 2, '0', STR_PAD_LEFT));
    }

    /**
     * Join the remote weekdays, as in "Mondays and Wednesdays", and the given time with the separator.
     */
    private function joinRemoteSchedule(string $separator, ?string $time): ?string
    {
        $weekdays = Weekday::pluralList($this->remote_weekdays ?? []);

        $schedule = collect([$weekdays, $time])->filter()->implode($separator);

        return $schedule === '' ? null : ucfirst($schedule);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remote_weekdays' => AsEnumCollection::of(Weekday::class),
        ];
    }
}
