<?php

use App\Actions\Library\RenewBookLoan;
use App\Actions\Library\ReturnBookLoan;
use App\Concerns\InteractsWithTableSorting;
use App\Enums\BookLoanStatus;
use App\Models\AssistedPerson;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Team;
use App\Rules\BookLoanRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTableSorting;
    use WithPagination;

    /**
     * @var list<int>
     */
    public const array PER_PAGE_OPTIONS = [5, 10, 25, 50, 100];

    /**
     * The columns the table can be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE_COLUMNS = ['person', 'loaned_on', 'due_on'];

    /**
     * The status shown, or an empty string for every loan still to be returned, overdue or not.
     */
    #[Session('library-loans-status')]
    public string $status = '';

    public string $search = '';

    #[Session('library-loans-per-page')]
    public int $perPage = 5;

    #[Locked]
    public ?int $returningLoanId = null;

    #[Locked]
    public ?int $renewingLoanId = null;

    public string $returnedOn = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', [BookLoan::class, $this->team()]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function confirmReturn(int $loanId): void
    {
        $loan = $this->findLoan($loanId);

        Gate::authorize('return', $loan);

        $this->returningLoanId = $loan->id;
        $this->returnedOn = today()->toDateString();
        $this->resetValidation();

        Flux::modal('return-book-loan')->show();
    }

    public function returnLoan(ReturnBookLoan $returnBookLoan): void
    {
        $loan = $this->findLoan($this->returningLoanId);

        Gate::authorize('return', $loan);

        $validated = $this->validate(['returnedOn' => BookLoanRules::returnedOn()], [], ['returnedOn' => __('return date')]);

        $returnBookLoan->handle($loan, Auth::user(), $validated['returnedOn']);

        $this->reset('returningLoanId', 'returnedOn');

        Flux::modal('return-book-loan')->close();

        Flux::toast(variant: 'success', text: __('Return registered.'));
    }

    public function confirmRenew(int $loanId): void
    {
        $loan = $this->findLoan($loanId);

        Gate::authorize('renew', $loan);

        $this->renewingLoanId = $loan->id;

        Flux::modal('renew-book-loan')->show();
    }

    public function renewLoan(RenewBookLoan $renewBookLoan, int $loanId): void
    {
        $loan = $this->findLoan($loanId);

        Gate::authorize('renew', $loan);

        try {
            $loan = $renewBookLoan->handle($loan);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        } finally {
            $this->reset('renewingLoanId');
            Flux::modal('renew-book-loan')->close();
        }

        Flux::toast(variant: 'success', text: __('Loan renewed until :date.', ['date' => $loan->due_on->format('d/m/Y')]));
    }

    /**
     * @return LengthAwarePaginator<int, BookLoan>
     */
    #[Computed]
    public function loans(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $code = BookCopy::parseCode($search);
        $status = BookLoanStatus::tryFrom($this->status);

        return $this->team()->bookLoans()
            ->with(['assistedPerson', 'copy.book'])
            ->tap(fn (Builder $query) => match ($status) {
                BookLoanStatus::Overdue => $query->overdue(),
                BookLoanStatus::Returned => $query->returned(),
                default => $query->open(),
            })
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereHas('assistedPerson', fn (Builder $people) => $people->withTrashed()->whereLike('name', "%{$search}%"))
                ->orWhereHas('copy', fn (Builder $copies) => $copies->withTrashed()
                    ->whereHas('book', fn (Builder $books) => $books->withTrashed()->whereLike('title', "%{$search}%"))
                    ->when($code, fn (Builder $copies, int $code) => $copies->orWhere('code', $code)))))
            ->when($this->sortBy, fn (Builder $query, string $column) => match ($column) {
                'person' => $query->orderBy(
                    AssistedPerson::withTrashed()->select('name')->whereColumn('assisted_people.id', 'book_loans.assisted_person_id'),
                    $this->sortDirection,
                ),
                'loaned_on' => $query->orderBy('loaned_on', $this->sortDirection),
                'due_on' => $query->orderBy('due_on', $this->sortDirection),
            })
            ->when($status === BookLoanStatus::Returned, fn (Builder $query) => $query->orderByDesc('returned_on'), fn (Builder $query) => $query->orderBy('due_on'))
            ->orderByDesc('id')
            ->paginate(in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_OPTIONS[0]);
    }

    /**
     * Count the loans to be returned and the overdue ones, shown next to the status filter.
     *
     * @return array{open: int, overdue: int}
     */
    #[Computed]
    public function counts(): array
    {
        $counts = $this->team()->bookLoans()->open()
            ->selectRaw('count(*) as open, sum(case when due_on < ? then 1 else 0 end) as overdue', [today()->toDateString()])
            ->toBase()
            ->first();

        return ['open' => (int) $counts->open, 'overdue' => (int) $counts->overdue];
    }

    #[Computed]
    public function returningLoan(): ?BookLoan
    {
        return $this->returningLoanId === null ? null : $this->team()->bookLoans()->with(['assistedPerson', 'copy.book'])->find($this->returningLoanId);
    }

    #[Computed]
    public function renewingLoan(): ?BookLoan
    {
        return $this->renewingLoanId === null ? null : $this->team()->bookLoans()->with(['assistedPerson', 'copy.book'])->find($this->renewingLoanId);
    }

    /**
     * Find one of the team's loans, with the team already set for the policy checks.
     */
    private function findLoan(?int $loanId): BookLoan
    {
        $team = $this->team();

        return $team->bookLoans()->findOrFail($loanId)->setRelation('team', $team);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title(__('Loans'));
    }
}; ?>

@use('App\Enums\BookLoanStatus')

<section class="w-full">
    <x-page-header :heading="__('Loans')" :subheading="__('Books lent to the assisted people')" :separator="false">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by person, title or code...')"
                clearable
                class="max-sm:flex-1 sm:w-80"
                data-test="loans-search-input"
            />

            <flux:button variant="primary" icon="plus" :href="route('library.loans.create')" wire:navigate data-test="loans-new-button">
                {{ __('New loan') }}
            </flux:button>
        </div>
    </x-page-header>

    <flux:radio.group wire:model.live="status" variant="segmented" class="mb-4 w-full sm:w-fit" data-test="loans-status-filter">
        <flux:radio value="" :label="__('To return (:count)', ['count' => $this->counts['open']])" />
        <flux:radio :value="BookLoanStatus::Overdue->value" :label="__('Overdue (:count)', ['count' => $this->counts['overdue']])" />
        <flux:radio :value="BookLoanStatus::Returned->value" :label="__('Returned')" />
    </flux:radio.group>

    <flux:card class="scroll-mt-6 px-4 pb-4 [--flux-bleed:1rem]" id="loans-table">
        @if ($this->loans->isNotEmpty())
            <flux:table bleed>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'person'" :direction="$sortDirection" wire:click="sort('person')" data-test="loans-sort-person">{{ __('Assisted person') }}</flux:table.column>
                    <flux:table.column>{{ __('Book') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'loaned_on'" :direction="$sortDirection" wire:click="sort('loaned_on')" data-test="loans-sort-loaned-on">{{ __('Lent on') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'due_on'" :direction="$sortDirection" wire:click="sort('due_on')" data-test="loans-sort-due-on">{{ __('Due on') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->loans as $loan)
                        <flux:table.row :key="$loan->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800" data-test="loan-row">
                            <flux:table.cell class="font-medium text-zinc-800 dark:text-white">
                                @if ($loan->assistedPerson->trashed())
                                    {{ $loan->assistedPerson->name }}
                                @else
                                    <flux:link :href="route('assisted-people.show', ['assistedPerson' => $loan->assistedPerson])" wire:navigate variant="ghost" class="text-zinc-800 dark:text-white">{{ $loan->assistedPerson->name }}</flux:link>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="max-w-80">
                                <div class="min-w-0">
                                    @if ($loan->copy->book->trashed())
                                        <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $loan->copy->book->title }}</div>
                                    @else
                                        <flux:link :href="route('library.books.show', ['book' => $loan->copy->book])" wire:navigate variant="ghost" class="block truncate font-medium text-zinc-800 dark:text-white">{{ $loan->copy->book->title }}</flux:link>
                                    @endif
                                    <div>{{ $loan->copy->formatted_code }}</div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="text-zinc-800 dark:text-white">{{ $loan->loaned_on->format('d/m/Y') }}</flux:table.cell>

                            <flux:table.cell>
                                <div class="text-zinc-800 dark:text-white">{{ $loan->due_on->format('d/m/Y') }}</div>
                                <div @class([
                                    'text-red-600 dark:text-red-400' => $loan->status === BookLoanStatus::Overdue,
                                    'text-amber-700 dark:text-amber-400' => $loan->isDueSoon(),
                                ]) data-test="loan-due-hint">
                                    {{ $loan->dueHint() }}
                                    @if (! $loan->returned_on && $loan->renewals_count > 0)
                                        <span>· {{ trans_choice('Renewed :count time|Renewed :count times', $loan->renewals_count) }}</span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                @if (! $loan->returned_on)
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" icon="arrow-path" icon:variant="outline" :tooltip="__('Renew')" :aria-label="__('Renew')" wire:click="confirmRenew({{ $loan->id }})" data-test="loan-renew-button" />
                                        <flux:button size="sm" icon="arrow-uturn-left" icon:variant="outline" wire:click="confirmReturn({{ $loan->id }})" data-test="loan-return-button">
                                            {{ __('Return book') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="@container -mx-4 flex flex-wrap items-center justify-center gap-3 border-t border-zinc-800/10 px-4 pt-3 dark:border-white/20">
                <flux:pagination :paginator="$this->loans" scroll-to="#loans-table" class="contents! @container-normal! *:order-2 [&>:first-child]:order-none [&>:first-child]:font-normal @max-[40rem]:[&>:first-child]:w-full @max-[40rem]:[&>:first-child]:text-center" />

                <div class="order-1 flex items-center gap-2 @[40rem]:ms-auto">
                    <flux:text class="whitespace-nowrap text-xs">{{ __('Per page') }}</flux:text>
                    <flux:select wire:model.live="perPage" size="xs" data-test="loans-per-page-select">
                        @foreach ($this::PER_PAGE_OPTIONS as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        @else
            <flux:text class="pt-8 pb-4 text-center text-zinc-500 dark:text-zinc-400">
                @if ($search !== '')
                    {{ __('No loans match your search.') }}
                @else
                    {{ __('No loans to show.') }}
                @endif
            </flux:text>
        @endif
    </flux:card>

    <flux:modal name="renew-book-loan" class="md:w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Renew loan') }}</flux:heading>
                @if ($this->renewingLoan)
                    <flux:subheading>
                        {{ __(':title (:code), lent to :name', ['title' => $this->renewingLoan->copy->book->title, 'code' => $this->renewingLoan->copy->formatted_code, 'name' => $this->renewingLoan->assistedPerson->name]) }}
                    </flux:subheading>
                @endif
            </div>

            @if ($this->renewingLoan)
                <flux:text data-test="loan-renew-new-due-on">
                    {{ __('The book will be due on :date.', ['date' => $this->renewingLoan->dueOnAfterRenewal(auth()->user()->currentTeam->loan_period_days)->format('d/m/Y')]) }}
                </flux:text>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($this->renewingLoan)
                    <flux:button variant="primary" wire:click="renewLoan({{ $this->renewingLoan->id }})" wire:loading.attr="disabled" data-test="loan-renew-confirm">{{ __('Renew') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    <flux:modal name="return-book-loan" class="md:w-md">
        <form wire:submit="returnLoan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Register return') }}</flux:heading>
                @if ($this->returningLoan)
                    <flux:subheading>
                        {{ __(':title (:code), lent to :name', ['title' => $this->returningLoan->copy->book->title, 'code' => $this->returningLoan->copy->formatted_code, 'name' => $this->returningLoan->assistedPerson->name]) }}
                    </flux:subheading>
                @endif
            </div>

            <x-date-input wire:model="returnedOn" :label="__('Return date')" :max="today()->toDateString()" required data-test="loan-returned-on-input" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="loan-return-confirm">{{ __('Register return') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
