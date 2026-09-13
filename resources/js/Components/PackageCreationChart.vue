<script setup>
import { ref, onMounted, watch } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    chartData: {
        type: Array,
        required: true,
    },
});

const canvasRef = ref(null);
let chartInstance = null;

function buildChart() {
    if (chartInstance) {
        chartInstance.destroy();
    }

    const labels = props.chartData.map(item => item.label);
    const data = props.chartData.map(item => item.value);

    chartInstance = new Chart(canvasRef.value, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Packages Created',
                    data,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.2)',
                    tension: 0.3,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#e5e7eb',
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.1)',
                    },
                },
                x: {
                    ticks: {
                        color: '#e5e7eb',
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.1)',
                    },
                },
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#e5e7eb',
                    },
                },
                tooltip: {
                    backgroundColor: '#111c2d',
                    titleColor: '#f8fafc',
                    bodyColor: '#e5e7eb',
                },
            },
        },
    });
}

// Re‑build chart when data changes
watch(
    () => props.chartData,
    () => {
        buildChart();
    },
    { deep: true }
);

onMounted(() => {
    buildChart();
});
</script>

<template>
    <div class="chart-container" style="height: 300px;">
        <canvas ref="canvasRef"></canvas>
    </div>
</template>

<style scoped>
.chart-container {
    position: relative;
    width: 100%;
}
</style>
