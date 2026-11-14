<?php

namespace App\Enums;

/**
 * A part of the blank attendance sheet the center may leave out of print. The fluidic remedies are the sheet's back.
 */
enum AttendanceSheetSection: string
{
    case Guidances = 'guidances';
    case Passes = 'passes';
    case Infiltration = 'infiltration';
    case Return = 'return';
    case Observations = 'observations';
    case FluidicRemedies = 'fluidic_remedies';

    /**
     * Get the sections whose entries come from a team catalog, chosen one by one on the settings page.
     *
     * @return array<int, self>
     */
    public static function withCatalog(): array
    {
        return array_values(array_filter(self::cases(), fn (self $section): bool => $section->catalog() !== null));
    }

    /**
     * Get the catalog whose entries the section lists, or null when it lists none.
     */
    public function catalog(): ?Catalog
    {
        return match ($this) {
            self::Guidances => Catalog::Guidance,
            self::Passes => Catalog::PassType,
            default => null,
        };
    }

    /**
     * Get the label of the checkbox that picks every catalog entry of the section at once.
     */
    public function allEntriesLabel(): string
    {
        return match ($this) {
            self::Guidances => __('All guidances'),
            self::Passes => __('All passes'),
            default => __('All'),
        };
    }

    /**
     * Get the name of the section as printed on the sheet.
     */
    public function label(): string
    {
        return match ($this) {
            self::Guidances => __('Guidances'),
            self::Passes => __('Passes'),
            self::Infiltration => __('Infiltration'),
            self::Return => __('Return'),
            self::Observations => __('Observations'),
            self::FluidicRemedies => __('Back with the fluidic remedies'),
        };
    }

    /**
     * Get what the section brings to the sheet, shown beside its switch on the settings page.
     */
    public function description(): string
    {
        return match ($this) {
            self::Guidances => __('The selected guidances, each with a box to tick.'),
            self::Passes => __('The selected passes, with the quantity and the mode to mark.'),
            self::Infiltration => __('Lines for the site or reason and the day to remove it.'),
            self::Return => __('Boxes for the usual return intervals and a line for another date.'),
            self::Observations => __('Lines for notes, filling the room left at the bottom of the front.'),
            self::FluidicRemedies => __('A second page, lined to write the fluidic remedies. Without it, each sheet takes a single page, so do not print a batch double-sided.'),
        };
    }
}
