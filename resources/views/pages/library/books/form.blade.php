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

<div class="flex flex-wrap items-start gap-8">
    <aside class="flex w-52 shrink-0 flex-col gap-6">
        <flux:field>
            <flux:label>{{ __('Cover') }}</flux:label>

            {{-- A drop zone: a file dropped on it, or picked with the button, is uploaded to the "cover" property. --}}
            <div
                x-data="{ dragging: false }"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false; $event.dataTransfer.files.length && $wire.upload('cover', $event.dataTransfer.files[0])"
                class="space-y-2"
            >
                <input x-ref="coverInput" type="file" wire:model="cover" accept="image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" data-test="book-cover-input" />

                <div
                    x-bind:class="dragging && 'border-(--color-accent)! bg-blue-50 dark:bg-blue-500/10'"
                    class="relative flex aspect-2/3 w-full flex-col items-center justify-center gap-3 overflow-hidden rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 text-center transition-colors dark:border-white/20 dark:bg-white/5"
                    data-test="book-cover-preview"
                >
                    @if ($coverPreviewUrl)
                        <img src="{{ $coverPreviewUrl }}" alt="{{ __('Cover') }}" class="absolute inset-0 size-full object-cover" />
                    @else
                        <flux:icon name="photo" class="size-7 text-zinc-400 dark:text-zinc-500" />
                        <flux:text class="text-sm">{{ __('Drag the image here') }}</flux:text>
                        <flux:button size="sm" x-on:click="$refs.coverInput.click()" data-test="book-cover-choose-button">{{ __('Choose image') }}</flux:button>
                        <flux:text class="text-xs">{{ __('PNG, JPG or WEBP up to 2 MB.') }}</flux:text>
                    @endif

                    <div wire:loading.flex wire:target="cover" class="absolute inset-0 items-center justify-center bg-white/80 dark:bg-zinc-900/80">
                        <flux:text>{{ __('Uploading...') }}</flux:text>
                    </div>
                </div>

                @if ($coverPreviewUrl)
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" icon="photo" icon:variant="outline" x-on:click="$refs.coverInput.click()" data-test="book-cover-choose-button">{{ __('Change image') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="trash" icon:variant="outline" wire:click="removeCoverImage" :aria-label="__('Remove cover')" :tooltip="__('Remove cover')" data-test="book-cover-remove-button" />
                    </div>
                @endif
            </div>

            <flux:error name="cover" />
        </flux:field>

        @if ($withCopies)
            <flux:field>
                <flux:label>{{ __('Copies') }}</flux:label>
                <flux:input type="number" min="1" :max="BookRules::MAX_COPIES_AT_ONCE" wire:model="copiesCount" required class="max-w-24" data-test="book-copies-count-input" />
                <flux:description>{{ __('Each one gets the next code of the label.') }}</flux:description>
                <flux:error name="copiesCount" />
            </flux:field>
        @endif
    </aside>

    <div class="min-w-0 flex-1 basis-[36rem] space-y-6">
        <flux:field>
            <flux:label>{{ __('ISBN') }}</flux:label>
            <flux:description>{{ __('Type the ISBN printed on the back cover to fill in the book from the public catalogs.') }}</flux:description>

            <flux:input.group class="max-w-md">
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

        <flux:separator variant="subtle" />

        <div class="grid gap-x-6 gap-y-5 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <flux:input wire:model="title" :label="__('Title')" required :placeholder="__('E.g. Nosso Lar')" data-test="book-title-input" />
            </div>

            <div class="sm:col-span-2">
                <flux:input wire:model="subtitle" :label="__('Subtitle')" data-test="book-subtitle-input" />
            </div>

            <flux:field class="sm:col-span-2">
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

            <flux:field class="sm:col-span-2">
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
                <div class="sm:col-span-4">
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
                </div>
            @endif

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

            <flux:input wire:model="edition" :label="__('Edition')" :placeholder="__('E.g. 2nd')" data-test="book-edition-input" />
            <flux:input type="number" min="1000" wire:model="publishedYear" :label="__('Year')" data-test="book-published-year-input" />
            <flux:input type="number" min="1" wire:model="pageCount" :label="__('Pages')" data-test="book-page-count-input" />

            <div class="sm:col-span-4">
                <flux:textarea wire:model="synopsis" :label="__('Synopsis')" rows="auto" data-test="book-synopsis-input" />
            </div>
        </div>
    </div>
</div>
