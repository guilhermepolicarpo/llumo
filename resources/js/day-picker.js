/**
 * Picks a single day: steps one day at a time, opens a month calendar from the date, and optionally clears the
 * day so every date is listed.
 *
 * The value is a 'Y-m-d' string, or an empty string for every date, bound to Livewire through x-modelable. Every
 * date is handled as that string and turned into a local Date only to do math or format it, so the browser time
 * zone never shifts a day. Today comes from the server, in the application time zone.
 *
 * The calendar may mark days, such as the ones with appointments: the marked days of each month shown are asked
 * from a renderless method of the Livewire component, named by `markers`, and kept for the rest of the visit.
 */

/** @param {string} iso */
function toDate(iso) {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** @param {Date} date */
function toIso(date) {
    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** @param {string} iso @param {number} days */
function addDays(iso, days) {
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

/** @param {string} text */
function capitalize(text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

export default function registerDayPicker(Alpine) {
    Alpine.data('dayPicker', ({ today, locale, markers = null }) => {
        /** One formatter per set of options, since building them costs far more than formatting. @type {Map<string, Intl.DateTimeFormat>} */
        const formatters = new Map();

        const format = (iso, options) => {
            const key = JSON.stringify(options);

            if (! formatters.has(key)) {
                formatters.set(key, new Intl.DateTimeFormat(locale, options));
            }

            return formatters.get(key).format(toDate(iso));
        };

        const weekdayName = (iso) => capitalize(format(iso, { weekday: 'long' }));

        /** The narrow weekday names from Sunday on, taken from a week that starts on a Sunday. */
        const weekdays = Array.from({ length: 7 }, (_, index) => format(addDays('2023-01-01', index), { weekday: 'narrow' }));

        return {
            today,
            weekdays,
            value: '',

            /** The day the calendar keyboard focus is on, which also sets the month shown. */
            focused: today,

            /** @type {Record<string, string[]>} */
            marked: {},

            init() {
                this.$watch('month', () => this.loadMarkers());
            },

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

            get month() {
                return this.focused.slice(0, 7);
            },

            get monthLabel() {
                return capitalize(format(this.focused, { month: 'long', year: 'numeric' }));
            },

            /** The weeks of the month shown, from the Sunday before its first day to the Saturday after its last. */
            get days() {
                const first = toDate(`${this.month}-01`);
                const start = addDays(`${this.month}-01`, -first.getDay());
                const daysInMonth = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
                const length = Math.ceil((first.getDay() + daysInMonth) / 7) * 7;
                const marked = new Set(this.marked[this.month]);

                return Array.from({ length }, (_, index) => {
                    const iso = addDays(start, index);

                    return {
                        iso,
                        number: Number(iso.slice(8)),
                        label: format(iso, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
                        inMonth: iso.startsWith(this.month),
                        marked: marked.has(iso),
                    };
                });
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

            /** @param {string} iso */
            select(iso) {
                this.value = iso;
                this.close();
            },

            close() {
                this.$refs.panel.hidePopover();
                this.$refs.trigger.focus();
            },

            /** @param {ToggleEvent} event */
            toggled(event) {
                if (event.newState !== 'open') {
                    return;
                }

                this.focused = this.day;
                this.loadMarkers();
                this.focusDay();
            },

            /** @param {number} months */
            showMonth(months) {
                this.focused = addMonths(this.focused, months);
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
                this.$nextTick(() => this.$refs.panel.querySelector(`[data-day="${this.focused}"]`)?.focus());
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
