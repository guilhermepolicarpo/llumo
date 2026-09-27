<?php

namespace App\Enums;

use App\Models\FluidicRemedy;
use App\Models\Guidance;
use App\Models\Mentor;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

enum Catalog: string
{
    case Mentor = 'mentor';
    case FluidicRemedy = 'fluidic_remedy';
    case Guidance = 'guidance';
    case PassType = 'pass_type';

    /**
     * Find the catalog addressed by the given URL segment.
     */
    public static function fromSlug(string $slug): ?self
    {
        return collect(self::cases())->first(fn (self $catalog) => $catalog->slug() === $slug);
    }

    /**
     * Get the URL segment of the catalog's management page, derived from its team relationship name.
     */
    public function slug(): string
    {
        return Str::kebab($this->relationName());
    }

    /**
     * Get the URL of the catalog's management page, so callers never build it from the enum's backing value.
     */
    public function url(): string
    {
        return route('catalogs.index', ['catalog' => $this->slug()]);
    }

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
     * Get the plural title of the catalog shown on its management page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mentor => __('Mentors'),
            self::FluidicRemedy => __('Fluidic remedies'),
            self::Guidance => __('Guidances'),
            self::PassType => __('Pass types'),
        };
    }

    /**
     * Get the description of the catalog shown on its management page.
     */
    public function description(): string
    {
        return match ($this) {
            self::Mentor => __('Spiritual mentors who guide the appointments.'),
            self::FluidicRemedy => __('Fluidic remedies prescribed during the appointments.'),
            self::Guidance => __('Guidances given to the assisted people.'),
            self::PassType => __('Types of pass prescribed during the appointments.'),
        };
    }

    /**
     * Get the example shown in the name field while creating or editing an entry.
     */
    public function namePlaceholder(): string
    {
        return match ($this) {
            self::Mentor => __('E.g. Eurípedes Barsanulfo'),
            self::FluidicRemedy => __('E.g. Calming'),
            self::Guidance => __('E.g. Gospel at home'),
            self::PassType => __('E.g. Pass with 3 mediums'),
        };
    }

    /**
     * Get the title of the button and modal used to create an entry on the management page.
     */
    public function newEntryTitle(): string
    {
        return match ($this) {
            self::Mentor => __('New mentor'),
            self::FluidicRemedy => __('New fluidic remedy'),
            self::Guidance => __('New guidance'),
            self::PassType => __('New pass type'),
        };
    }

    /**
     * Get the title of the modal used to edit an entry.
     */
    public function editEntryTitle(): string
    {
        return match ($this) {
            self::Mentor => __('Edit mentor'),
            self::FluidicRemedy => __('Edit fluidic remedy'),
            self::Guidance => __('Edit guidance'),
            self::PassType => __('Edit pass type'),
        };
    }

    /**
     * Get the title of the modal used to delete an entry.
     */
    public function deleteEntryTitle(): string
    {
        return match ($this) {
            self::Mentor => __('Delete mentor'),
            self::FluidicRemedy => __('Delete fluidic remedy'),
            self::Guidance => __('Delete guidance'),
            self::PassType => __('Delete pass type'),
        };
    }

    /**
     * Get the label of the success message shown after an entry is updated.
     */
    public function updatedEntryLabel(): string
    {
        return match ($this) {
            self::Mentor => __('Mentor updated.'),
            self::FluidicRemedy => __('Fluidic remedy updated.'),
            self::Guidance => __('Guidance updated.'),
            self::PassType => __('Pass type updated.'),
        };
    }

    /**
     * Get the label of the success message shown after an entry is deleted.
     */
    public function deletedEntryLabel(): string
    {
        return match ($this) {
            self::Mentor => __('Mentor deleted.'),
            self::FluidicRemedy => __('Fluidic remedy deleted.'),
            self::Guidance => __('Guidance deleted.'),
            self::PassType => __('Pass type deleted.'),
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
