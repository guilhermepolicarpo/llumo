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
        const weekdayAbbreviation = (iso) => capitalize(format(iso, { weekday: 'short' }).replace(/\.$/, ''));
        const dateAbbreviation = (iso) => format(iso, { day: '2-digit', month: 'short', year: 'numeric' }).replace(/\./g, '');

        return {
            today,
            value: '',

            /**
             * Every weekday and every month the label can show, laid invisibly under it so the picker always takes the
             * width of the longest one, taken from a week that starts on a Sunday and from the months of this year.
             */
            widestWeekdays: Array.from({ length: 7 }, (_, index) => weekdayAbbreviation(addDays('2023-01-01', index))),
            widestDates: Array.from({ length: 12 }, (_, index) => dateAbbreviation(`${today.slice(0, 4)}-${String(index + 1).padStart(2, '0')}-28`)),

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

            get weekdayShort() {
                return weekdayAbbreviation(this.day);
            },

            /** Abbreviates the month, such as "19 de out de 2026", so the picker keeps its width from day to day. */
            get dateMedium() {
                return dateAbbreviation(this.day);
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
