<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-900">Migration Status</h2>
      <div class="flex items-center space-x-2">
        <button
          v-if="hasPending"
          @click="handleRunAll"
          :disabled="isRunning"
          class="btn btn-primary text-sm"
        >
          <span v-if="isRunning">⏳ Running...</span>
          <span v-else>▶️ Run All ({{ pendingCount }})</span>
        </button>
        <div class="badge badge-warning" v-if="hasPending">
          {{ pendingCount }} Pending
        </div>
        <div class="badge badge-safe" v-else>
          All Up to Date
        </div>
      </div>
    </div>

    <!-- Pending Migrations -->
    <div v-if="hasPending" class="space-y-3">
      <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Pending</h3>
      <div v-for="migration in pending" :key="migration.name" class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h4 class="font-medium text-gray-900">{{ formatName(migration.name) }}</h4>
            <p class="text-sm text-gray-600 mt-1">{{ migration.name }}</p>
            <div class="flex items-center space-x-4 mt-3">
              <span :class="['badge', getRiskBadgeClass(migration.risk)]">
                {{ migration.risk }}
              </span>
              <span class="text-xs text-gray-500">
                Est. time: {{ migration.estimated_time || 'Unknown' }}
              </span>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <button
              @click="handlePreview(migration)"
              class="btn btn-secondary text-xs"
            >
              👁 Preview
            </button>
            <button
              @click="handleRunSingle(migration)"
              :disabled="isRunning"
              class="btn btn-primary text-xs"
            >
              <span v-if="isRunning">⏳</span>
              <span v-else>▶️ Run</span>
            </button>
            <div class="text-2xl">{{ getRiskIcon(migration.risk) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recently Applied -->
    <div v-if="recentlyApplied.length > 0" class="mt-6">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recently Applied</h3>
        <button
          @click="handleRollback"
          :disabled="isRollingBack"
          class="btn text-xs bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100"
        >
          <span v-if="isRollingBack">⏳ Rolling back...</span>
          <span v-else>↩️ Rollback Last</span>
        </button>
      </div>
      <div v-for="migration in recentlyApplied" :key="migration.name" class="border border-green-200 bg-green-50 rounded-lg p-4 mb-2">
        <div class="flex items-center justify-between">
          <div>
            <h4 class="font-medium text-gray-900">{{ formatName(migration.name) }}</h4>
            <p class="text-xs text-gray-600 mt-1">{{ formatRelativeTime(migration.applied_at) }}</p>
          </div>
          <div class="text-xl">✅</div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!hasPending && recentlyApplied.length === 0" class="text-center py-8">
      <div class="text-4xl mb-3">✅</div>
      <p class="text-gray-600">All migrations are up to date</p>
      <p class="text-sm text-gray-500 mt-1">No pending migrations found</p>
    </div>
  </div>

  <!-- Migration Preview Modal -->
  <migration-preview-modal
    :show="showPreviewModal"
    :migration-name="selectedMigration"
    @close="closePreview"
    @run-migration="handleRunFromPreview"
  />
</template>

<script setup>
import { ref, computed } from 'vue';
import { formatMigrationName, formatRelativeTime, getRiskIcon } from '../utils/formatters';
import { useToast } from '../composables/useToast';
import api from '../utils/api';
import MigrationPreviewModal from './MigrationPreviewModal.vue';

const props = defineProps({
  migrations: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['refresh']);

const { success, error, warning } = useToast();
const isRunning = ref(false);
const isRollingBack = ref(false);
const showPreviewModal = ref(false);
const selectedMigration = ref(null);

const pending = computed(() => {
  return props.migrations.filter(m => m.status === 'pending');
});

const applied = computed(() => {
  return props.migrations.filter(m => m.status === 'applied')
    .sort((a, b) => new Date(b.applied_at) - new Date(a.applied_at));
});

const recentlyApplied = computed(() => {
  return applied.value.slice(0, 3);
});

const hasPending = computed(() => pending.value.length > 0);
const pendingCount = computed(() => pending.value.length);

function formatName(name) {
  return formatMigrationName(name);
}

function getRiskBadgeClass(risk) {
  const classes = {
    safe: 'badge-safe',
    warning: 'badge-warning',
    danger: 'badge-danger',
  };
  return classes[risk?.toLowerCase()] || 'bg-gray-100 text-gray-800';
}

async function handleRunAll() {
  const hasDanger = pending.value.some(m => m.risk === 'danger');
  const hasWarning = pending.value.some(m => m.risk === 'warning');

  let message = `Run ${pendingCount.value} pending migration(s)?`;
  if (hasDanger) {
    message = `⚠️ WARNING: Some migrations contain DANGEROUS operations (drop/delete). ${message}`;
  } else if (hasWarning) {
    message = `⚠️ Some migrations contain operations that modify data. ${message}`;
  }

  if (!confirm(message)) return;

  try {
    isRunning.value = true;
    const result = await api.runMigrations({ force: true });

    if (result.success) {
      success(`Successfully ran ${pendingCount.value} migration(s)`);
      emit('refresh');
    } else {
      error(result.message || 'Failed to run migrations');
    }
  } catch (err) {
    console.error('Error running migrations:', err);
    error(err.response?.data?.message || 'Failed to run migrations');
  } finally {
    isRunning.value = false;
  }
}

async function handleRunSingle(migration) {
  const riskMessage = migration.risk === 'danger'
    ? '⚠️ WARNING: This migration contains DANGEROUS operations. '
    : migration.risk === 'warning'
    ? '⚠️ This migration modifies data. '
    : '';

  if (!confirm(`${riskMessage}Run migration "${formatName(migration.name)}"?`)) return;

  try {
    isRunning.value = true;
    const result = await api.runMigrations({
      path: migration.path,
      force: true,
    });

    if (result.success) {
      success(`Migration "${formatName(migration.name)}" completed successfully`);
      emit('refresh');
    } else {
      error(result.message || 'Failed to run migration');
    }
  } catch (err) {
    console.error('Error running migration:', err);
    error(err.response?.data?.message || 'Failed to run migration');
  } finally {
    isRunning.value = false;
  }
}

async function handleRollback() {
  if (!confirm('Rollback the last migration batch? This will use safe rollback (data preserved).')) return;

  try {
    isRollingBack.value = true;
    const result = await api.rollbackMigrations({ step: 1 });

    if (result.success) {
      success('Rollback completed successfully (data preserved in archives)');
      emit('refresh');
    } else {
      error(result.message || 'Failed to rollback migration');
    }
  } catch (err) {
    console.error('Error rolling back migration:', err);
    error(err.response?.data?.message || 'Failed to rollback migration');
  } finally {
    isRollingBack.value = false;
  }
}

function handlePreview(migration) {
  selectedMigration.value = migration.name;
  showPreviewModal.value = true;
}

function closePreview() {
  showPreviewModal.value = false;
  selectedMigration.value = null;
}

async function handleRunFromPreview(migrationName) {
  closePreview();

  const migration = pending.value.find(m => m.name === migrationName);
  if (migration) {
    await handleRunSingle(migration);
  }
}
</script>
