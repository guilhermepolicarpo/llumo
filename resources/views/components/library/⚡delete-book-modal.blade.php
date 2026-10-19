<?php

use App\Actions\Library\DeleteBook;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /**
     * Where to go once the book is deleted, for a page that shows the book itself; otherwise the page is told to refresh.
     */
    #[Locked]
    public ?string $redirectTo = null;

    #[Locked]
    public ?int $bookId = null;

    public string $bookTitle = '';

    #[On('confirm-delete-book')]
    public function confirmDeleteBook(int $bookId, string $bookTitle): void
    {
        $this->bookId = $bookId;
        $this->bookTitle = $bookTitle;

        Flux::modal('delete-book')->show();
    }

    public function deleteBook(DeleteBook $deleteBook): void
    {
        $team = Auth::user()->currentTeam;
        $book = $team->books()->findOrFail($this->bookId)->setRelation('team', $team);

        Gate::authorize('delete', $book);

        Flux::modal('delete-book')->close();

        try {
            $deleteBook->handle($book);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Book deleted.'));

        if ($this->redirectTo !== null) {
            $this->redirect($this->redirectTo, navigate: true);

            return;
        }

        $this->dispatch('book-deleted');
    }
}; ?>

<flux:modal name="delete-book" focusable class="max-w-lg">
    <form wire:submit="deleteBook" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete book') }}</flux:heading>
            <flux:subheading>{{ __('Are you sure you want to delete :name and all its copies? Their loans stay in the history.', ['name' => $bookTitle]) }}</flux:subheading>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="book-delete-confirm">{{ __('Delete') }}</flux:button>
        </div>
    </form>
</flux:modal>
