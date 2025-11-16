<template>
  <div class="card">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Performance Metrics</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Risk Distribution -->
      <div class="border border-gray-200 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 mb-4">Risk Distribution</h3>
        <div v-if="hasRiskData">
          <canvas ref="riskChartCanvas" class="max-h-64"></canvas>
          <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between text-sm">
              <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                <span class="text-gray-700">Safe</span>
              </div>
              <span class="font-medium">{{ riskDistribution.safe || 0 }} ({{ safePercentage }}%)</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                <span class="text-gray-700">Warning</span>
              </div>
              <span class="font-medium">{{ riskDistribution.warning || 0 }} ({{ warningPercentage }}%)</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                <span class="text-gray-700">Danger</span>
              </div>
              <span class="font-medium">{{ riskDistribution.danger || 0 }} ({{ dangerPercentage }}%)</span>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          No risk data available
        </div>
      </div>

      <!-- Execution Times -->
      <div class="border border-gray-200 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 mb-4">Recent Execution Times</h3>
        <div v-if="hasExecutionData">
          <canvas ref="executionChartCanvas" class="max-h-64"></canvas>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          No execution data available
        </div>
      </div>

      <!-- Table Sizes -->
      <div class="border border-gray-200 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 mb-4">Largest Tables</h3>
        <div v-if="hasTableSizeData" class="space-y-3">
          <div v-for="(table, index) in topTables" :key="index">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm text-gray-700">{{ table.name }}</span>
              <span class="text-sm font-medium text-gray-900">{{ formatBytes(table.size) }}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-blue-500 h-2 rounded-full" :style="{ width: getTableSizePercentage(table.size) + '%' }"></div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          No table size data available
        </div>
      </div>

      <!-- Archive Sizes -->
      <div class="border border-gray-200 rounded-lg p-4">
        <h3 class="font-semibold text-gray-900 mb-4">Archive Statistics</h3>
        <div v-if="hasArchiveData">
          <div class="space-y-4">
            <div class="text-center">
              <p class="text-3xl font-bold text-gray-900">{{ formatBytes(totalArchiveSize) }}</p>
              <p class="text-sm text-gray-600 mt-1">Total Archive Size</p>
            </div>
            <div class="grid grid-cols-2 gap-4 text-center">
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xl font-bold text-gray-900">{{ archiveCount }}</p>
                <p class="text-xs text-gray-600">Archives</p>
              </div>
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xl font-bold text-gray-900">{{ formatBytes(averageArchiveSize) }}</p>
                <p class="text-xs text-gray-600">Avg Size</p>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          No archive data available
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';
import { formatDuration, formatBytes, truncate } from '../utils/formatters';

// Register Chart.js components
Chart.register(...registerables);

const props = defineProps({
  metrics: {
    type: Object,
    default: () => ({}),
  },
});

// Chart refs
const riskChartCanvas = ref(null);
const executionChartCanvas = ref(null);
let riskChart = null;
let executionChart = null;

const riskDistribution = computed(() => props.metrics?.risk_distribution || {});
const executionTimes = computed(() => props.metrics?.execution_times || []);
const tableSizes = computed(() => props.metrics?.table_sizes || []);
const archiveSizes = computed(() => props.metrics?.archive_sizes || []);

const hasRiskData = computed(() => {
  const dist = riskDistribution.value;
  return (dist.safe || 0) + (dist.warning || 0) + (dist.danger || 0) > 0;
});

const hasExecutionData = computed(() => executionTimes.value.length > 0);
const hasTableSizeData = computed(() => tableSizes.value.length > 0);
const hasArchiveData = computed(() => archiveSizes.value.length > 0);

const totalOperations = computed(() => {
  const dist = riskDistribution.value;
  return (dist.safe || 0) + (dist.warning || 0) + (dist.danger || 0);
});

const safePercentage = computed(() => {
  if (totalOperations.value === 0) return 0;
  return Math.round((riskDistribution.value.safe || 0) / totalOperations.value * 100);
});

const warningPercentage = computed(() => {
  if (totalOperations.value === 0) return 0;
  return Math.round((riskDistribution.value.warning || 0) / totalOperations.value * 100);
});

const dangerPercentage = computed(() => {
  if (totalOperations.value === 0) return 0;
  return Math.round((riskDistribution.value.danger || 0) / totalOperations.value * 100);
});

const recentExecutions = computed(() => executionTimes.value.slice(0, 5));

const topTables = computed(() => {
  return [...tableSizes.value]
    .sort((a, b) => b.size - a.size)
    .slice(0, 5);
});

const maxTableSize = computed(() => {
  if (topTables.value.length === 0) return 0;
  return Math.max(...topTables.value.map(t => t.size));
});

function getTableSizePercentage(size) {
  if (maxTableSize.value === 0) return 0;
  return Math.round((size / maxTableSize.value) * 100);
}

const totalArchiveSize = computed(() => {
  return archiveSizes.value.reduce((total, item) => total + item.size, 0);
});

const archiveCount = computed(() => archiveSizes.value.length);

const averageArchiveSize = computed(() => {
  if (archiveCount.value === 0) return 0;
  return totalArchiveSize.value / archiveCount.value;
});

// Initialize Risk Distribution Chart (Doughnut)
function initRiskChart() {
  if (!riskChartCanvas.value || !hasRiskData.value) return;

  const ctx = riskChartCanvas.value.getContext('2d');
  const dist = riskDistribution.value;

  if (riskChart) {
    riskChart.destroy();
  }

  riskChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Safe', 'Warning', 'Danger'],
      datasets: [{
        data: [dist.safe || 0, dist.warning || 0, dist.danger || 0],
        backgroundColor: [
          'rgb(34, 197, 94)',   // green-500
          'rgb(234, 179, 8)',   // yellow-500
          'rgb(239, 68, 68)',   // red-500
        ],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false, // We have our own legend below
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const label = context.label || '';
              const value = context.parsed || 0;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
              return `${label}: ${value} (${percentage}%)`;
            }
          }
        }
      }
    }
  });
}

// Initialize Execution Times Chart (Bar)
function initExecutionChart() {
  if (!executionChartCanvas.value || !hasExecutionData.value) return;

  const ctx = executionChartCanvas.value.getContext('2d');
  const times = executionTimes.value.slice(0, 10); // Show last 10

  if (executionChart) {
    executionChart.destroy();
  }

  executionChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: times.map(t => truncate(t.migration, 20)),
      datasets: [{
        label: 'Execution Time (ms)',
        data: times.map(t => t.time),
        backgroundColor: 'rgba(59, 130, 246, 0.5)', // blue-500 with opacity
        borderColor: 'rgb(59, 130, 246)', // blue-500
        borderWidth: 1,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            title: function(context) {
              const index = context[0].dataIndex;
              return times[index].migration;
            },
            label: function(context) {
              return `${context.parsed.y}ms`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return value + 'ms';
            }
          }
        }
      }
    }
  });
}

// Initialize charts on mount
onMounted(async () => {
  await nextTick();
  initRiskChart();
  initExecutionChart();
});

// Watch for data changes and update charts
watch([riskDistribution, hasRiskData], async () => {
  await nextTick();
  initRiskChart();
}, { deep: true });

watch([executionTimes, hasExecutionData], async () => {
  await nextTick();
  initExecutionChart();
}, { deep: true });
</script>
