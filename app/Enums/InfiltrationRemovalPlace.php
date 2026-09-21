<?php

namespace App\Enums;

enum InfiltrationRemovalPlace: string
{
    case AtTheCenter = 'at_the_center';
    case AnotherCenter = 'another_center';
    case AtHome = 'at_home';

    /**
     * Get the display label for the place.
     */
    public function label(): string
    {
        return match ($this) {
            self::AtTheCenter => __('At this center'),
            self::AnotherCenter => __('At another center'),
            self::AtHome => __('At home'),
        };
    }

    /**
     * Determine whether removing the infiltration here requires scheduling an appointment at this center.
     */
    public function schedulesRemoval(): bool
    {
        return $this === self::AtTheCenter;
    }

    /**
     * Get the places as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $place): array => ['value' => $place->value, 'label' => $place->label()],
            self::cases(),
        );
    }
}
