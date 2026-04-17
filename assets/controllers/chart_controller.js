import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

/**
 * Generic Chart.js controller.
 * Usage in Twig:
 *   <canvas data-controller="chart"
 *           data-chart-type-value="bar"
 *           data-chart-data-value='{{ chartData|json_encode }}'
 *           data-chart-options-value='{{ chartOptions|json_encode }}'
 *           height="300"></canvas>
 */
export default class extends Controller {
    static values = {
        type:    { type: String,  default: 'bar' },
        data:    { type: Object,  default: {} },
        options: { type: Object,  default: {} },
    };

    _chart = null;

    connect() {
        this._chart = new Chart(this.element, {
            type:    this.typeValue,
            data:    this.dataValue,
            options: this._mergeDefaults(this.optionsValue),
        });
    }

    disconnect() {
        if (this._chart) {
            this._chart.destroy();
            this._chart = null;
        }
    }

    dataValueChanged() {
        if (this._chart) {
            this._chart.data = this.dataValue;
            this._chart.update();
        }
    }

    _mergeDefaults(opts) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } },
                ...(opts.plugins || {}),
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr-TN') + ' DT' } },
                ...(opts.scales || {}),
            },
            ...opts,
        };
    }
}
