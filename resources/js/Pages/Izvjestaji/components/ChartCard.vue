<template>
  <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
    <h3 class="text-sm font-semibold mb-2 text-gray-700 dark:text-gray-200">{{ title }}</h3>
    <!-- Fixed-height wrapper prevents infinite growth loops -->
    <div class="relative h-72">
      <canvas :id="canvasId" class="w-full h-full"></canvas>
    </div>
  </div>

</template>

<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, LineController, LineElement, PointElement, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, LineController, LineElement, PointElement, Tooltip, Legend);

const props = defineProps({
  title: String,
  type: { type: String, default: 'bar' },
  labels: { type: Array, default: () => [] },
  datasets: { type: Array, default: () => [] },
  canvasId: { type: String, required: true },
});

let chartInstance = null;

function render() {
  const ctx = document.getElementById(props.canvasId);
  if (!ctx) return;
  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(ctx, {
    type: props.type,
    data: {
      labels: props.labels,
      datasets: props.datasets,
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: props.type === 'bar' ? { x: { stacked: false }, y: { beginAtZero: true } } : {}
    }
  });
}

onMounted(render);
onBeforeUnmount(() => { if (chartInstance) chartInstance.destroy(); });
watch(() => [props.labels, props.datasets, props.type], render, { deep: true });
</script>
