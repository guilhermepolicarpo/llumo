<?php

namespace App\Enums;

/**
 * The status of a loan, derived from its dates rather than stored.
 */
enum BookLoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Returned = 'returned';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Overdue => __('Overdue'),
            self::Returned => __('Returned'),
        };
    }

    /**
     * Get the badge color for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Overdue => 'red',
            self::Returned => 'zinc',
        };
    }
}
