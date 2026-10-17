import registerCalendar from './calendar';
import registerCatalogPicker from './catalog-picker';
import registerDateInput from './date-input';
import registerDayPicker from './day-picker';

document.addEventListener('alpine:init', () => {
    registerCalendar(window.Alpine);
    registerCatalogPicker(window.Alpine);
    registerDateInput(window.Alpine);
    registerDayPicker(window.Alpine);
});

// Livewire skips the navigate view transition while a modal dialog is open, so close any open Flux modal before the page swaps.
document.addEventListener('livewire:navigate', () => window.Flux?.modals().close());
