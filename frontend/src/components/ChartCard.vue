<script setup lang="ts">
import { computed } from 'vue'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
} from 'chart.js'
import { Bar, Line } from 'vue-chartjs'
import type { ChartPayload } from '../services/adminChat'

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Tooltip)

const props = defineProps<{ chart: ChartPayload }>()

// #6366f1 validated for the dark surface (#0f172a): OKLCH L in band, chroma and 3:1 contrast pass
const SERIES_COLOR = '#6366f1'
const TICK_COLOR = '#94a3b8'
const GRID_COLOR = 'rgba(148, 163, 184, 0.1)'

const barData = computed(() => ({
  labels: props.chart.labels,
  datasets: props.chart.datasets.map((dataset) => ({
    label: dataset.label,
    data: dataset.data,
    backgroundColor: SERIES_COLOR,
    borderRadius: 4,
    borderSkipped: 'start' as const,
    maxBarThickness: 42,
  })),
}))

const lineData = computed(() => ({
  labels: props.chart.labels,
  datasets: props.chart.datasets.map((dataset) => ({
    label: dataset.label,
    data: dataset.data,
    borderColor: SERIES_COLOR,
    backgroundColor: SERIES_COLOR,
    borderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
    tension: 0.3,
  })),
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: 'rgba(15, 23, 42, 0.95)',
      titleColor: '#e2e8f0',
      bodyColor: '#e2e8f0',
      borderColor: 'rgba(129, 140, 248, 0.35)',
      borderWidth: 1,
      padding: 10,
      displayColors: false,
    },
  },
  scales: {
    x: {
      grid: { display: false },
      border: { color: GRID_COLOR },
      ticks: { color: TICK_COLOR },
    },
    y: {
      beginAtZero: true,
      grid: { color: GRID_COLOR },
      border: { display: false },
      ticks: { color: TICK_COLOR },
    },
  },
}))
</script>

<template>
  <figure class="chart-card">
    <figcaption>{{ chart.title }} <span class="series-label">· {{ chart.datasets[0]?.label }}</span></figcaption>
    <div class="chart-canvas">
      <Bar v-if="chart.type === 'bar'" :data="barData" :options="chartOptions" />
      <Line v-else :data="lineData" :options="chartOptions" />
    </div>
  </figure>
</template>

<style scoped>
.chart-card {
  margin: 0;
  padding: 16px 18px;
  border: 1px solid rgba(148, 163, 184, 0.12);
  border-radius: 18px;
  background: rgba(30, 41, 59, 0.88);
}

.chart-card figcaption {
  margin-bottom: 12px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #e2e8f0;
}

.series-label {
  font-weight: 600;
  color: rgba(148, 163, 184, 0.9);
}

.chart-canvas {
  position: relative;
  height: 240px;
}
</style>
