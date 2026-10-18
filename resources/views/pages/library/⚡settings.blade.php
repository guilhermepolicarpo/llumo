<?php

use App\Actions\Library\UpdateLibrarySettings;
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

        Gate::authorize('update', $team);

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

    /**
     * Describe, with the saved rules, until when a book lent today would stay with the assisted person.
     */
    #[Computed]
    public function example(): string
    {
        $team = $this->team();
        $dueOn = today()->addDays($team->loan_period_days);
        $dates = ['today' => today()->format('d/m/Y'), 'due' => $dueOn->format('d/m/Y')];

        return match ($team->loan_max_renewals) {
            null => __('In practice: a book lent today (:today) is due by :due and can be renewed with no limit.', $dates),
            0 => __('In practice: a book lent today (:today) is due by :due and cannot be renewed.', $dates),
            default => trans_choice('In practice: a book lent today (:today) is due by :due and, with :count renewal, can stay until :last.|In practice: a book lent today (:today) is due by :due and, with :count renewals, can stay until :last.', $team->loan_max_renewals, [
                ...$dates,
                'last' => $dueOn->addDays($team->loan_max_renewals * $team->loan_period_days)->format('d/m/Y'),
            ]),
        };
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
    @include('partials.team-settings-heading')

    <x-pages::team-settings.layout :heading="__('Loan rules')" :subheading="__('How long books are lent for and the limits applied to each assisted person.')">
        <form wire:submit="saveSettings" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Loan period') }}</flux:label>
                <flux:description>{{ __('Suggested due date for new loans and how much each renewal adds.') }}</flux:description>
                <flux:input.group class="max-w-48">
                    <flux:input type="number" min="1" max="365" wire:model="loanPeriodDays" required data-test="library-loan-period-days-input" />
                    <flux:input.group.suffix>{{ __('days') }}</flux:input.group.suffix>
                </flux:input.group>
                <flux:error name="loanPeriodDays" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Maximum renewals per loan') }}</flux:label>
                <flux:description>{{ __('Leave blank for no limit.') }}</flux:description>
                <flux:input.group class="max-w-48">
                    <flux:input type="number" min="0" max="99" wire:model="loanMaxRenewals" data-test="library-loan-max-renewals-input" />
                    <flux:input.group.suffix>{{ __('renewals') }}</flux:input.group.suffix>
                </flux:input.group>
                <flux:error name="loanMaxRenewals" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Maximum open loans per assisted person') }}</flux:label>
                <flux:description>{{ __('Leave blank for no limit. Assisted people with an overdue book cannot borrow another one.') }}</flux:description>
                <flux:input.group class="max-w-48">
                    <flux:input type="number" min="1" max="99" wire:model="loanMaxActivePerPerson" data-test="library-loan-max-active-input" />
                    <flux:input.group.suffix>{{ __('books') }}</flux:input.group.suffix>
                </flux:input.group>
                <flux:error name="loanMaxActivePerPerson" />
            </flux:field>

            <flux:callout icon="information-circle" icon:variant="outline" color="blue" data-test="loan-rules-example">
                <flux:callout.text>{{ $this->example }}</flux:callout.text>
            </flux:callout>

            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="library-settings-save-button">{{ __('Save rules') }}</flux:button>
        </form>
    </x-pages::team-settings.layout>
</section>
