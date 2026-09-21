/**
 * Searchable, creatable picker for the team catalogs used on the appointment record.
 *
 * The options are not rendered by Livewire: every catalog of the component is loaded in one background
 * request as soon as the first picker starts, shared by every picker of the same catalog, filtered here,
 * and only the first matches are drawn.
 *
 * Choosing an entry only changes the component state locally ($set without a request); the selection
 * reaches the server with the next request, usually the save. That is why the selected entries, and the
 * optional per-entry detail, are drawn here from the local state instead of by Livewire.
 */
const VISIBLE_LIMIT = 50;

/** @type {Map<string, { options: Array<{ id: string, name: string, searchKey: string }>, loaded: boolean, loading: boolean }>} */
const caches = new Map();

/** Loads in flight, one per Livewire component (they bring every catalog at once). @type {Map<string, Promise<void>>} */
const pendingLoads = new Map();

/**
 * Lower-case the value and strip its accents, so "coracao" finds "Coração".
 */
const normalize = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .trim()
    .toLocaleLowerCase();

const toOption = ({ id, name }) => ({ id: String(id), name, searchKey: normalize(name) });

const byName = (first, second) => first.name.localeCompare(second.name, 'pt-BR', { sensitivity: 'base' });

const cacheFor = (Alpine, componentId, key) => {
    const cacheId = `${componentId}:${key}`;

    if (! caches.has(cacheId)) {
        caches.set(cacheId, Alpine.reactive({ options: [], loaded: false, loading: false }));
    }

    return caches.get(cacheId);
};

const cachesOf = (componentId) => [...caches.entries()]
    .filter(([cacheId]) => cacheId.startsWith(`${componentId}:`))
    .map(([, cache]) => cache);

/**
 * Forget the catalogs of components that are gone, e.g. pages left through wire:navigate.
 */
const forgetDestroyedComponents = () => {
    [...caches.keys()]
        .filter((cacheId) => ! window.Livewire.find(cacheId.split(':')[0]))
        .forEach((cacheId) => caches.delete(cacheId));
};

export default function registerCatalogPicker(Alpine) {
    Alpine.data('catalogPicker', ({ model, multiple = false, cacheKey, create, detailModel = null }) => ({
        open: false,
        search: '',
        creating: false,
        highlighted: 0,

        get cache() {
            return cacheFor(Alpine, this.$wire.$id, cacheKey);
        },

        init() {
            this.ensureLoaded();

            this.$watch('search', () => {
                const exactIndex = this.visible.findIndex((option) => option.searchKey === normalize(this.search));

                this.highlighted = exactIndex >= 0 ? exactIndex : 0;
            });
        },

        get matches() {
            const term = normalize(this.search);

            return term === '' ? this.cache.options : this.cache.options.filter((option) => option.searchKey.includes(term));
        },

        get visible() {
            return this.matches.slice(0, VISIBLE_LIMIT);
        },

        get hiddenCount() {
            return this.matches.length - this.visible.length;
        },

        get canCreate() {
            const term = normalize(this.search);

            return this.cache.loaded && term !== '' && ! this.cache.options.some((option) => option.searchKey === term);
        },

        /**
         * Load every catalog of this component once; later calls share the request in flight.
         */
        ensureLoaded() {
            if (this.cache.loaded) {
                return;
            }

            const componentId = this.$wire.$id;

            this.cache.loading = true;

            if (! pendingLoads.has(componentId)) {
                forgetDestroyedComponents();

                pendingLoads.set(componentId, this.$wire.pickerOptions()
                    .then((optionsByKey) => {
                        Object.entries(optionsByKey).forEach(([key, options]) => {
                            const cache = cacheFor(Alpine, componentId, key);

                            cache.options = options.map(toOption).sort(byName);
                            cache.loaded = true;
                        });
                    })
                    .catch(() => {})
                    .finally(() => {
                        cachesOf(componentId).forEach((cache) => cache.loading = false);
                        pendingLoads.delete(componentId);
                    }));
            }
        },

        toggle() {
            if (this.open) {
                this.close();

                return;
            }

            this.open = true;
            this.highlighted = 0;
            this.ensureLoaded();
            this.$nextTick(() => this.$refs.search?.focus());
        },

        close() {
            this.open = false;
            this.search = '';
        },

        selectedIds() {
            const value = this.$wire.$get(model);

            return (multiple ? (value ?? []) : [value])
                .filter((id) => id !== null && id !== undefined && id !== '')
                .map(String);
        },

        isSelected(id) {
            return this.selectedIds().includes(id);
        },

        /**
         * Name a selected entry, loading the catalog when the entry was selected by the server under a
         * name this picker has not seen (e.g. after a pass row above it is removed).
         */
        nameOf(id) {
            const name = JSON.parse(this.$root.dataset.selectedOptions || '{}')[id]
                ?? this.cache.options.find((option) => option.id === id)?.name;

            if (name === undefined && ! this.cache.loaded) {
                this.ensureLoaded();
            }

            return name ?? '…';
        },

        detailOf(id) {
            return this.$wire.$get(`${detailModel}.${id}`) ?? '';
        },

        setDetail(id, value) {
            this.$wire.$set(`${detailModel}.${id}`, value, false);
            this.$dispatch('catalog-picker-change');
        },

        choose(id) {
            if (multiple) {
                const selected = this.selectedIds();

                this.$wire.$set(model, selected.includes(id) ? selected.filter((value) => value !== id) : [...selected, id], false);
                this.$dispatch('catalog-picker-change');

                return;
            }

            this.$wire.$set(model, id, false);
            this.$dispatch('catalog-picker-change');
            this.close();
        },

        move(step) {
            const count = this.visible.length + (this.canCreate ? 1 : 0);

            if (count === 0) {
                return;
            }

            this.highlighted = (this.highlighted + step + count) % count;
            this.$nextTick(() => this.$refs.options?.querySelector('[data-highlighted="true"]')?.scrollIntoView({ block: 'nearest' }));
        },

        confirm() {
            const option = this.visible[this.highlighted];

            if (option) {
                this.choose(option.id);

                return;
            }

            this.create();
        },

        create() {
            const name = this.search.trim();

            if (this.creating || ! this.canCreate) {
                return;
            }

            this.creating = true;

            create(name)
                .then((entry) => {
                    if (! entry?.id) {
                        return;
                    }

                    if (! this.cache.options.some((option) => option.id === String(entry.id))) {
                        this.cache.options = [...this.cache.options, toOption(entry)].sort(byName);
                    }

                    this.search = '';

                    if (! multiple) {
                        this.open = false;
                    }
                })
                .catch(() => {})
                .finally(() => this.creating = false);
        },
    }));
}
