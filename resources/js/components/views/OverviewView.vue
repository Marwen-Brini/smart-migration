<template>
  <div class="p-8 space-y-6">
    <!-- Status Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <!-- Pending Migrations Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Pending Migrations</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ status?.pending_count || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
            <span class="text-2xl">⏳</span>
          </div>
        </div>
        <div class="mt-4">
          <span
            :class="[
              'text-xs font-semibold px-2 py-1 rounded-full',
              status?.pending_count > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'
            ]"
          >
            {{ status?.pending_count > 0 ? 'Action Required' : 'Up to Date' }}
          </span>
        </div>
      </div>

      <!-- Applied Migrations Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Applied Migrations</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ status?.applied_count || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <span class="text-2xl">✅</span>
          </div>
        </div>
        <div class="mt-4">
          <span class="text-xs text-gray-500">Total executed</span>
        </div>
      </div>

      <!-- Database Tables Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Database Tables</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ status?.table_count || 0 }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <span class="text-2xl">📊</span>
          </div>
        </div>
        <div class="mt-4">
          <span class="text-xs text-gray-500">{{ status?.database_driver || 'N/A' }}</span>
        </div>
      </div>

      <!-- Schema Drift Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Schema Drift</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">
              {{ status?.drift_detected ? 'Detected' : 'None' }}
            </p>
          </div>
          <div
            :class="[
              'w-12 h-12 rounded-lg flex items-center justify-center',
              status?.drift_detected ? 'bg-red-100' : 'bg-green-100'
            ]"
          >
            <span class="text-2xl">{{ status?.drift_detected ? '⚠️' : '✓' }}</span>
          </div>
        </div>
        <div class="mt-4">
          <span
            :class="[
              'text-xs font-semibold px-2 py-1 rounded-full',
              status?.drift_detected ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
            ]"
          >
            {{ status?.drift_detected ? 'Fix Required' : 'In Sync' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Drift Alert -->
    <drift-alert v-if="status?.drift_detected" :drift="drift" @refresh="$emit('refresh')" />

    <!-- Two Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent Migrations -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Recent Migrations</h3>
          <button
            @click="$emit('navigate', 'migrations')"
            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
          >
            View All →
          </button>
        </div>
        <div class="space-y-3">
          <div
            v-for="migration in recentMigrations"
            :key="migration.name"
            class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ migration.name }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ migration.applied_at || 'Pending' }}</p>
            </div>
            <div class="ml-4">
              <span
                :class="[
                  'text-xs font-semibold px-2 py-1 rounded-full',
                  migration.status === 'applied' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                ]"
              >
                {{ migration.status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-4">
          <button
            v-if="status?.pending_count > 0"
            @click="$emit('run-migrations')"
            class="flex flex-col items-center justify-center p-4 border-2 border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
          >
            <span class="text-2xl mb-2">▶️</span>
            <span class="text-sm font-medium text-gray-900">Run Pending</span>
            <span class="text-xs text-gray-500 mt-1">{{ status.pending_count }} migrations</span>
          </button>

          <button
            @click="$emit('create-snapshot')"
            class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <span class="text-2xl mb-2">📸</span>
            <span class="text-sm font-medium text-gray-900">Take Snapshot</span>
            <span class="text-xs text-gray-500 mt-1">Save current state</span>
          </button>

          <button
            v-if="status?.drift_detected"
            @click="$emit('fix-drift')"
            class="flex flex-col items-center justify-center p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 transition-colors"
          >
            <span class="text-2xl mb-2">🔧</span>
            <span class="text-sm font-medium text-gray-900">Fix Drift</span>
            <span class="text-xs text-gray-500 mt-1">Generate fix</span>
          </button>

          <button
            @click="$emit('navigate', 'schema')"
            class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <span class="text-2xl mb-2">🏗️</span>
            <span class="text-sm font-medium text-gray-900">View Schema</span>
            <span class="text-xs text-gray-500 mt-1">Explore tables</span>
          </button>
        </div>
      </div>
    </div>

    <!-- System Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
            <span class="text-xl">🗄️</span>
          </div>
          <div>
            <p class="text-sm opacity-90">Database</p>
            <p class="text-lg font-semibold">{{ status?.database_driver || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
            <span class="text-xl">🚀</span>
          </div>
          <div>
            <p class="text-sm opacity-90">Environment</p>
            <p class="text-lg font-semibold">{{ status?.environment || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
            <span class="text-xl">📦</span>
          </div>
          <div>
            <p class="text-sm opacity-90">Laravel</p>
            <p class="text-lg font-semibold">{{ status?.laravel_version || 'N/A' }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DriftAlert from '../DriftAlert.vue';

const props = defineProps({
  status: {
    type: Object,
    default: () => ({}),
  },
  migrations: {
    type: Array,
    default: () => [],
  },
  drift: {
    type: Object,
    default: () => ({}),
  },
});

defineEmits(['refresh', 'navigate', 'run-migrations', 'create-snapshot', 'fix-drift']);

const recentMigrations = computed(() => {
  return props.migrations.slice(0, 5);
});
</script>
