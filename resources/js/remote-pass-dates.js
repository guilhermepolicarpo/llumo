/**
 * The dates of the remote passes prescribed on the appointment record, chosen in a modal before the remote pass sheet is
 * printed.
 *
 * The rows live in the record form ($wire.form.passPrescriptions), so the modal follows whatever was picked without a
 * request. Each remote row gets one date per pass, suggested on the weekdays the center gives that pass type remotely
 * (or weekly, when it set none) and kept as 'Y-m-d' strings, blank for a date left to be written by hand.
 */
import { addDays, toDate } from './calendar';
import { catalogEntryName } from './catalog-picker';

const REMOTE = 'remote';

/** The key under which the pickers keep the pass types they loaded. */
const PASS_TYPES = 'pass_type';

/** The ISO weekday of a 'Y-m-d' date: Monday is 1, Sunday is 7. @param {string} iso */
function isoWeekday(iso) {
    return toDate(iso).getDay() || 7;
}

/**
 * Suggest the given number of dates after the given day: on the given weekdays, or once a week when there are none.
 *
 * @param {string} after
 * @param {number} count
 * @param {number[]} weekdays
 */
export function suggestDates(after, count, weekdays) {
    const dates = [];

    if (weekdays.length === 0) {
        for (let week = 1; dates.length < count; week++) {
            dates.push(addDays(after, 7 * week));
        }

        return dates;
    }

    for (let day = addDays(after, 1); dates.length < count; day = addDays(day, 1)) {
        if (weekdays.includes(isoWeekday(day))) {
            dates.push(day);
        }
    }

    return dates;
}

export default function registerRemotePassDates(Alpine) {
    Alpine.data('remotePassDates', ({ scheduledOn, schedules, printUrl, labels }) => ({
        labels,

        /** The remote rows of the form, each with its index among all the pass rows. */
        get remoteRows() {
            return (this.$wire.form.passPrescriptions ?? [])
                .map((row, index) => ({ ...row, index }))
                .filter((row) => row.mode === REMOTE);
        },

        get hasRemoteRows() {
            return this.remoteRows.length > 0;
        },

        /** How many passes a row prescribes, within the limits the form accepts. @param {{quantity: number|string}} row */
        quantityOf(row) {
            const quantity = parseInt(row.quantity, 10);

            return Number.isNaN(quantity) ? 1 : Math.min(Math.max(quantity, 1), 99);
        },

        /** "1 pass" or "5 passes", as the badge of a row. @param {{quantity: number|string}} row */
        countLabel(row) {
            const quantity = this.quantityOf(row);

            return quantity === 1 ? labels.onePass : labels.passes.replace(':count', quantity);
        },

        /** How many of the remote passes already have a date, as in "8 of 8 dates set". */
        get datesSummary() {
            let total = 0;
            let filled = 0;

            this.remoteRows.forEach((row) => {
                const quantity = this.quantityOf(row);

                total += quantity;
                filled += (row.dates ?? []).slice(0, quantity).filter((date) => date).length;
            });

            return labels.datesSet.replace(':filled', filled).replace(':total', total);
        },

        /** The label of a date field, as in "2nd pass". @param {number} position */
        positionLabel(position) {
            return labels.position.replace(':position', position);
        },

        /** The pass type's name, taken from the pass types the pickers loaded. @param {{pass_type_id: string}} row */
        nameOf(row) {
            return catalogEntryName(this.$wire.$id, PASS_TYPES, row.pass_type_id);
        },

        /** When the center gives the row's pass type remotely. @param {{pass_type_id: string}} row */
        scheduleOf(row) {
            return schedules[row.pass_type_id] ?? { weekdays: [], schedule: null };
        },

        /**
         * Give each remote row one date per pass: the dates already chosen are kept, and the missing ones are suggested
         * after the last of them. Called when the modal opens, so it always matches the quantities in the form.
         */
        fill() {
            this.remoteRows.forEach((row) => {
                const quantity = this.quantityOf(row);
                const chosen = (row.dates ?? []).slice(0, quantity).map((date) => date ?? '');
                const filled = chosen.filter((date) => date !== '').sort();

                this.setDates(row, filled.length === 0
                    ? this.suggestFor(row, scheduledOn, quantity)
                    : [...chosen, ...this.suggestFor(row, filled.at(-1), quantity - chosen.length)]);
            });
        },

        /** Replace every remote row's dates with fresh suggestions. */
        suggestAll() {
            this.remoteRows.forEach((row) => this.setDates(row, this.suggestFor(row, scheduledOn, this.quantityOf(row))));
        },

        /**
         * Suggest dates for a row on the days of its pass type.
         *
         * @param {{pass_type_id: string}} row @param {string} after @param {number} count
         */
        suggestFor(row, after, count) {
            return suggestDates(after, count, this.scheduleOf(row).weekdays);
        },

        /** @param {{index: number}} row @param {string[]} dates */
        setDates(row, dates) {
            this.$wire.form.passPrescriptions[row.index].dates = dates;
        },

        /**
         * Keep the form as a draft and open its remote pass sheet in a new tab. The tab is opened right on the click, so
         * the browser does not block it, and pointed at the sheet once the draft is kept; when the passes do not validate,
         * the tab closes and so does the modal, uncovering the errors on the form.
         */
        async saveAndPrint() {
            const tab = window.open('', '_blank');
            const saved = await this.$wire.prepareRemotePassSheet().catch(() => false);

            this.$flux.modal('remote-pass-dates').close();

            if (saved === true && tab) {
                tab.location.href = printUrl;
            } else {
                tab?.close();
            }
        },
    }));
}
