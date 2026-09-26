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
