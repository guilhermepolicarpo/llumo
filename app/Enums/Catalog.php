<?php

namespace App\Enums;

use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Mentor;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Model;

enum Catalog: string
{
    case Mentor = 'mentor';
    case FluidicRemedy = 'fluidic_remedy';
    case Guidance = 'guidance';
    case PassType = 'pass_type';

    /**
     * Get the model class that stores the catalog's entries.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Mentor => Mentor::class,
            self::FluidicRemedy => FluidicRemedy::class,
            self::Guidance => Guidance::class,
            self::PassType => PassType::class,
        };
    }

    /**
     * Get the name of the team relationship that holds the catalog's entries.
     */
    public function relationName(): string
    {
        return match ($this) {
            self::Mentor => 'mentors',
            self::FluidicRemedy => 'fluidicRemedies',
            self::Guidance => 'guidances',
            self::PassType => 'passTypes',
        };
    }

    /**
     * Get the label of the success message shown after an entry is created inline.
     */
    public function newEntryLabel(): string
    {
        return match ($this) {
            self::Mentor => __('Mentor created.'),
            self::FluidicRemedy => __('Fluidic remedy created.'),
            self::Guidance => __('Guidance created.'),
            self::PassType => __('Pass type created.'),
        };
    }

    /**
     * Get the placeholder shown by the catalog picker while nothing is selected.
     */
    public function placeholder(): string
    {
        return match ($this) {
            self::Mentor => __('Select a mentor...'),
            self::FluidicRemedy => __('Select the fluidic remedies...'),
            self::Guidance => __('Search for a guidance...'),
            self::PassType => __('Select a pass...'),
        };
    }
}
