<script setup>
import { Chart, registerables } from 'chart.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

const props = defineProps({
    system: { type: Object, default: () => ({}) },
});

const trendCanvas = ref(null);
const moduleCanvas = ref(null);
const statusCanvas = ref(null);
let charts = [];

const moduleEntries = computed(() => Object.entries(props.system.modules ?? {}));
const statusEntries = computed(() => Object.entries(props.system.status_mix ?? {}));
const hasTrend = computed(() => (props.system.trend ?? []).some((point) => point.bookings > 0));
const hasModules = computed(() => moduleEntries.value.some(([, value]) => value.bookings > 0));
const hasStatuses = computed(() => statusEntries.value.some(([, value]) => value > 0));

function destroyCharts() {
    charts.forEach((chart) => chart.destroy());
    charts = [];
}

function buildCharts() {
    destroyCharts();

    if (hasTrend.value && trendCanvas.value) {
        charts.push(new Chart(trendCanvas.value, {
            type: 'line',
            data: {
                labels: (props.system.trend ?? []).map((point) => point.date),
                datasets: [{
                    label: 'Bookings',
                    data: (props.system.trend ?? []).map((point) => point.bookings),
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, .16)',
                    fill: true,
                    tension: .3,
                }],
            },
            options: chartOptions(),
        }));
    }

    if (hasModules.value && moduleCanvas.value) {
        charts.push(new Chart(moduleCanvas.value, {
            type: 'bar',
            data: {
                labels: moduleEntries.value.map(([, value]) => value.label),
                datasets: [{
                    label: 'Bookings',
                    data: moduleEntries.value.map(([, value]) => value.bookings),
                    backgroundColor: ['#f59e0b', '#60a5fa', '#34d399'],
                    borderRadius: 6,
                }],
            },
            options: chartOptions(),
        }));
    }

    if (hasStatuses.value && statusCanvas.value) {
        charts.push(new Chart(statusCanvas.value, {
            type: 'doughnut',
            data: {
                labels: statusEntries.value.map(([key]) => key.replace(/_/g, ' ')),
                datasets: [{
                    data: statusEntries.value.map(([, value]) => value),
                    backgroundColor: ['#f59e0b', '#38bdf8', '#34d399', '#fb7185'],
                    borderWidth: 0,
                }],
            },
            options: { ...chartOptions(), cutout: '62%' },
        }));
    }
}

function chartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: 'rgba(148, 163, 184, .16)' } },
            x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { display: false } },
        },
        plugins: { legend: { labels: { color: '#cbd5e1' } } },
    };
}

watch(() => props.system, () => nextTick(buildCharts), { deep: true });
onMounted(buildCharts);
onBeforeUnmount(destroyCharts);
</script>

<template>
    <div class="reporting-chart-grid">
        <div class="reporting-chart-card reporting-chart-card--wide">
            <h3>Bookings over time</h3>
            <div v-if="hasTrend" class="reporting-chart-canvas"><canvas ref="trendCanvas"></canvas></div>
            <p v-else class="reporting-chart-empty">No bookings in this period.</p>
        </div>
        <div class="reporting-chart-card">
            <h3>Bookings by module</h3>
            <div v-if="hasModules" class="reporting-chart-canvas"><canvas ref="moduleCanvas"></canvas></div>
            <p v-else class="reporting-chart-empty">No module activity in this period.</p>
        </div>
        <div class="reporting-chart-card">
            <h3>Status mix</h3>
            <div v-if="hasStatuses" class="reporting-chart-canvas"><canvas ref="statusCanvas"></canvas></div>
            <p v-else class="reporting-chart-empty">No status data in this period.</p>
        </div>
    </div>
</template>

<style scoped>
.reporting-chart-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; }
.reporting-chart-card { min-width: 0; padding: 1rem; border: 1px solid var(--admin-border); border-radius: .75rem; background: var(--admin-surface); }
.reporting-chart-card--wide { grid-column: span 1; }
.reporting-chart-card h3 { margin: 0 0 .85rem; color: var(--admin-text); font-size: .95rem; }
.reporting-chart-canvas { position: relative; height: 230px; }
.reporting-chart-empty { display: grid; min-height: 230px; margin: 0; place-items: center; color: var(--admin-text-muted); font-size: .85rem; text-align: center; }
@media (max-width: 991.98px) { .reporting-chart-grid { grid-template-columns: 1fr 1fr; } .reporting-chart-card--wide { grid-column: span 2; } }
@media (max-width: 575.98px) { .reporting-chart-grid { grid-template-columns: 1fr; } .reporting-chart-card--wide { grid-column: span 1; } }
</style>
