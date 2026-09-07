<?php

namespace App\Actions\Teams;

use App\Concerns\NormalizesBlankStrings;
use App\Models\Team;
use App\Rules\PostalCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UpdateTeamProfile
{
    use NormalizesBlankStrings;

    /**
     * Update the team's name, address and logo.
     *
     * @param  array{name: string, postal_code?: ?string, street?: ?string, number?: ?string,
     *              complement?: ?string, district?: ?string, city?: ?string, state?: ?string}  $attributes
     */
    public function handle(Team $team, array $attributes, ?UploadedFile $logo = null): Team
    {
        $previousLogoPath = $team->logo_path;

        $newLogoPath = $logo ? $this->storeLogo($logo) : null;

        try {
            $team = DB::transaction(function () use ($team, $attributes, $newLogoPath) {
                $locked = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

                $locked->update($this->attributesToPersist($attributes, $newLogoPath));

                return $locked;
            });
        } catch (\Throwable $exception) {
            $this->deleteLogo($newLogoPath);

            throw $exception;
        }

        if ($newLogoPath !== null) {
            $this->deleteLogo($previousLogoPath);
        }

        return $team;
    }

    /**
     * Remove the team's logo, leaving the rest of the profile untouched.
     */
    public function removeLogo(Team $team): Team
    {
        $previousLogoPath = $team->logo_path;

        $team = DB::transaction(function () use ($team) {
            $locked = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $locked->update(['logo_path' => null]);

            return $locked;
        });

        $this->deleteLogo($previousLogoPath);

        return $team;
    }

    /**
     * Store the uploaded logo on the public disk.
     */
    protected function storeLogo(UploadedFile $logo): string
    {
        $path = $logo->store('team-logos', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The team logo could not be stored.');
        }

        return $path;
    }

    /**
     * Build the attribute list written to the team.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributesToPersist(array $attributes, ?string $newLogoPath): array
    {
        $persisted = [
            'name' => $attributes['name'],
            'postal_code' => PostalCode::digits($attributes['postal_code'] ?? null),
            'street' => $this->blankToNull($attributes['street'] ?? null),
            'number' => $this->blankToNull($attributes['number'] ?? null),
            'complement' => $this->blankToNull($attributes['complement'] ?? null),
            'district' => $this->blankToNull($attributes['district'] ?? null),
            'city' => $this->blankToNull($attributes['city'] ?? null),
            'state' => $this->blankToNull($attributes['state'] ?? null),
        ];

        if ($newLogoPath !== null) {
            $persisted['logo_path'] = $newLogoPath;
        }

        return $persisted;
    }

    /**
     * Delete a stored logo from the public disk.
     */
    protected function deleteLogo(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
