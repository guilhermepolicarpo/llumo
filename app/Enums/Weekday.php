<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * A day of the week, numbered the ISO-8601 way (Monday is 1, Sunday is 7).
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /**
     * Get the weekday the given date falls on.
     */
    public static function of(CarbonInterface $date): self
    {
        return self::from($date->dayOfWeekIso);
    }

    /**
     * Get the plural display label for the weekday, as in "only on Mondays".
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Monday => __('Mondays'),
            self::Tuesday => __('Tuesdays'),
            self::Wednesday => __('Wednesdays'),
            self::Thursday => __('Thursdays'),
            self::Friday => __('Fridays'),
            self::Saturday => __('Saturdays'),
            self::Sunday => __('Sundays'),
        };
    }

    /**
     * Get the abbreviated display label for the weekday.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => __('Mon'),
            self::Tuesday => __('Tue'),
            self::Wednesday => __('Wed'),
            self::Thursday => __('Thu'),
            self::Friday => __('Fri'),
            self::Saturday => __('Sat'),
            self::Sunday => __('Sun'),
        };
    }

    /**
     * Get the weekdays as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $weekday): array => ['value' => (string) $weekday->value, 'label' => $weekday->shortLabel()],
            self::cases(),
        );
    }
}
