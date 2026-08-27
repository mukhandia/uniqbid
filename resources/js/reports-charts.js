import Chart from 'chart.js/auto';

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function textColor() {
    return isDark() ? '#e4e4e7' : '#525252';
}

function gridColor() {
    return isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(24,24,27,0.08)';
}

function hexAlpha(hex, alpha) {
    const raw = hex.replace('#', '');
    const value = Number.parseInt(raw, 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function kes(value) {
    return `KES ${new Intl.NumberFormat('en-KE').format(Number(value) || 0)}`;
}

function chartOptions(payload) {
    const currency = Boolean(payload.currency);
    const doughnut = payload.type === 'doughnut';

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: doughnut ? 'nearest' : 'index',
            intersect: doughnut,
        },
        plugins: {
            legend: {
                position: doughnut ? 'bottom' : 'top',
                labels: {
                    color: textColor(),
                    boxWidth: 12,
                    padding: 16,
                },
            },
            tooltip: {
                callbacks: {
                    label(context) {
                        const label = context.dataset.label ? `${context.dataset.label}: ` : '';
                        const value = context.parsed.y ?? context.parsed;

                        return currency ? `${label}${kes(value)}` : `${label}${value}`;
                    },
                },
            },
        },
        scales: doughnut
            ? {}
            : {
                x: {
                    stacked: payload.type === 'bar',
                    ticks: { color: textColor(), maxRotation: 0 },
                    grid: { display: false },
                },
                y: {
                    stacked: payload.type === 'bar',
                    beginAtZero: true,
                    ticks: {
                        color: textColor(),
                        callback(value) {
                            return currency ? kes(value) : value;
                        },
                    },
                    grid: { color: gridColor() },
                },
            },
    };
}

function chartData(payload) {
    if (payload.type === 'doughnut') {
        const dataset = payload.datasets?.[0] ?? { data: [], colors: payload.colors ?? [] };

        return {
            labels: payload.labels ?? [],
            datasets: [
                {
                    label: dataset.label ?? 'Total',
                    data: dataset.data ?? [],
                    backgroundColor: dataset.colors ?? payload.colors ?? [],
                    borderWidth: 0,
                },
            ],
        };
    }

    return {
        labels: payload.labels ?? [],
        datasets: (payload.datasets ?? []).map((dataset) => ({
            label: dataset.label,
            data: dataset.data ?? [],
            borderColor: dataset.color,
            backgroundColor: payload.type === 'line' ? hexAlpha(dataset.color, 0.15) : hexAlpha(dataset.color, 0.85),
            fill: payload.type === 'line',
            tension: 0.3,
            pointRadius: payload.type === 'line' ? 2 : 0,
            borderWidth: payload.type === 'line' ? 2 : 0,
        })),
    };
}

export function bootReportCharts() {
    document.querySelectorAll('[data-report-chart]').forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }

        const canvas = el.querySelector('canvas');

        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        const raw = el.getAttribute('data-report-chart');

        if (!raw) {
            return;
        }

        const existing = Chart.getChart(canvas);
        existing?.destroy();

        try {
            const payload = JSON.parse(raw);

            new Chart(canvas, {
                type: payload.type || 'line',
                data: chartData(payload),
                options: chartOptions(payload),
            });
        } catch (error) {
            console.error('UniqBid reports chart failed to render.', error);
        }
    });
}
