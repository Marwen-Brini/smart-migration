<template>
  <div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 flex-shrink-0">
      <div class="h-full flex flex-col">
        <!-- Logo/Brand -->
        <div class="px-6 py-6 border-b border-gray-200">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center text-white text-xl font-bold">
              SM
            </div>
            <div>
              <h1 class="text-lg font-bold text-gray-900">Smart Migration</h1>
              <p class="text-xs text-gray-500">Dashboard v2.0</p>
            </div>
          </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          <button
            v-for="item in navItems"
            :key="item.id"
            @click="activeView = item.id"
            :class="[
              'w-full flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              activeView === item.id
                ? 'bg-blue-50 text-blue-700'
                : 'text-gray-700 hover:bg-gray-100'
            ]"
          >
            <span class="text-lg">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
            <span
              v-if="item.badge"
              :class="[
                'ml-auto px-2 py-0.5 text-xs font-semibold rounded-full',
                item.badgeClass || 'bg-gray-200 text-gray-700'
              ]"
            >
              {{ item.badge }}
            </span>
          </button>
        </nav>

        <!-- Quick Actions -->
        <div class="px-3 py-4 border-t border-gray-200 space-y-2">
          <button
            @click="$emit('create-snapshot')"
            class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
          >
            <span>📸</span>
            <span>Create Snapshot</span>
          </button>
          <button
            @click="$emit('refresh')"
            :disabled="isRefreshing"
            class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium disabled:opacity-50"
          >
            <span>🔄</span>
            <span>{{ isRefreshing ? 'Refreshing...' : 'Refresh' }}</span>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <header class="bg-white border-b border-gray-200 px-8 py-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ currentView.title }}</h2>
            <p class="text-sm text-gray-600 mt-1">{{ currentView.description }}</p>
          </div>
          <div class="flex items-center space-x-3">
            <!-- Export Menu -->
            <div class="relative">
              <button
                @click="showExportMenu = !showExportMenu"
                class="flex items-center space-x-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium"
              >
                <span>📥</span>
                <span>Export</span>
              </button>
              <div
                v-if="showExportMenu"
                class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
              >
                <div class="py-1">
                  <button
                    @click="$emit('export', 'html')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-2"
                  >
                    <span>📄</span>
                    <span>Full HTML Report</span>
                  </button>
                  <button
                    @click="$emit('export', 'csv')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-2"
                  >
                    <span>📊</span>
                    <span>Migrations (CSV)</span>
                  </button>
                  <button
                    @click="$emit('export', 'schema')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-2"
                  >
                    <span>🗂️</span>
                    <span>Schema (JSON)</span>
                  </button>
                  <button
                    @click="$emit('export', 'metrics')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-2"
                  >
                    <span>📈</span>
                    <span>Metrics (JSON)</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Environment Badge -->
            <div class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">
              {{ status?.environment || 'N/A' }}
            </div>
          </div>
        </div>
      </header>

      <!-- Content Area -->
      <div class="flex-1 overflow-y-auto">
        <slot :active-view="activeView"></slot>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  status: {
    type: Object,
    default: () => ({}),
  },
  isRefreshing: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['create-snapshot', 'refresh', 'export']);

const activeView = ref('overview');
const showExportMenu = ref(false);

const navItems = computed(() => [
  {
    id: 'overview',
    label: 'Overview',
    icon: '📊',
    badge: null,
  },
  {
    id: 'migrations',
    label: 'Migrations',
    icon: '🗂️',
    badge: props.status?.pending_count || null,
    badgeClass: props.status?.pending_count > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-200 text-gray-700',
  },
  {
    id: 'schema',
    label: 'Schema',
    icon: '🏗️',
    badge: props.status?.table_count || null,
  },
  {
    id: 'drift',
    label: 'Schema Drift',
    icon: '🔍',
    badge: props.status?.drift_detected ? '!' : null,
    badgeClass: 'bg-red-100 text-red-800',
  },
  {
    id: 'snapshots',
    label: 'Snapshots',
    icon: '📸',
    badge: null,
  },
  {
    id: 'metrics',
    label: 'Metrics',
    icon: '📈',
    badge: null,
  },
  {
    id: 'history',
    label: 'History',
    icon: '📜',
    badge: null,
  },
  {
    id: 'performance',
    label: 'Performance',
    icon: '⚡',
    badge: null,
  },
  {
    id: 'conflicts',
    label: 'Conflicts',
    icon: '⚠️',
    badge: null,
  },
]);

const viewDetails = {
  overview: {
    title: 'Overview',
    description: 'Dashboard overview and quick statistics',
  },
  migrations: {
    title: 'Migrations',
    description: 'Manage and execute database migrations',
  },
  schema: {
    title: 'Database Schema',
    description: 'Explore your database structure',
  },
  drift: {
    title: 'Schema Drift',
    description: 'Detect and fix schema drift',
  },
  snapshots: {
    title: 'Snapshots',
    description: 'Manage schema snapshots',
  },
  metrics: {
    title: 'Performance Metrics',
    description: 'View migration and database metrics',
  },
  history: {
    title: 'Migration History',
    description: 'View past migration executions',
  },
  performance: {
    title: 'Performance Monitoring',
    description: 'Track migration performance metrics and anomalies',
  },
  conflicts: {
    title: 'Migration Conflicts',
    description: 'Detect and resolve migration conflicts',
  },
};

const currentView = computed(() => viewDetails[activeView.value] || viewDetails.overview);

defineExpose({
  activeView,
});
</script>
