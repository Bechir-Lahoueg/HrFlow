import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['grid', 'monthLabel', 'todayBtn'];
    static values = {
        leaves: { type: Array, default: [] },
        country: { type: String, default: 'TN' },
        year: Number,
        month: Number,
    };

    connect() {
        const now = new Date();
        if (!this.hasYearValue) this.yearValue = now.getFullYear();
        if (!this.hasMonthValue) this.monthValue = now.getMonth();
        this._holidays = {};
        this._fetchHolidays(this.yearValue).then(() => this._render());
    }

    prev() {
        this.monthValue--;
        if (this.monthValue < 0) { this.monthValue = 11; this.yearValue--; }
        this._ensureHolidays().then(() => this._render());
    }

    next() {
        this.monthValue++;
        if (this.monthValue > 11) { this.monthValue = 0; this.yearValue++; }
        this._ensureHolidays().then(() => this._render());
    }

    today() {
        const now = new Date();
        this.yearValue = now.getFullYear();
        this.monthValue = now.getMonth();
        this._ensureHolidays().then(() => this._render());
    }

    async _ensureHolidays() {
        if (!this._holidays[this.yearValue]) {
            await this._fetchHolidays(this.yearValue);
        }
    }

    async _fetchHolidays(year) {
        try {
            const res = await fetch('https://date.nager.at/api/v3/PublicHolidays/' + year + '/' + this.countryValue);
            if (res.ok) {
                const data = await res.json();
                this._holidays[year] = {};
                data.forEach(h => { this._holidays[year][h.date] = h.localName || h.name; });
            }
        } catch (_) {
            this._holidays[year] = {};
        }
    }

    _render() {
        const year = this.yearValue;
        const month = this.monthValue;
        const today = new Date();
        const todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

        const monthNames = ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre'];
        const dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

        this.monthLabelTarget.textContent = monthNames[month] + ' ' + year;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        let startDay = firstDay.getDay() - 1;
        if (startDay < 0) startDay = 6;

        const holidays = (this._holidays[year]) || {};
        const leaves = this.leavesValue || [];

        let html = '<div class="grid grid-cols-7 gap-px">';

        // Day headers
        dayNames.forEach((d, i) => {
            const isWeekend = i >= 5;
            html += '<div class="cal-header ' + (isWeekend ? 'text-rose-400 dark:text-rose-500' : '') + '">' + d + '</div>';
        });

        // Empty cells before month start
        for (let i = 0; i < startDay; i++) {
            html += '<div class="cal-cell cal-empty"></div>';
        }

        // Day cells
        for (let d = 1; d <= lastDay.getDate(); d++) {
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            const dayOfWeek = new Date(year, month, d).getDay();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
            const isToday = dateStr === todayStr;
            const holiday = holidays[dateStr] || null;

            // Find leaves for this date
            const dayLeaves = [];
            leaves.forEach(l => {
                if (dateStr >= l.start && dateStr <= l.end) {
                    dayLeaves.push(l);
                }
            });

            let cls = 'cal-cell';
            if (isToday) cls += ' cal-today';
            if (isWeekend) cls += ' cal-weekend';
            if (holiday) cls += ' cal-holiday';

            html += '<div class="' + cls + '">';
            html += '<span class="cal-day-num' + (isToday ? ' cal-day-today' : '') + '">' + d + '</span>';

            if (holiday) {
                html += '<div class="cal-event cal-event-holiday" title="' + this._esc(holiday) + '">';
                html += '<svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>';
                html += '<span class="truncate">' + this._esc(holiday) + '</span></div>';
            }

            dayLeaves.forEach(l => {
                html += '<div class="cal-event cal-event-leave" title="' + this._esc(l.name) + ' — ' + this._esc(l.type) + '">';
                html += '<span class="w-1.5 h-1.5 rounded-full bg-teal-400 shrink-0"></span>';
                html += '<span class="truncate">' + this._esc(l.name) + '</span></div>';
            });

            html += '</div>';
        }

        // Fill remaining cells to complete the grid
        const totalCells = startDay + lastDay.getDate();
        const remainder = totalCells % 7;
        if (remainder > 0) {
            for (let i = 0; i < 7 - remainder; i++) {
                html += '<div class="cal-cell cal-empty"></div>';
            }
        }

        html += '</div>';
        this.gridTarget.innerHTML = html;
    }

    _esc(str) {
        const el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }
}
