<script setup>
import { Chart, registerables } from 'chart.js';
import { onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

const props = defineProps({
    points: { type: Array, default: () => [] },
});

const canvasRef = ref(null);
let chart = null;

function build() {
    if (chart) chart.destroy();
    if (!canvasRef.value) return;

    chart = new Chart(canvasRef.value, {
        type: 'line',
        data: {
            labels: props.points.map((p) => p.date),
            datasets: [
                { label: 'Bookings', data: props.points.map((p) => p.bookings), borderColor: '#38bdf8', tension: 0.3, fill: false },
                { label: 'Gross booking value (₹)', data: props.points.map((p) => Number(p.gross_booking_value)), borderColor: '#f59e0b', tension: 0.3, fill: false, yAxisID: 'y1' },
                { label: 'Platform commission (₹)', data: props.points.map((p) => Number(p.platform_commission)), borderColor: '#34d399', tension: 0.3, fill: false, yAxisID: 'y1' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { color: '#e5e7eb' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                y1: { beginAtZero: true, position: 'right', ticks: { color: '#e5e7eb' }, grid: { drawOnChartArea: false } },
                x: { ticks: { color: '#e5e7eb', maxTicksLimit: 10 }, grid: { color: 'rgba(255,255,255,0.1)' } },
            },
            plugins: { legend: { labels: { color: '#e5e7eb' } } },
        },
    });
}

watch(() => props.points, build, { deep: true });
onMounted(build);
</script>

<template>
    <div class="chart-container" style="height: 280px;">
        <canvas ref="canvasRef"></canvas>
    </div>
</template>

<style scoped>
.chart-container { position: relative; width: 100%; }
</style>
