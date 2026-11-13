/**
 * A form field for a single date, or a date and time: the day is typed as dd/mm/yyyy (with the time, dd/mm/yyyy hh:mm)
 * or picked in the shared month calendar (resources/js/calendar.js), which then also lists the times. Touch devices
 * open the system's own date (or date and time) picker instead.
 *
 * The value is a 'Y-m-d' string, or 'Y-m-d H:i' once a time is chosen, or an empty string for no date, bound to
 * Livewire through x-modelable. The typed text only becomes the value once it is a real day within min/max and, if
 * given, a real time; anything else puts back the last value.
 */
import { isOutOfRange, joinDateTime, splitDateTime, toDate, toIso } from './calendar';

/** @param {string} value 'Y-m-d' or 'Y-m-d H:i' */
function display(value) {
    const [day, time] = splitDateTime(value);

    return joinDateTime(day.split('-').reverse().join('/'), time);
}

/** Turns a dd/mm/yyyy text into a 'Y-m-d' string, or null when it is not a real day. @param {string} text */
function parseDay(text) {
    const match = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

    if (! match) {
        return null;
    }

    const iso = `${match[3]}-${match[2]}-${match[1]}`;

    return toIso(toDate(iso)) === iso ? iso : null;
}

/** Turns a hh:mm text into the same 'H:i' string, or null when it is not a real time. @param {string} text */
function parseTime(text) {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(text) ? text : null;
}

export default function registerDateInput(Alpine) {
    Alpine.data('dateInput', ({ min = null, max = null, withTime = false }) => ({
        value: '',
        text: '',

        init() {
            this.text = display(this.value);
            this.$watch('value', (value) => (this.text = display(value)));
        },

        /**
         * The value as the system's date and time picker holds it, 'Y-m-dTH:i'. That picker cannot leave the time out,
         * so a day without a time shows midnight, and midnight comes back as no time.
         */
        get nativeValue() {
            const [day, time] = splitDateTime(this.value);

            return day ? `${day}T${time || '00:00'}` : '';
        },

        set nativeValue(native) {
            const [day = '', time = ''] = (native ?? '').split('T');
            const minutes = time.slice(0, 5);

            this.value = joinDateTime(day, minutes === '00:00' ? '' : minutes);
        },

        commit() {
            const text = this.text.replace(/[_\s:/]+$/, '').trim();

            if (text === '') {
                this.value = '';

                return;
            }

            const [dayText, timeText = ''] = text.split(/\s+/);
            const day = parseDay(dayText);
            const time = withTime && timeText !== '' ? parseTime(timeText) : '';

            if (day && time !== null && ! isOutOfRange(day, min, max)) {
                this.value = joinDateTime(day, time);
            }

            this.text = display(this.value);
        },
    }));
}
