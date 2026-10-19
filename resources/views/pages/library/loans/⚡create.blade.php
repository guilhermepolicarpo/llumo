<?php

use App\Actions\Library\LendBookCopy;
use App\Concerns\InteractsWithAssistedPersonPicker;
use App\Enums\BookLoanStatus;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Team;
use App\Rules\BookLoanRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAssistedPersonPicker;

    public ?int $bookCopyId = null;

    public string $bookCopySearch = '';

    public string $loanedOn = '';

    public string $dueOn = '';

    public string $loanNotes = '';

    /**
     * Whether the user may change the loan rules, worked out once to link to them beside the form.
     */
    #[Locked]
    public bool $canEditLoanRules = false;

    public function mount(): void
    {
        $team = $this->team();

        Gate::authorize('create', [BookLoan::class, $team]);

        $this->canEditLoanRules = Gate::allows('update', $team);

        $this->loanedOn = today()->toDateString();
        $this->dueOn = today()->addDays($team->loan_period_days)->toDateString();

        $this->prefillFromQuery($team);
    }

    /**
     * Pre-select the copy and the assisted person given in the link that opened the form, from a book or a person.
     */
    private function prefillFromQuery(Team $team): void
    {
        $bookCopyId = request()->integer('book_copy');
        $assistedPersonId = request()->integer('assisted_person');

        if ($bookCopyId > 0 && $team->bookCopies()->available()->whereKey($bookCopyId)->exists()) {
            $this->bookCopyId = $bookCopyId;
        }

        if ($assistedPersonId > 0 && $team->assistedPeople()->whereKey($assistedPersonId)->exists()) {
            $this->assistedPersonId = $assistedPersonId;
        }
    }

    /**
     * Move the due date along with the loan date, keeping the team's loan period.
     */
    public function updatedLoanedOn(string $value): void
    {
        if (Validator::make(['loanedOn' => $value], ['loanedOn' => ['date_format:Y-m-d']])->passes()) {
            $this->dueOn = Date::createFromFormat('!Y-m-d', $value)->addDays($this->team()->loan_period_days)->toDateString();
        }
    }

    public function selectBookCopy(int $bookCopyId): void
    {
        $this->bookCopyId = $this->team()->bookCopies()->available()->findOrFail($bookCopyId)->id;
        $this->bookCopySearch = '';

        unset($this->selectedBookCopy);
        $this->resetValidation('bookCopyId');
    }

    public function clearBookCopy(): void
    {
        $this->bookCopyId = null;

        unset($this->selectedBookCopy);
    }

    public function lendBookCopy(LendBookCopy $lendBookCopy): void
    {
        $team = $this->team();

        Gate::authorize('create', [BookLoan::class, $team]);

        $validated = $this->validate(BookLoanRules::all($team), [], [
            'assistedPersonId' => __('assisted person'),
            'bookCopyId' => __('copy'),
            'loanedOn' => __('loan date'),
            'dueOn' => __('due date'),
            'loanNotes' => __('notes'),
        ]);

        $lendBookCopy->handle(
            $team->bookCopies()->findOrFail($validated['bookCopyId']),
            $team->assistedPeople()->findOrFail($validated['assistedPersonId']),
            Auth::user(),
            [
                'loaned_on' => $validated['loanedOn'],
                'due_on' => $validated['dueOn'],
                'notes' => $validated['loanNotes'],
            ],
        );

        Flux::toast(variant: 'success', text: __('Loan registered.'));

        $this->redirectRoute('library.loans.index', navigate: true);
    }

    #[Computed]
    public function selectedBookCopy(): ?BookCopy
    {
        return $this->bookCopyId === null ? null : $this->team()->bookCopies()->with('book.authors')->find($this->bookCopyId);
    }

    /**
     * Get the available copies whose code or book title matches the search, or null when there is nothing to search.
     *
     * @return Collection<int, BookCopy>|null
     */
    #[Computed]
    public function bookCopySuggestions(): ?Collection
    {
        $search = trim($this->bookCopySearch);
        $code = BookCopy::parseCode($search);

        if ($code === null && mb_strlen($search) < 2) {
            return null;
        }

        return $this->team()->bookCopies()
            ->available()
            ->with('book')
            ->where(fn (Builder $query) => $query
                ->whereHas('book', fn (Builder $books) => $books->whereLike('title', "%{$search}%"))
                ->when($code, fn (Builder $query, int $code) => $query->orWhere('code', $code)))
            ->orderBy('code')
            ->limit(10)
            ->get();
    }

    /**
     * Get the team's loan rules shown beside the form.
     *
     * @return array{periodDays: int, maxRenewals: ?int, maxOpenLoans: ?int}
     */
    #[Computed]
    public function loanRules(): array
    {
        $team = $this->team();

        return [
            'periodDays' => $team->loan_period_days,
            'maxRenewals' => $team->loan_max_renewals,
            'maxOpenLoans' => $team->loan_max_active_per_person,
        ];
    }

    /**
     * Get the selected assisted person's books still to be returned, to show what they hold before lending.
     *
     * @return Collection<int, BookLoan>
     */
    #[Computed]
    public function openLoans(): Collection
    {
        if ($this->assistedPersonId === null) {
            return new Collection;
        }

        return $this->team()->bookLoans()
            ->open()
            ->where('assisted_person_id', $this->assistedPersonId)
            ->with('copy.book')
            ->orderBy('due_on')
            ->get();
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title(__('New loan'));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('New loan')" :subheading="__('Lend a copy to an assisted person')" :back-href="route('library.loans.index')" :back-label="__('Back to loans')" />

    <div class="flex flex-wrap items-start gap-8">
        <form wire:submit="lendBookCopy" class="min-w-0 max-w-xl flex-1 basis-[28rem] space-y-6">
            <x-assisted-person-combobox
                :selected="$this->selectedAssistedPerson"
                :search="$assistedPersonSearch"
                :suggestions="$this->assistedPersonSuggestions"
                test-prefix="loan"
            />

            <flux:field>
                <flux:label>{{ __('Copy') }}</flux:label>

                @if ($this->selectedBookCopy)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-white/10" data-test="loan-selected-book-copy">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $this->selectedBookCopy->book->title }}</div>
                            <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Copy :code', ['code' => $this->selectedBookCopy->formatted_code]) }}
                                @if ($this->selectedBookCopy->book->authors->isNotEmpty())
                                    · {{ $this->selectedBookCopy->book->authors->pluck('name')->join(', ') }}
                                @endif
                            </div>
                        </div>

                        <flux:button size="sm" variant="ghost" icon="arrows-right-left" icon:variant="outline" wire:click="clearBookCopy" data-test="loan-change-book-copy-button">
                            {{ __('Change') }}
                        </flux:button>
                    </div>
                @else
                    <div class="relative" x-data="{ open: true }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                        <flux:input
                            wire:model.live.debounce.300ms="bookCopySearch"
                            icon="magnifying-glass"
                            :placeholder="__('Type the code on the label or the title...')"
                            autocomplete="off"
                            x-on:focus="open = true"
                            x-on:input="open = true"
                            data-test="loan-book-copy-search-input"
                        />

                        @if ($this->bookCopySuggestions !== null)
                            <div x-show="open" class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-800" data-test="loan-book-copy-suggestions">
                                @forelse ($this->bookCopySuggestions as $suggestion)
                                    <button
                                        type="button"
                                        wire:key="book-copy-suggestion-{{ $suggestion->id }}"
                                        wire:click="selectBookCopy({{ $suggestion->id }})"
                                        class="flex w-full items-center gap-3 px-3 py-2 text-start hover:bg-zinc-50 not-last:border-b not-last:border-zinc-100 dark:hover:bg-zinc-700/50 dark:not-last:border-white/5"
                                        data-test="loan-book-copy-suggestion"
                                    >
                                        <span class="font-mono text-sm text-zinc-500 dark:text-zinc-400">{{ $suggestion->formatted_code }}</span>
                                        <span class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $suggestion->book->title }}</span>
                                    </button>
                                @empty
                                    <flux:text class="px-3 py-3">{{ __('No available copy matches your search.') }}</flux:text>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @endif

                <flux:error name="bookCopyId" />
            </flux:field>

            <div class="grid gap-6 sm:grid-cols-2">
                <x-date-input wire:model.live="loanedOn" :label="__('Loan date')" :max="today()->toDateString()" required data-test="loan-loaned-on-input" />
                <x-date-input wire:model="dueOn" :label="__('Due date')" :min="$loanedOn" required data-test="loan-due-on-input" />
            </div>

            <flux:textarea wire:model="loanNotes" :label="__('Notes')" :badge="__('Optional')" rows="auto" data-test="loan-notes-input" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('library.loans.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" type="submit" data-test="loan-save-button">
                    {{ __('Register loan') }}
                </flux:button>
            </div>
        </form>

        <aside class="min-w-0 max-w-md flex-1 basis-80 space-y-4" aria-label="{{ __('Loan context') }}">
            @if ($this->selectedAssistedPerson)
                @php($hasOverdue = $this->openLoans->contains(fn ($loan) => $loan->status === BookLoanStatus::Overdue))

                <flux:card class="space-y-3" data-test="loan-open-loans">
                    <div class="flex items-baseline justify-between gap-2">
                        <flux:heading>{{ __('With :name now', ['name' => $this->selectedAssistedPerson->name]) }}</flux:heading>
                        @if ($this->loanRules['maxOpenLoans'] !== null)
                            <flux:text class="shrink-0 text-sm">{{ __(':count of :max open', ['count' => $this->openLoans->count(), 'max' => $this->loanRules['maxOpenLoans']]) }}</flux:text>
                        @endif
                    </div>

                    @if ($this->loanRules['maxOpenLoans'] !== null)
                        <flux:progress :value="min(100, $this->openLoans->count() / $this->loanRules['maxOpenLoans'] * 100)" :color="$hasOverdue || $this->openLoans->count() >= $this->loanRules['maxOpenLoans'] ? 'red' : null" />
                    @endif

                    @if ($this->openLoans->isEmpty())
                        <flux:text>{{ __('No books to return.') }}</flux:text>
                    @else
                        @if ($hasOverdue)
                            <flux:callout icon="exclamation-triangle" icon:variant="outline" color="red" :heading="__('This assisted person has an overdue book')" />
                        @endif

                        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($this->openLoans as $loan)
                                <li wire:key="open-loan-{{ $loan->id }}" class="flex items-center justify-between gap-3 py-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="shrink-0 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $loan->copy->formatted_code }}</span>
                                        <span class="truncate text-sm text-zinc-900 dark:text-white">{{ $loan->copy->book->title }}</span>
                                    </div>
                                    <span class="shrink-0 text-xs {{ $loan->status === BookLoanStatus::Overdue ? 'font-medium text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                        {{ __('Due on :date', ['date' => $loan->due_on->format('d/m/Y')]) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </flux:card>
            @else
                <div class="rounded-lg border border-dashed border-zinc-300 p-5 text-center dark:border-zinc-600" data-test="loan-open-loans-empty">
                    <flux:text>{{ __('Choose an assisted person to see the books they still have to return.') }}</flux:text>
                </div>
            @endif

            <flux:card class="space-y-3 bg-zinc-50 dark:bg-white/5" data-test="loan-rules">
                <div class="flex items-baseline justify-between gap-2">
                    <flux:heading>{{ __('Loan rules') }}</flux:heading>
                    @if ($canEditLoanRules)
                        <flux:link :href="route('team-settings.loan-rules')" wire:navigate class="text-sm">{{ __('Edit') }}</flux:link>
                    @endif
                </div>

                <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-sm">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Loan period') }}</dt>
                    <dd class="text-end text-zinc-900 dark:text-white">{{ trans_choice(':count day|:count days', $this->loanRules['periodDays']) }}</dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Renewals') }}</dt>
                    <dd class="text-end text-zinc-900 dark:text-white">{{ $this->loanRules['maxRenewals'] === null ? __('No limit') : __('Up to :count', ['count' => $this->loanRules['maxRenewals']]) }}</dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Open per assisted person') }}</dt>
                    <dd class="text-end text-zinc-900 dark:text-white">{{ $this->loanRules['maxOpenLoans'] === null ? __('No limit') : __('Up to :count', ['count' => $this->loanRules['maxOpenLoans']]) }}</dd>
                </dl>

                <flux:text class="text-sm">{{ __('Assisted people with an overdue book cannot borrow another one.') }}</flux:text>
            </flux:card>
        </aside>
    </div>

    <livewire:appointments.create-assisted-person-modal />
</section>
