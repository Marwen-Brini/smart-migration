<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Performance Monitoring</h2>
        <p class="mt-1 text-sm text-gray-500">Track and analyze migration performance metrics</p>
      </div>
      <button
        @click="refreshData"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        :disabled="loading"
      >
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !report" class="text-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
      <p class="mt-4 text-gray-600">Loading performance data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <p class="text-red-800">{{ error }}</p>
    </div>

    <!-- No Data State -->
    <div v-else-if="!report || report.total_migrations_tracked === 0" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
      <svg class="mx-auto h-12 w-12 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      <h3 class="mt-4 text-lg font-medium text-gray-900">No Performance Data Yet</h3>
      <p class="mt-2 text-sm text-gray-600">
        Run migrations with <code class="px-2 py-1 bg-gray-100 rounded">php artisan migrate:safe</code> to start collecting performance metrics.
      </p>
    </div>

    <!-- Performance Data -->
    <div v-else class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Migrations Tracked</p>
              <p class="mt-2 text-3xl font-bold text-gray-900">{{ report.total_migrations_tracked }}</p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
              <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Runs</p>
              <p class="mt-2 text-3xl font-bold text-gray-900">{{ report.summary.total_runs }}</p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
              <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Avg Duration</p>
              <p class="mt-2 text-3xl font-bold text-gray-900">{{ formatDuration(report.summary.avg_duration_ms) }}</p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
              <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Generated</p>
              <p class="mt-2 text-sm font-medium text-gray-900">{{ formatDate(report.generated_at) }}</p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
              <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Slowest Migrations -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Slowest Migrations</h3>
        </div>
        <div class="p-6">
          <div v-if="report.summary.slowest_migrations && report.summary.slowest_migrations.length > 0" class="space-y-4">
            <div
              v-for="(migration, index) in report.summary.slowest_migrations"
              :key="migration.migration"
              class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
            >
              <div class="flex items-center space-x-4 flex-1">
                <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                  <span class="text-sm font-bold text-red-600">{{ index + 1 }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ migration.migration }}</p>
                  <p class="text-xs text-gray-500">{{ migration.total_runs }} runs</p>
                </div>
              </div>
              <div class="flex items-center space-x-6 text-sm">
                <div class="text-right">
                  <p class="font-medium text-gray-900">{{ formatDuration(migration.duration.avg) }}</p>
                  <p class="text-xs text-gray-500">avg</p>
                </div>
                <div class="text-right">
                  <p class="font-medium text-red-600">{{ formatDuration(migration.duration.max) }}</p>
                  <p class="text-xs text-gray-500">max</p>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- Memory Intensive Migrations -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Most Memory Intensive</h3>
        </div>
        <div class="p-6">
          <div v-if="report.summary.most_memory_intensive && report.summary.most_memory_intensive.length > 0" class="space-y-4">
            <div
              v-for="(migration, index) in report.summary.most_memory_intensive"
              :key="migration.migration"
              class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
            >
              <div class="flex items-center space-x-4 flex-1">
                <div class="flex-shrink-0 w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                  <span class="text-sm font-bold text-orange-600">{{ index + 1 }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ migration.migration }}</p>
                  <p class="text-xs text-gray-500">{{ migration.total_runs }} runs</p>
                </div>
              </div>
              <div class="flex items-center space-x-6 text-sm">
                <div class="text-right">
                  <p class="font-medium text-gray-900">{{ formatMemory(migration.memory.avg) }}</p>
                  <p class="text-xs text-gray-500">avg</p>
                </div>
                <div class="text-right">
                  <p class="font-medium text-orange-600">{{ formatMemory(migration.memory.max) }}</p>
                  <p class="text-xs text-gray-500">max</p>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- All Migrations Table -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">All Migrations Performance</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Migration</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Runs</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Duration</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Duration</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Memory</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Queries</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="migration in report.migrations" :key="migration.migration">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ migration.migration }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ migration.total_runs }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDuration(migration.duration.avg) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDuration(migration.duration.max) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatMemory(migration.memory.avg) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ Math.round(migration.queries.avg) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const report = ref(null);
const loading = ref(false);
const error = ref(null);

const fetchPerformanceData = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await fetch('/api/smart-migration/performance/report');
    if (!response.ok) {
      throw new Error('Failed to fetch performance data');
    }
    report.value = await response.json();
  } catch (err) {
    error.value = err.message;
    console.error('Error fetching performance data:', err);
  } finally {
    loading.value = false;
  }
};

const refreshData = () => {
  fetchPerformanceData();
};

const formatDuration = (ms) => {
  if (ms < 1000) {
    return `${ms.toFixed(2)}ms`;
  }
  return `${(ms / 1000).toFixed(2)}s`;
};

const formatMemory = (mb) => {
  return `${mb.toFixed(2)}MB`;
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString();
};

onMounted(() => {
  fetchPerformanceData();
});
</script>
