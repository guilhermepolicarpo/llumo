<?php

namespace App\Enums;

enum AppointmentMode: string
{
    case InPerson = 'in_person';
    case Remote = 'remote';

    /**
     * Get the display label for the mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::InPerson => __('In person'),
            self::Remote => __('Remote'),
        };
    }

    /**
     * Determine whether the assisted person is expected at the centre, so the appointment is received on
     * arrival and missed when they never come.
     */
    public function expectsArrival(): bool
    {
        return $this === self::InPerson;
    }

    /**
     * Get the modes whose assisted person is expected at the centre.
     *
     * @return array<int, self>
     */
    public static function expectingArrival(): array
    {
        return array_values(array_filter(self::cases(), fn (self $mode): bool => $mode->expectsArrival()));
    }

    /**
     * Get the Heroicon name for the mode.
     */
    public function icon(): string
    {
        return match ($this) {
            self::InPerson => 'map-pin',
            self::Remote => 'wifi',
        };
    }

    /**
     * Get the modes as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}
