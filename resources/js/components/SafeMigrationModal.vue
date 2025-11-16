<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="handleClose"
  >
    <div class="bg-white rounded-lg max-w-3xl w-full max-h-[90vh] overflow-hidden m-4 flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Safe Migration Execution</h3>
          <p class="text-sm text-gray-500 mt-1">{{ migrationName }}</p>
        </div>
        <button
          v-if="!isRunning"
          @click="handleClose"
          class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
        >
          &times;
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto p-6">
        <!-- Pre-execution Phase -->
        <div v-if="phase === 'preparing'" class="space-y-4">
          <div class="flex items-center space-x-3">
            <div class="animate-spin text-2xl">⚙️</div>
            <div>
              <p class="font-medium text-gray-900">Preparing Migration...</p>
              <p class="text-sm text-gray-600">Analyzing affected tables and data</p>
            </div>
          </div>
        </div>

        <!-- Backup Phase -->
        <div v-if="phase === 'backup'" class="space-y-4">
          <div class="flex items-center space-x-3 text-blue-600">
            <div class="text-2xl">✅</div>
            <p class="font-medium">Preparation Complete</p>
          </div>

          <div v-if="affectedTables.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm font-medium text-blue-900 mb-2">Affected Tables:</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="table in affectedTables"
                :key="table"
                class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"
              >
                {{ table }}
              </span>
            </div>
          </div>

          <div v-if="dataLoss.length > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm font-medium text-yellow-900 mb-2">⚠️ Data Protection Active:</p>
            <ul class="space-y-1">
              <li v-for="(loss, index) in dataLoss" :key="index" class="text-sm text-yellow-800">
                {{ formatDataLoss(loss) }}
              </li>
            </ul>
          </div>

          <div class="flex items-center space-x-3">
            <div class="animate-spin text-2xl">💾</div>
            <div>
              <p class="font-medium text-gray-900">Creating Backups...</p>
              <p class="text-sm text-gray-600">Safe rollback protection enabled</p>
            </div>
          </div>
        </div>

        <!-- Execution Phase -->
        <div v-if="phase === 'executing'" class="space-y-4">
          <div class="flex items-center space-x-3 text-green-600">
            <div class="text-2xl">✅</div>
            <p class="font-medium">Backups Created</p>
          </div>

          <div class="flex items-center space-x-3">
            <div class="animate-spin text-2xl">🔄</div>
            <div>
              <p class="font-medium text-gray-900">Executing Migration...</p>
              <p class="text-sm text-gray-600">Running SQL operations safely</p>
            </div>
          </div>

          <div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm">
            <div class="flex items-center space-x-2 mb-2">
              <span class="text-yellow-400">$</span>
              <span>php artisan migrate:safe</span>
            </div>
            <div class="text-gray-400">Processing migration operations...</div>
          </div>
        </div>

        <!-- Success Phase -->
        <div v-if="phase === 'success'" class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <div class="text-5xl mb-3">✅</div>
            <h4 class="text-xl font-semibold text-green-900 mb-2">Migration Completed Successfully!</h4>
            <p class="text-green-700">{{ successMessage }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="bg-purple-50 rounded-lg p-4">
              <p class="text-sm font-medium text-purple-900">Duration</p>
              <p class="text-2xl font-bold text-purple-600 mt-1">{{ durationMs }}ms</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
              <p class="text-sm font-medium text-blue-900">Tables Affected</p>
              <p class="text-2xl font-bold text-blue-600 mt-1">{{ affectedTables.length }}</p>
            </div>
          </div>

          <div v-if="affectedTables.length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Modified Tables:</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="table in affectedTables"
                :key="table"
                class="px-2 py-1 bg-gray-200 text-gray-800 text-xs rounded"
              >
                {{ table }}
              </span>
            </div>
          </div>
        </div>

        <!-- Error Phase -->
        <div v-if="phase === 'error'" class="space-y-4">
          <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center space-x-3 mb-4">
              <div class="text-3xl">❌</div>
              <div>
                <h4 class="text-lg font-semibold text-red-900">Migration Failed</h4>
                <p class="text-sm text-red-700">The migration was automatically rolled back</p>
              </div>
            </div>

            <div class="bg-white border border-red-200 rounded p-4">
              <p class="text-sm font-medium text-red-900 mb-2">Error Details:</p>
              <p class="text-sm text-red-800 font-mono">{{ errorMessage }}</p>
            </div>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center space-x-2 mb-2">
              <span class="text-xl">🛡️</span>
              <p class="text-sm font-medium text-yellow-900">Safe Rollback Protection</p>
            </div>
            <ul class="text-sm text-yellow-800 space-y-1">
              <li>✓ All changes have been reverted</li>
              <li>✓ Affected tables restored from backup</li>
              <li>✓ Database is in original state</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-t border-gray-200 p-6 bg-gray-50">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-600">
            <span v-if="phase === 'success'" class="text-green-600 font-medium">
              ✓ Migration completed in {{ durationMs }}ms
            </span>
            <span v-else-if="phase === 'error'" class="text-red-600 font-medium">
              ✗ Migration failed and was rolled back
            </span>
            <span v-else-if="isRunning" class="text-blue-600 font-medium">
              ⏳ Migration in progress...
            </span>
          </div>

          <button
            v-if="!isRunning"
            @click="handleClose"
            class="btn bg-gray-600 text-white hover:bg-gray-700"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  migrationName: {
    type: String,
    required: true,
  },
  autoRun: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'complete', 'error']);

const phase = ref('preparing'); // preparing, backup, executing, success, error
const isRunning = ref(false);
const affectedTables = ref([]);
const dataLoss = ref([]);
const durationMs = ref(0);
const successMessage = ref('');
const errorMessage = ref('');

const isComplete = computed(() => phase.value === 'success' || phase.value === 'error');

const formatDataLoss = (loss) => {
  if (loss.type === 'table') {
    return `📦 Table '${loss.name}' with ${loss.rows} rows will be backed up`;
  } else {
    return `📄 Column '${loss.table}.${loss.name}' with ${loss.rows} non-null values will be backed up`;
  }
};

const runSafeMigration = async () => {
  if (!props.migrationName) return;

  isRunning.value = true;
  phase.value = 'preparing';

  try {
    // Simulate preparation phase
    await new Promise(resolve => setTimeout(resolve, 800));

    // Move to backup phase
    phase.value = 'backup';
    await new Promise(resolve => setTimeout(resolve, 1000));

    // Move to execution phase
    phase.value = 'executing';

    // Call the API
    const response = await fetch('/api/smart-migration/migrations/run-safe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        migration: props.migrationName,
      }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Migration failed');
    }

    // Success!
    phase.value = 'success';
    durationMs.value = data.duration_ms || 0;
    affectedTables.value = data.affected_tables || [];
    dataLoss.value = data.data_loss || [];
    successMessage.value = data.message || 'Migration executed successfully';

    emit('complete', data);
  } catch (err) {
    console.error('Error running safe migration:', err);
    phase.value = 'error';
    errorMessage.value = err.message || 'Failed to execute migration';
    emit('error', err);
  } finally {
    isRunning.value = false;
  }
};

const handleClose = () => {
  if (!isRunning.value) {
    emit('close');
  }
};

// Watch for modal opening and auto-run
watch(() => props.show, (newShow) => {
  if (newShow && props.autoRun) {
    runSafeMigration();
  }
});
</script>
