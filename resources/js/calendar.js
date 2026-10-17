/**
 * The month calendar shared by the date pickers, drawn inside their popover (resources/views/components/calendar.blade.php).
 *
 * Every date is a 'Y-m-d' string, turned into a local Date only to do math or format it, so the browser time
 * zone never shifts a day. Today comes from the server, in the application time zone.
 *
 * The calendar is nested in the picker that opens it and works on that picker's scope: it reads and sets its
 * `value`, the chosen day, and gives the focus back to its `$refs.trigger` once it closes.
 *
 * Days outside `min`/`max` cannot be picked. With `markers`, the name of a renderless method of the Livewire
 * component that returns the days of a month ('Y-m') to mark, those days get a dot, asked once per month shown;
 * without it, only today does.
 */

/** @param {string} iso */
export function toDate(iso) {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** @param {Date} date */
export function toIso(date) {
    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** @param {string} iso @param {number} days */
export function addDays(iso, days) {
    const date = toDate(iso);
    date.setDate(date.getDate() + days);

    return toIso(date);
}

/** Moves a day by whole months, holding it at the last day of a shorter month. @param {string} iso @param {number} months */
function addMonths(iso, months) {
    const date = toDate(iso);
    const target = new Date(date.getFullYear(), date.getMonth() + months, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    target.setDate(Math.min(date.getDate(), lastDay));

    return toIso(target);
}

/** Whether a day falls before `min` or after `max`, either of which may be missing. @param {string} iso @param {?string} min @param {?string} max */
export function isOutOfRange(iso, min, max) {
    return Boolean((min && iso < min) || (max && iso > max));
}

/** @param {string} text */
export function capitalize(text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Formats days for a locale, building one formatter per set of options since that costs far more than formatting. @param {string} locale */
export function makeFormatter(locale) {
    /** @type {Map<string, Intl.DateTimeFormat>} */
    const formatters = new Map();

    return (iso, options) => {
        const key = JSON.stringify(options);

        if (! formatters.has(key)) {
            formatters.set(key, new Intl.DateTimeFormat(locale, options));
        }

        return formatters.get(key).format(toDate(iso));
    };
}

/** @param {string} month 'Y-m' */
function lastDayOf(month) {
    return addDays(addMonths(`${month}-01`, 1), -1);
}

export default function registerCalendar(Alpine) {
    Alpine.data('calendar', ({ today, locale, markers = null, min = null, max = null }) => {
        const format = makeFormatter(locale);

        return {
            today,

            /** Whether the popover is open, so the weeks are only drawn while they can be seen. */
            open: false,

            /** The narrow weekday names from Sunday on, taken from a week that starts on a Sunday. */
            weekdays: Array.from({ length: 7 }, (_, index) => format(addDays('2023-01-01', index), { weekday: 'narrow' })),

            /** The day the keyboard focus is on, which also sets the month shown. */
            focused: today,

            /** @type {Record<string, string[]>} */
            marked: {},

            init() {
                this.$watch('month', () => this.loadMarkers());
            },

            get month() {
                return this.focused.slice(0, 7);
            },

            get year() {
                return Number(this.focused.slice(0, 4));
            },

            get monthLabel() {
                return capitalize(format(this.focused, { month: 'long', year: 'numeric' }));
            },

            get monthName() {
                return capitalize(format(this.focused, { month: 'long' }));
            },

            /** The weeks of the month shown, from the Sunday before its first day to the Saturday after its last. */
            get days() {
                const first = `${this.month}-01`;
                const offset = toDate(first).getDay();
                const start = addDays(first, -offset);
                const length = Math.ceil((offset + toDate(lastDayOf(this.month)).getDate()) / 7) * 7;
                const marked = new Set(this.marked[this.month]);

                return Array.from({ length }, (_, index) => {
                    const iso = addDays(start, index);

                    return {
                        iso,
                        number: Number(iso.slice(8)),
                        label: format(iso, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
                        inMonth: iso.startsWith(this.month),
                        isToday: iso === today,
                        disabled: isOutOfRange(iso, min, max),
                        marked: markers ? marked.has(iso) : iso === today,
                    };
                });
            },

            /** @param {string} iso */
            isOutOfRange(iso) {
                return isOutOfRange(iso, min, max);
            },

            /** Whether the month that many months away has any day within the range. @param {number} months */
            canShowMonth(months) {
                const first = addMonths(`${this.month}-01`, months);

                return ! ((max && first > max) || (min && lastDayOf(first.slice(0, 7)) < min));
            },

            /** @param {number} months */
            showMonth(months) {
                this.focused = addMonths(this.focused, months);
            },

            /** @param {number|string} year */
            showYear(year) {
                this.focused = addMonths(this.focused, (Number(year) - this.year) * 12);
            },

            /** Chooses a day, or none with an empty string, and closes the calendar. @param {string} iso */
            pick(iso) {
                if (iso && isOutOfRange(iso, min, max)) {
                    return;
                }

                this.value = iso;
                this.close();
            },

            close() {
                this.$root.hidePopover();
                this.$refs.trigger?.focus();
            },

            /** @param {ToggleEvent} event */
            toggled(event) {
                this.open = event.newState === 'open';

                if (! this.open) {
                    return;
                }

                this.focused = this.value || today;
                this.loadMarkers();
                this.focusDay();
            },

            /** @param {KeyboardEvent} event */
            navigate(event) {
                const moves = {
                    ArrowLeft: () => addDays(this.focused, -1),
                    ArrowRight: () => addDays(this.focused, 1),
                    ArrowUp: () => addDays(this.focused, -7),
                    ArrowDown: () => addDays(this.focused, 7),
                    PageUp: () => addMonths(this.focused, -1),
                    PageDown: () => addMonths(this.focused, 1),
                };

                if (! moves[event.key]) {
                    return;
                }

                event.preventDefault();
                this.focused = moves[event.key]();
                this.focusDay();
            },

            focusDay() {
                this.$nextTick(() => this.$root.querySelector(`[data-day="${this.focused}"]`)?.focus());
            },

            async loadMarkers() {
                const month = this.month;

                if (! markers || month in this.marked) {
                    return;
                }

                this.marked[month] = [];
                this.marked[month] = await this.$wire[markers](month);
            },
        };
    });
}
