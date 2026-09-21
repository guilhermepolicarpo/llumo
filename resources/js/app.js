import registerCatalogPicker from './catalog-picker';

document.addEventListener('alpine:init', () => registerCatalogPicker(window.Alpine));

// Livewire skips the navigate view transition while a modal dialog is open, so close any open Flux modal before the page swaps.
document.addEventListener('livewire:navigate', () => window.Flux?.modals().close());
