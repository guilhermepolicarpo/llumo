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
use Livewire\Component;

new class extends Component
{
    use InteractsWithAssistedPersonPicker;

    public ?int $bookCopyId = null;

    public string $bookCopySearch = '';

    public string $loanedOn = '';

    public string $dueOn = '';

    public string $loanNotes = '';

    public function mount(): void
    {
        $team = $this->team();

        Gate::authorize('create', [BookLoan::class, $team]);

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

    <form wire:submit="lendBookCopy" class="max-w-xl space-y-6">
        <x-assisted-person-combobox
            :selected="$this->selectedAssistedPerson"
            :search="$assistedPersonSearch"
            :suggestions="$this->assistedPersonSuggestions"
            test-prefix="loan"
        />

        @if ($this->openLoans->isNotEmpty())
            @php($overdue = $this->openLoans->contains(fn ($loan) => $loan->status === BookLoanStatus::Overdue))
            <flux:callout :icon="$overdue ? 'exclamation-triangle' : 'information-circle'" icon:variant="outline" :color="$overdue ? 'red' : 'zinc'" data-test="loan-open-loans">
                <flux:callout.heading>
                    {{ $overdue ? __('This assisted person has an overdue book') : trans_choice('This assisted person has :count book to return|This assisted person has :count books to return', $this->openLoans->count()) }}
                </flux:callout.heading>
                <flux:callout.text>
                    <ul class="space-y-1">
                        @foreach ($this->openLoans as $loan)
                            <li wire:key="open-loan-{{ $loan->id }}">
                                {{ $loan->copy->book->title }} ({{ $loan->copy->formatted_code }}) — {{ __('due on :date', ['date' => $loan->due_on->format('d/m/Y')]) }}
                            </li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif

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

        <flux:input wire:model="loanNotes" :label="__('Notes')" data-test="loan-notes-input" />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('library.loans.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit" data-test="loan-save-button">
                {{ __('Register loan') }}
            </flux:button>
        </div>
    </form>

    <livewire:appointments.create-assisted-person-modal />
</section>
