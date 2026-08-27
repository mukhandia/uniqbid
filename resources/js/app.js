import { bootReportCharts } from './reports-charts';

window.UniqbidReportsCharts = { boot: bootReportCharts };

function boot() {
    bootReportCharts();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

document.addEventListener('livewire:navigated', boot);

document.addEventListener('livewire:init', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(boot);
        });
    });
});
