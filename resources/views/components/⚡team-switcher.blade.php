<?php

use App\Data\UserTeam;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function currentTeam(): ?array
    {
        $team = Auth::user()->currentTeam;

        return $team ? [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'logo_url' => $team->logo_url,
        ] : null;
    }

    /**
     * @return Collection<int, UserTeam>
     */
    #[Computed]
    public function teams(): Collection
    {
        return Auth::user()->toUserTeams(includeCurrent: true);
    }

    public function switchTeam(string $slug): void
    {
        $user = Auth::user();

        abort_unless(
            $user->belongsToTeam($team = Team::where('slug', $slug)->firstOrFail()),
            403
        );

        $currentTeamSlug = $user->currentTeam?->slug;

        $user->switchTeam($team);

        if (! request()->header('Referer')) {
            $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);

            return;
        }

        if (! $currentTeamSlug) {
            $this->redirect(request()->header('Referer'), navigate: true);

            return;
        }

        $redirectTo = $this->replaceCurrentTeamInReferer(
            request()->header('Referer'),
            $currentTeamSlug,
            $team->slug,
        );

        $this->redirect($redirectTo ?? request()->header('Referer'), navigate: true);
    }

    protected function replaceCurrentTeamInReferer(string $referer, string $currentTeamSlug, string $newTeamSlug): ?string
    {
        $redirectTo = preg_replace(
            '#/'.preg_quote($currentTeamSlug, '#').'(?=/|\?|$)#',
            '/'.$newTeamSlug,
            $referer,
            1,
        );

        return preg_replace(
            '#([?&]current_team=)'.preg_quote($currentTeamSlug, '#').'(?=&|$)#',
            '$1'.$newTeamSlug,
            $redirectTo ?? $referer,
            1,
        );
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="start">
        @php($currentTeam = $this->currentTeam)

        <flux:button variant="ghost" class="group w-full justify-start in-data-flux-sidebar-collapsed-desktop:justify-center" data-test="team-switcher-trigger">
            @if ($currentTeam)
                <flux:avatar size="xs" :src="$currentTeam['logo_url']" :name="$currentTeam['name']" class="shrink-0" />
            @else
                <flux:icon name="users" class="size-4 in-data-flux-sidebar-collapsed-desktop:block" />
            @endif
            <span class="truncate font-semibold in-data-flux-sidebar-collapsed-desktop:hidden">{{ $currentTeam['name'] ?? __('Select Spiritist Center') }}</span>
            <flux:icon
                name="chevrons-up-down"
                variant="micro"
                class="ms-auto size-4 in-data-flux-sidebar-collapsed-desktop:hidden"
            />
        </flux:button>

        <flux:menu class="min-w-56">
            <flux:menu.heading>{{ __('Spiritist Centers') }}</flux:menu.heading>

            @foreach ($this->teams as $team)
                <flux:menu.item
                    wire:click="switchTeam('{{ $team->slug }}')"
                    class="cursor-pointer"
                    data-test="team-switcher-item"
                >
                    <div class="flex w-full items-center justify-between gap-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <flux:avatar size="xs" :src="$team->logoUrl" :name="$team->name" class="shrink-0" />
                            <span class="truncate">{{ $team->name }}</span>
                        </span>
                        @if ($team->isCurrent)
                            <flux:icon name="check" class="size-4" />
                        @endif
                    </div>
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />

            <flux:modal.trigger name="create-team-switcher">
                <flux:menu.item icon="plus" class="cursor-pointer" data-test="team-switcher-new-team">
                    {{ __('New Spiritist Center') }}
                </flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
</div>
