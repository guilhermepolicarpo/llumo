<?php

namespace App\Enums;

/**
 * What the appointments list prints for the day: the attendance sheets of who waits for a record, the list of the open
 * appointments without one, or both in a single PDF.
 */
enum AppointmentPrint: string
{
    case Sheets = 'sheets';
    case List = 'list';
    case Both = 'both';

    /**
     * Get the label of the button or menu item that prints it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sheets => __('Print sheets'),
            self::List => __('Print list'),
            self::Both => __('Sheets and list'),
        };
    }

    /**
     * Get the Heroicon name of the button or menu item that prints it.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Sheets => 'printer',
            self::List => 'list-bullet',
            self::Both => 'document-duplicate',
        };
    }

    /**
     * Determine whether it prints the attendance sheets.
     */
    public function printsSheets(): bool
    {
        return $this !== self::List;
    }

    /**
     * Determine whether it prints the list of appointments without a sheet.
     */
    public function printsList(): bool
    {
        return $this !== self::Sheets;
    }
}
