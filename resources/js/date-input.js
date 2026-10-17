/**
 * A form field for a single date: the day is typed as dd/mm/yyyy or picked in the shared month calendar
 * (resources/js/calendar.js), and on touch devices the field opens the system's own date picker instead.
 *
 * The value is a 'Y-m-d' string, or an empty string for no date, bound to Livewire through x-modelable. The typed
 * text only becomes the value once it is a real day within min/max; anything else puts back the last value.
 */
import { isOutOfRange, toDate, toIso } from './calendar';

/** @param {string} iso */
function display(iso) {
    return iso ? iso.split('-').reverse().join('/') : '';
}

/** Turns a dd/mm/yyyy text into a 'Y-m-d' string, or null when it is not a real day. @param {string} text */
function parse(text) {
    const match = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

    if (! match) {
        return null;
    }

    const iso = `${match[3]}-${match[2]}-${match[1]}`;

    return toIso(toDate(iso)) === iso ? iso : null;
}

export default function registerDateInput(Alpine) {
    Alpine.data('dateInput', ({ min = null, max = null }) => ({
        value: '',
        text: '',

        init() {
            this.text = display(this.value);
            this.$watch('value', (value) => (this.text = display(value)));
        },

        commit() {
            if (this.text === '') {
                this.value = '';

                return;
            }

            const iso = parse(this.text);

            if (iso && ! isOutOfRange(iso, min, max)) {
                this.value = iso;
            }

            this.text = display(this.value);
        },
    }));
}
