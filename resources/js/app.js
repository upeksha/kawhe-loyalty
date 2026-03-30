import './bootstrap';
import 'flowbite';
import Chart from 'chart.js/auto';

import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.disableSubmitLoading === 'false') return;

    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const targets = submitter
        ? [submitter]
        : Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

    targets.forEach((el) => {
        if ('disabled' in el) {
            el.disabled = true;
        }

        if (!(el instanceof HTMLButtonElement)) return;
        if (el.dataset.originalText) return;

        const loadingText = el.dataset.loadingText;
        if (!loadingText) return;

        el.dataset.originalText = el.innerHTML;
        el.innerHTML = loadingText;
    });
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

const dashboardCharts = [];

function createDashboardChart(canvas) {
    if (!(canvas instanceof HTMLCanvasElement)) return null;

    const rawConfig = canvas.dataset.chart;
    if (!rawConfig) return null;

    let parsed;
    try {
        parsed = JSON.parse(rawConfig);
    } catch (error) {
        console.error('Invalid chart config', error);
        return null;
    }

    const chart = new Chart(canvas, {
        type: parsed.type ?? 'line',
        data: parsed.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 100,
            animation: {
                duration: 700,
                easing: 'easeOutQuart',
            },
            plugins: {
                legend: parsed.options?.plugins?.legend ?? { display: false },
                tooltip: {
                    backgroundColor: '#1c1917',
                    titleColor: '#fafaf9',
                    bodyColor: '#fafaf9',
                    displayColors: true,
                    padding: 12,
                    cornerRadius: 12,
                    intersect: false,
                    mode: 'index',
                    ...parsed.options?.plugins?.tooltip,
                },
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
            scales: parsed.options?.scales ?? {},
            elements: parsed.options?.elements ?? {},
            ...parsed.options,
        },
    });

    return chart;
}

function initializeDashboardCharts() {
    dashboardCharts.splice(0).forEach((chart) => chart.destroy());

    document.querySelectorAll('[data-chart]').forEach((canvas) => {
        const chart = createDashboardChart(canvas);
        if (chart) {
            dashboardCharts.push(chart);
        }
    });
}

Chart.defaults.font.family = 'Figtree, sans-serif';
Chart.defaults.color = '#78716c';
Chart.defaults.borderColor = '#e7e5e4';

document.addEventListener('DOMContentLoaded', initializeDashboardCharts);
