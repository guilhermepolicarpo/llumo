@use('App\Enums\AuthorRole')
@use('App\Enums\LibraryCatalog')
@use('App\Rules\BookRules')

{{-- The book's fields, bound to the InteractsWithBookForm properties of the create and edit pages. --}}
@props([
    'selectedEntries',
    'incarnateAuthorIds',
    'spiritualAuthorIds',
    'publisherId',
    'categoryId',
    'collectionId',
    'suggestedAuthorNames',
    'suggestedPublisherName',
    'coverPreviewUrl',
    'withCopies' => false,
])

<div class="space-y-6">
    <flux:field>
        <flux:label>{{ __('ISBN') }}</flux:label>
        <flux:description>{{ __('Type the ISBN printed on the back cover to fill in the book from the public catalogs.') }}</flux:description>

        <flux:input.group>
            <flux:input
                wire:model="isbn"
                wire:keydown.enter.prevent="lookUpIsbn"
                placeholder="978-85-7328-944-2"
                inputmode="numeric"
                autofocus
                data-test="book-isbn-input"
            />
            <flux:button icon="magnifying-glass" icon:variant="outline" wire:click="lookUpIsbn" wire:target="lookUpIsbn" data-test="book-isbn-lookup-button">
                {{ __('Look up') }}
            </flux:button>
        </flux:input.group>

        <flux:error name="isbn" />
    </flux:field>

    <div class="grid gap-6 md:grid-cols-[1fr_12rem]">
        <flux:fieldset class="min-w-0">
            <flux:input wire:model="title" :label="__('Title')" required :placeholder="__('E.g. Nosso Lar')" data-test="book-title-input" />
            <flux:input wire:model="subtitle" :label="__('Subtitle')" data-test="book-subtitle-input" />

            <flux:field>
                <flux:label>{{ AuthorRole::Incarnate->pluralLabel() }}</flux:label>
                <flux:description>{{ __('Who wrote the book, or the medium who psychographed it.') }}</flux:description>
                <x-catalog-picker
                    :catalog="LibraryCatalog::Author"
                    model="incarnateAuthorIds"
                    :options="$selectedEntries[LibraryCatalog::Author->value]"
                    :selected="$incarnateAuthorIds"
                    multiple
                    index="incarnate"
                    test-id="book-incarnate-authors"
                />
                <flux:error name="incarnateAuthorIds.*" />
            </flux:field>

            <flux:field>
                <flux:label>{{ AuthorRole::Spiritual->pluralLabel() }}</flux:label>
                <flux:description>{{ __('The spirit who dictated the book, when it was psychographed.') }}</flux:description>
                <x-catalog-picker
                    :catalog="LibraryCatalog::Author"
                    model="spiritualAuthorIds"
                    :options="$selectedEntries[LibraryCatalog::Author->value]"
                    :selected="$spiritualAuthorIds"
                    multiple
                    index="spiritual"
                    test-id="book-spiritual-authors"
                />
                <flux:error name="spiritualAuthorIds.*" />
            </flux:field>

            @if ($suggestedAuthorNames !== [])
                <flux:callout icon="light-bulb" icon:variant="outline" color="blue" data-test="book-suggested-authors">
                    <flux:callout.heading>{{ __('Authors found in the catalogs') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('They are not in the library yet. Tell which role each one had in this book.') }}</flux:callout.text>

                    <x-slot name="actions">
                        <div class="w-full space-y-2">
                            @foreach ($suggestedAuthorNames as $index => $name)
                                <div class="flex flex-wrap items-center gap-2" wire:key="suggested-author-{{ $index }}-{{ $name }}">
                                    <span class="me-auto font-medium text-zinc-800 dark:text-white">{{ $name }}</span>
                                    <flux:button size="xs" wire:click="addSuggestedAuthor({{ $index }}, 'incarnate')" data-test="book-suggested-author-incarnate">{{ __('Incarnate') }}</flux:button>
                                    <flux:button size="xs" wire:click="addSuggestedAuthor({{ $index }}, 'spiritual')" data-test="book-suggested-author-spiritual">{{ __('Spiritual') }}</flux:button>
                                    <flux:button size="xs" variant="ghost" icon="x-mark" icon:variant="outline" wire:click="dismissSuggestedAuthor({{ $index }})" :aria-label="__('Ignore :name', ['name' => $name])" data-test="book-suggested-author-dismiss" />
                                </div>
                            @endforeach
                        </div>
                    </x-slot>
                </flux:callout>
            @endif

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Publisher') }}</flux:label>
                    <x-catalog-picker
                        :catalog="LibraryCatalog::Publisher"
                        model="publisherId"
                        :options="$selectedEntries[LibraryCatalog::Publisher->value]"
                        :selected="$publisherId"
                        test-id="book-publisher"
                    />
                    <flux:error name="publisherId" />

                    @if ($suggestedPublisherName !== null)
                        <div class="mt-2 flex flex-wrap items-center gap-2" data-test="book-suggested-publisher">
                            <flux:text class="text-sm">{{ __('Found: :name', ['name' => $suggestedPublisherName]) }}</flux:text>
                            <flux:button size="xs" icon="plus" icon:variant="outline" wire:click="addSuggestedPublisher" data-test="book-suggested-publisher-add">{{ __('Add') }}</flux:button>
                            <flux:button size="xs" variant="ghost" icon="x-mark" icon:variant="outline" wire:click="dismissSuggestedPublisher" :aria-label="__('Ignore :name', ['name' => $suggestedPublisherName])" data-test="book-suggested-publisher-dismiss" />
                        </div>
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Category') }}</flux:label>
                    <x-catalog-picker
                        :catalog="LibraryCatalog::Category"
                        model="categoryId"
                        :options="$selectedEntries[LibraryCatalog::Category->value]"
                        :selected="$categoryId"
                        test-id="book-category"
                    />
                    <flux:error name="categoryId" />
                </flux:field>
            </div>

            <div class="grid gap-6 sm:grid-cols-[1fr_8rem]">
                <flux:field>
                    <flux:label>{{ __('Collection') }}</flux:label>
                    <x-catalog-picker
                        :catalog="LibraryCatalog::Collection"
                        model="collectionId"
                        :options="$selectedEntries[LibraryCatalog::Collection->value]"
                        :selected="$collectionId"
                        test-id="book-collection"
                    />
                    <flux:error name="collectionId" />
                </flux:field>

                <flux:input type="number" min="1" wire:model="collectionVolume" :label="__('Volume')" data-test="book-collection-volume-input" />
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <flux:input wire:model="edition" :label="__('Edition')" :placeholder="__('E.g. 2nd')" data-test="book-edition-input" />
                <flux:input type="number" min="1000" wire:model="publishedYear" :label="__('Year')" data-test="book-published-year-input" />
                <flux:input type="number" min="1" wire:model="pageCount" :label="__('Pages')" data-test="book-page-count-input" />
            </div>

            <flux:textarea wire:model="synopsis" :label="__('Synopsis')" rows="4" data-test="book-synopsis-input" />

            @if ($withCopies)
                <flux:input
                    type="number"
                    min="1"
                    :max="BookRules::MAX_COPIES_AT_ONCE"
                    wire:model="copiesCount"
                    :label="__('Number of copies')"
                    :description="__('Each copy gets the next code of the library, to be written on its label.')"
                    required
                    class="sm:max-w-48"
                    data-test="book-copies-count-input"
                />
            @endif
        </flux:fieldset>

        <flux:field>
            <flux:label>{{ __('Cover') }}</flux:label>

            <div class="space-y-3">
                <div class="flex aspect-2/3 w-full max-w-48 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5" data-test="book-cover-preview">
                    @if ($coverPreviewUrl)
                        <img src="{{ $coverPreviewUrl }}" alt="{{ __('Cover') }}" class="size-full object-cover" />
                    @else
                        <flux:icon name="book-open" class="size-10 text-zinc-300 dark:text-zinc-600" />
                    @endif
                </div>

                <flux:input type="file" wire:model="cover" accept="image/png,image/jpeg,image/webp" size="sm" data-test="book-cover-input" />
                <flux:text wire:loading wire:target="cover">{{ __('Uploading...') }}</flux:text>

                @if ($coverPreviewUrl)
                    <flux:button size="sm" variant="ghost" icon="trash" icon:variant="outline" wire:click="removeCoverImage" data-test="book-cover-remove-button">
                        {{ __('Remove cover') }}
                    </flux:button>
                @endif
            </div>

            <flux:error name="cover" />
        </flux:field>
    </div>
</div>
