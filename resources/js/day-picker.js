/**
 * Picks a single day to browse a list by: steps one day at a time, opens the shared month calendar
 * (resources/js/calendar.js) from the date, and optionally clears the day so every date is listed.
 *
 * The value is a 'Y-m-d' string, or an empty string for every date, bound to Livewire through x-modelable. Today
 * comes from the server, in the application time zone.
 */
import { addDays, capitalize, makeFormatter } from './calendar';

export default function registerDayPicker(Alpine) {
    Alpine.data('dayPicker', ({ today, locale }) => {
        const format = makeFormatter(locale);
        const weekdayName = (iso) => capitalize(format(iso, { weekday: 'long' }));

        return {
            today,
            value: '',

            /** Whether every date is listed instead of a single day. */
            get all() {
                return this.value === '';
            },

            get day() {
                return this.value || today;
            },

            get isToday() {
                return this.value === today;
            },

            get weekdayLong() {
                return weekdayName(this.day);
            },

            get weekdayShort() {
                return capitalize(format(this.day, { weekday: 'short' }).replace(/\.$/, ''));
            },

            get dateLong() {
                return format(this.day, { day: 'numeric', month: 'long', year: 'numeric' });
            },

            get dateShort() {
                return format(this.day, { day: '2-digit', month: '2-digit', year: 'numeric' });
            },

            /** Names the day an arrow leads to, such as "Sexta-feira, 25/09". @param {number} days */
            neighbourLabel(days) {
                const iso = addDays(this.day, days);

                return `${weekdayName(iso)}, ${format(iso, { day: '2-digit', month: '2-digit' })}`;
            },

            /** @param {number} days */
            shift(days) {
                this.value = addDays(this.value, days);
            },

            toggleAll() {
                this.value = this.all ? today : '';
            },
        };
    });
}
