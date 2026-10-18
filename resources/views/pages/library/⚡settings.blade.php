<?php

use App\Actions\Library\UpdateLibrarySettings;
use App\Models\BookLoan;
use App\Models\Team;
use App\Rules\LibrarySettingsRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $loanPeriodDays = '';

    public string $loanMaxRenewals = '';

    public string $loanMaxActivePerPerson = '';

    public function mount(): void
    {
        $team = $this->team();

        Gate::authorize('viewAny', [BookLoan::class, $team]);

        $this->loanPeriodDays = (string) $team->loan_period_days;
        $this->loanMaxRenewals = (string) ($team->loan_max_renewals ?? '');
        $this->loanMaxActivePerPerson = (string) ($team->loan_max_active_per_person ?? '');
    }

    public function saveSettings(UpdateLibrarySettings $updateLibrarySettings): void
    {
        $team = $this->team();

        Gate::authorize('update', $team);

        $validated = $this->validate(LibrarySettingsRules::all());

        $updateLibrarySettings->handle($team, [
            'loan_period_days' => $validated['loanPeriodDays'],
            'loan_max_renewals' => $validated['loanMaxRenewals'],
            'loan_max_active_per_person' => $validated['loanMaxActivePerPerson'],
        ]);

        Flux::toast(variant: 'success', text: __('Loan rules updated.'));
    }

    #[Computed]
    public function canUpdate(): bool
    {
        return Auth::user()->toTeamPermissions($this->team())->canUpdateTeam;
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title(__('Loan rules'));
    }
}; ?>

<section class="w-full">
    @include('partials.library-heading')

    <x-pages::library.layout :heading="__('Loan rules')" :subheading="__('How long books are lent for and the limits applied to each assisted person.')">
        <form wire:submit="saveSettings" class="space-y-6">
            <flux:fieldset :disabled="! $this->canUpdate">
                <flux:input
                    type="number"
                    min="1"
                    max="365"
                    wire:model="loanPeriodDays"
                    :label="__('Loan period (days)')"
                    :description="__('Suggested due date for new loans and how much each renewal adds.')"
                    required
                    data-test="library-loan-period-days-input"
                />

                <flux:input
                    type="number"
                    min="0"
                    max="99"
                    wire:model="loanMaxRenewals"
                    :label="__('Maximum renewals per loan')"
                    :description="__('Leave blank for no limit.')"
                    data-test="library-loan-max-renewals-input"
                />

                <flux:input
                    type="number"
                    min="1"
                    max="99"
                    wire:model="loanMaxActivePerPerson"
                    :label="__('Maximum open loans per assisted person')"
                    :description="__('Leave blank for no limit. Assisted people with an overdue book cannot borrow another one.')"
                    data-test="library-loan-max-active-input"
                />
            </flux:fieldset>

            @if ($this->canUpdate)
                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="library-settings-save-button">{{ __('Save') }}</flux:button>
                </div>
            @else
                <flux:text>{{ __('Only the team\'s owner and admins can change the loan rules.') }}</flux:text>
            @endif
        </form>
    </x-pages::library.layout>
</section>
