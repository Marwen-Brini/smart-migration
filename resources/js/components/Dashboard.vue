<template>
  <dashboard-layout
    :status="status"
    :is-refreshing="isRefreshing"
    @create-snapshot="handleCreateSnapshot"
    @refresh="refreshAll"
    @export="handleExport"
    ref="layoutRef"
  >
    <template #default="{ activeView }">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-20">
        <div class="text-center">
          <div class="text-5xl mb-4">⏳</div>
          <p class="text-gray-600">Loading dashboard...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="hasError" class="p-8">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
          <div class="flex items-center space-x-3">
            <div class="text-2xl">❌</div>
            <div>
              <h3 class="text-lg font-semibold text-red-900">Error Loading Dashboard</h3>
              <p class="text-red-700">{{ errorMessage }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Dashboard Views -->
      <div v-else>
        <!-- Overview View -->
        <overview-view
          v-if="activeView === 'overview'"
          :status="status"
          :migrations="migrations"
          :drift="driftData"
          @refresh="refreshAll"
          @navigate="navigateToView"
          @run-migrations="handleRunMigrations"
          @create-snapshot="handleCreateSnapshot"
          @fix-drift="handleFixDrift"
        />

        <!-- Migrations View -->
        <div v-else-if="activeView === 'migrations'" class="p-8">
          <migration-status :migrations="migrations" @refresh="refreshAll" />
        </div>

        <!-- Schema View -->
        <div v-else-if="activeView === 'schema'" class="p-8">
          <schema-explorer :schema="schemaData" />
        </div>

        <!-- Drift View -->
        <div v-else-if="activeView === 'drift'" class="p-8">
          <drift-alert v-if="status?.drift_detected" :drift="driftData" @refresh="refreshAll" />
          <div v-else class="bg-green-50 border border-green-200 rounded-lg p-8 text-center">
            <div class="text-5xl mb-4">✅</div>
            <h3 class="text-xl font-semibold text-green-900 mb-2">No Schema Drift Detected</h3>
            <p class="text-green-700">Your database schema is in sync with migrations.</p>
          </div>
        </div>

        <!-- Snapshots View -->
        <div v-else-if="activeView === 'snapshots'" class="p-8">
          <snapshot-list :snapshots="snapshotsData" @refresh="refreshAll" />
        </div>

        <!-- Metrics View -->
        <div v-else-if="activeView === 'metrics'" class="p-8">
          <metrics-panel :metrics="metricsData" />
        </div>

        <!-- History View -->
        <div v-else-if="activeView === 'history'" class="p-8">
          <migration-timeline :history="history" />
        </div>

        <!-- Performance View -->
        <div v-else-if="activeView === 'performance'" class="p-8">
          <performance-view />
        </div>

        <!-- Conflicts View -->
        <div v-else-if="activeView === 'conflicts'" class="p-8">
          <conflicts-view />
        </div>
      </div>
    </template>
  </dashboard-layout>

  <!-- Toast Notifications -->
  <toast />
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useStatus } from '../composables/useStatus';
import { useMigrations, useMigrationHistory } from '../composables/useMigrations';
import { useSchema, useDrift } from '../composables/useSchema';
import { useMetrics } from '../composables/useMetrics';
import { useSnapshots } from '../composables/useSnapshots';
import { useToast } from '../composables/useToast';
import { downloadJSON, downloadCSV, downloadHTMLReport } from '../utils/exporters';
import api from '../utils/api';
import DashboardLayout from './DashboardLayout.vue';
import OverviewView from './views/OverviewView.vue';
import PerformanceView from './views/PerformanceView.vue';
import ConflictsView from './views/ConflictsView.vue';
import MigrationStatus from './MigrationStatus.vue';
import MigrationTimeline from './MigrationTimeline.vue';
import SchemaExplorer from './SchemaExplorer.vue';
import MetricsPanel from './MetricsPanel.vue';
import DriftAlert from './DriftAlert.vue';
import Toast from './Toast.vue';
import SnapshotList from './SnapshotList.vue';

// Composables
const { status, loading: statusLoading, error: statusError, fetchStatus } = useStatus();
const { migrations, loading: migrationsLoading, error: migrationsError, fetchMigrations } = useMigrations();
const { history, loading: historyLoading, error: historyError, fetchHistory } = useMigrationHistory();
const { schema: schemaData, loading: schemaLoading, error: schemaError, fetchSchema } = useSchema();
const { drift: driftData, loading: driftLoading, error: driftError, fetchDrift } = useDrift();
const { metrics: metricsData, loading: metricsLoading, error: metricsError, fetchMetrics } = useMetrics();
const { snapshots: snapshotsData, loading: snapshotsLoading, error: snapshotsError, fetchSnapshots } = useSnapshots();

// State
const isRefreshing = ref(false);
const isCreatingSnapshot = ref(false);
const layoutRef = ref(null);
const { success, error } = useToast();

// Computed
const isLoading = computed(() => {
  return statusLoading.value || migrationsLoading.value || historyLoading.value ||
         schemaLoading.value || driftLoading.value || metricsLoading.value ||
         snapshotsLoading.value;
});

const hasError = computed(() => {
  return statusError.value || migrationsError.value || historyError.value ||
         schemaError.value || driftError.value || metricsError.value ||
         snapshotsError.value;
});

const errorMessage = computed(() => {
  return statusError.value || migrationsError.value || historyError.value ||
         schemaError.value || driftError.value || metricsError.value ||
         snapshotsError.value || 'Unknown error occurred';
});

// Methods
async function loadAll() {
  await Promise.all([
    fetchStatus(),
    fetchMigrations(),
    fetchHistory(),
    fetchSchema(),
    fetchDrift(),
    fetchMetrics(),
    fetchSnapshots(),
  ]);
}

async function refreshAll() {
  isRefreshing.value = true;
  await loadAll();
  isRefreshing.value = false;
}

async function handleCreateSnapshot() {
  if (isCreatingSnapshot.value) return;

  try {
    isCreatingSnapshot.value = true;
    const result = await api.createSnapshot();

    if (result.success) {
      success(result.message || 'Snapshot created successfully!');
      await refreshAll();
    } else {
      error(result.message || 'Failed to create snapshot');
    }
  } catch (err) {
    console.error('Error creating snapshot:', err);
    error(err.response?.data?.message || 'Failed to create snapshot');
  } finally {
    isCreatingSnapshot.value = false;
  }
}

// Navigation
function navigateToView(viewId) {
  if (layoutRef.value) {
    layoutRef.value.activeView = viewId;
  }
}

// Quick Actions
async function handleRunMigrations() {
  const pendingCount = migrations.value.filter(m => m.status === 'pending').length;
  if (!confirm(`Run ${pendingCount} pending migration(s)?`)) return;

  try {
    const result = await api.runMigrations();
    if (result.success) {
      success(result.message || 'Migrations executed successfully!');
      await refreshAll();
    } else {
      error(result.message || 'Failed to run migrations');
    }
  } catch (err) {
    console.error('Error running migrations:', err);
    error(err.response?.data?.message || 'Failed to run migrations');
  }
}

async function handleFixDrift() {
  if (!confirm('Generate a fix migration for detected drift?')) return;

  try {
    const result = await api.generateFixMigration();
    if (result.success) {
      success(result.message || 'Fix migration generated successfully!');
      await refreshAll();
    } else {
      error(result.message || 'Failed to generate fix migration');
    }
  } catch (err) {
    console.error('Error generating fix migration:', err);
    error(err.response?.data?.message || 'Failed to generate fix migration');
  }
}

// Export functions
function handleExport(type) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);

  switch (type) {
    case 'html':
      exportFullReport();
      break;
    case 'csv':
      exportMigrationsCSV();
      break;
    case 'schema':
      exportSchemaJSON();
      break;
    case 'metrics':
      exportMetricsJSON();
      break;
  }
}

function exportFullReport() {
  const reportData = {
    status: status.value,
    migrations: migrations.value,
    schema: schemaData.value,
    metrics: metricsData.value,
    history: history.value,
  };
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);
  downloadHTMLReport(reportData, `smart-migration-report-${timestamp}.html`);
  success('Report downloaded successfully!');
}

function exportMigrationsCSV() {
  if (!migrations.value || migrations.value.length === 0) {
    error('No migrations data to export');
    return;
  }
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);
  const csvData = migrations.value.map(m => ({
    name: m.name,
    status: m.status,
    risk: m.risk,
    applied_at: m.applied_at || '',
    estimated_time: m.estimated_time || '',
  }));
  downloadCSV(csvData, `migrations-${timestamp}.csv`);
  success('Migrations exported to CSV!');
}

function exportSchemaJSON() {
  if (!schemaData.value || !schemaData.value.tables) {
    error('No schema data to export');
    return;
  }
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);
  downloadJSON(schemaData.value, `schema-${timestamp}.json`);
  success('Schema exported to JSON!');
}

function exportMetricsJSON() {
  if (!metricsData.value) {
    error('No metrics data to export');
    return;
  }
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);
  downloadJSON(metricsData.value, `metrics-${timestamp}.json`);
  success('Metrics exported to JSON!');
}

// Lifecycle
onMounted(() => {
  loadAll();

  // Auto-refresh every 30 seconds
  setInterval(() => {
    loadAll();
  }, 30000);
});
</script>
