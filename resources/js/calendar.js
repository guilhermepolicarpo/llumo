/**
 * The month calendar shared by the date pickers, drawn inside their popover (resources/views/components/calendar.blade.php).
 *
 * Every date is a 'Y-m-d' string, turned into a local Date only to do math or format it, so the browser time
 * zone never shifts a day. Today comes from the server, in the application time zone.
 *
 * The calendar is nested in the picker that opens it and works on that picker's scope: it reads and sets its
 * `value`, the chosen day as 'Y-m-d', or with `timeList` the chosen day and time as 'Y-m-d H:i', and gives the focus
 * back to its `$refs.trigger` once it closes.
 *
 * With `timeList`, the times of the day, `interval` minutes apart, are listed beside the weeks: picking a day keeps
 * the time and the calendar open, and picking a time closes it.
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

/** @param {number} number */
export function pad(number) {
    return String(number).padStart(2, '0');
}

/** @param {Date} date */
export function toIso(date) {
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

/**
 * Splits a picker value, 'Y-m-d' or 'Y-m-d H:i', into its day and its time, either of them an empty string.
 *
 * @param {?string} value
 * @returns {[string, string]}
 */
export function splitDateTime(value) {
    const [day = '', time = ''] = (value ?? '').split(' ');

    return [day, time];
}

/** Joins a day and a time, either of which may be empty, into a picker value. @param {string} day @param {string} time */
export function joinDateTime(day, time) {
    return day && time ? `${day} ${time}` : day;
}

/** The times of a day, 'H:i', the given number of minutes apart. @param {number} interval */
function timesOfDay(interval) {
    return Array.from({ length: Math.ceil(1440 / interval) }, (_, index) => {
        const minutes = index * interval;

        return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
    });
}

/** @param {string} month 'Y-m' */
function lastDayOf(month) {
    return addDays(addMonths(`${month}-01`, 1), -1);
}

export default function registerCalendar(Alpine) {
    Alpine.data('calendar', ({ today, locale, markers = null, min = null, max = null, timeList = false, interval = 15 }) => {
        const format = makeFormatter(locale);

        return {
            today,

            /** The times listed beside the weeks, none without `timeList`. */
            times: timeList ? timesOfDay(interval) : [],

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

            /** The chosen day, 'Y-m-d', or an empty string. */
            get selectedDay() {
                return splitDateTime(this.value)[0];
            },

            /** The chosen time, 'H:i', or an empty string. */
            get selectedTime() {
                return splitDateTime(this.value)[1];
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

            /**
             * Chooses a day, or none with an empty string, and closes the calendar. With the times listed, the time is
             * kept and the calendar stays open to pick one. @param {string} iso
             */
            pick(iso) {
                if (iso && isOutOfRange(iso, min, max)) {
                    return;
                }

                if (! timeList || ! iso) {
                    this.value = iso;
                    this.close();

                    return;
                }

                this.value = joinDateTime(iso, this.selectedTime);
                this.focused = iso;
            },

            /** Chooses a time for the chosen day, or for the day in focus when none is chosen, and closes. @param {string} time */
            pickTime(time) {
                const day = this.selectedDay || this.focused;

                if (isOutOfRange(day, min, max)) {
                    return;
                }

                this.value = joinDateTime(day, time);
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

                this.focused = this.selectedDay || today;
                this.loadMarkers();
                this.focusDay();
                this.scrollToTime();
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

            /** Moves the focus between the listed times with the up and down arrows. @param {KeyboardEvent} event */
            navigateTimes(event) {
                const step = { ArrowUp: -1, ArrowDown: 1 }[event.key];
                const index = this.times.indexOf(event.target.dataset.time);

                if (! step || index === -1) {
                    return;
                }

                event.preventDefault();
                this.$refs.times.querySelector(`[data-time="${this.times[Math.min(Math.max(index + step, 0), this.times.length - 1)]}"]`)?.focus();
            },

            /** Scrolls the listed times to the chosen one, or to the current time when none is chosen. */
            scrollToTime() {
                if (! timeList) {
                    return;
                }

                const now = new Date();
                const time = this.selectedTime || this.times[Math.floor((now.getHours() * 60 + now.getMinutes()) / interval)];

                this.$nextTick(() => {
                    const list = this.$refs.times;
                    const button = list?.querySelector(`[data-time="${time}"]`);

                    if (button) {
                        list.scrollTop = button.offsetTop - list.clientHeight / 2 + button.offsetHeight / 2;
                        list.scrollLeft = button.offsetLeft - list.clientWidth / 2 + button.offsetWidth / 2;
                    }
                });
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
