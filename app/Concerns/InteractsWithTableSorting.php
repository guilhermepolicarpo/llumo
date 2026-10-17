<?php

namespace App\Concerns;

use Livewire\Attributes\Locked;

/**
 * Sort a paginated table by one of the columns listed in the component's SORTABLE_COLUMNS constant.
 */
trait InteractsWithTableSorting
{
    /**
     * The column the list is sorted by, or an empty string for the component's default order.
     */
    #[Locked]
    public string $sortBy = '';

    /**
     * @var 'asc'|'desc'
     */
    #[Locked]
    public string $sortDirection = 'asc';

    /**
     * Sort the list by a column, flipping the direction when it is already the sorted one.
     */
    public function sort(string $column): void
    {
        if (! in_array($column, static::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }
}
