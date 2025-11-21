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
          <h3 class="text-xl font-semibold text-gray-900">Safe Rollback</h3>
          <p class="text-sm text-gray-500 mt-1">{{ migrationName || 'Last migration batch' }}</p>
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
        <!-- Confirmation Phase -->
        <div v-if="phase === 'confirm'" class="space-y-4">
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
              <div class="text-2xl">⚠️</div>
              <div class="flex-1">
                <h4 class="font-medium text-yellow-900 mb-2">Safe Rollback Protection</h4>
                <p class="text-sm text-yellow-800 mb-3">
                  This rollback will <strong>archive</strong> instead of <strong>drop</strong>:
                </p>
                <ul class="text-sm text-yellow-800 space-y-1">
                  <li>✓ Tables will be renamed with timestamp suffix</li>
                  <li>✓ Columns will be preserved in archive tables</li>
                  <li>✓ All data can be recovered if needed</li>
                  <li>✓ No permanent data loss</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-900 font-medium mb-2">What will be rolled back?</p>
            <p class="text-sm text-blue-800">
              {{ migrationName ? `The migration "${formatMigrationName(migrationName)}" will be undone.` : 'The last batch of migrations will be undone.' }}
            </p>
          </div>

          <div class="flex justify-end space-x-3 pt-4">
            <button @click="handleClose" class="btn btn-secondary">
              Cancel
            </button>
            <button
              @click="confirmRollback"
              class="btn bg-orange-600 text-white hover:bg-orange-700 border-orange-600"
            >
              ↩️ Confirm Rollback
            </button>
          </div>
        </div>

        <!-- Processing Phase -->
        <div v-if="phase === 'processing'" class="space-y-4">
          <div class="flex items-center space-x-3">
            <div class="animate-spin text-2xl">🔄</div>
            <div>
              <p class="font-medium text-gray-900">Rolling Back Migration...</p>
              <p class="text-sm text-gray-600">Archiving data safely</p>
            </div>
          </div>

          <div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm">
            <div class="flex items-center space-x-2 mb-2">
              <span class="text-yellow-400">$</span>
              <span>php artisan migrate:undo --safe</span>
            </div>
            <div class="text-gray-400">Performing safe rollback with data preservation...</div>
          </div>
        </div>

        <!-- Success Phase -->
        <div v-if="phase === 'success'" class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <div class="text-5xl mb-3">✅</div>
            <h4 class="text-xl font-semibold text-green-900 mb-2">Rollback Completed Successfully!</h4>
            <p class="text-green-700">{{ successMessage }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="bg-purple-50 rounded-lg p-4">
              <p class="text-sm font-medium text-purple-900">Duration</p>
              <p class="text-2xl font-bold text-purple-600 mt-1">{{ durationMs }}ms</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
              <p class="text-sm font-medium text-blue-900">Migrations Rolled Back</p>
              <p class="text-2xl font-bold text-blue-600 mt-1">{{ rolledBackCount }}</p>
            </div>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center space-x-2 mb-2">
              <span class="text-xl">📦</span>
              <p class="text-sm font-medium text-yellow-900">Data Preservation</p>
            </div>
            <ul class="text-sm text-yellow-800 space-y-1">
              <li>✓ All data has been archived with timestamp suffix</li>
              <li>✓ Archived tables can be found in your database</li>
              <li>✓ Data can be restored if needed</li>
            </ul>
          </div>

          <div v-if="rolledBack.length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Rolled Back Migrations:</p>
            <div class="space-y-1">
              <div
                v-for="migration in rolledBack"
                :key="migration"
                class="text-sm text-gray-600 font-mono"
              >
                ↩️ {{ migration }}
              </div>
            </div>
          </div>
        </div>

        <!-- Error Phase -->
        <div v-if="phase === 'error'" class="space-y-4">
          <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center space-x-3 mb-4">
              <div class="text-3xl">❌</div>
              <div>
                <h4 class="text-lg font-semibold text-red-900">Rollback Failed</h4>
                <p class="text-sm text-red-700">An error occurred during rollback</p>
              </div>
            </div>

            <div class="bg-white border border-red-200 rounded p-4">
              <p class="text-sm font-medium text-red-900 mb-2">Error Details:</p>
              <p class="text-sm text-red-800 font-mono">{{ errorMessage }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-t border-gray-200 p-6 bg-gray-50">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-600">
            <span v-if="phase === 'success'" class="text-green-600 font-medium">
              ✓ Rollback completed in {{ durationMs }}ms
            </span>
            <span v-else-if="phase === 'error'" class="text-red-600 font-medium">
              ✗ Rollback failed
            </span>
            <span v-else-if="phase === 'processing'" class="text-blue-600 font-medium">
              ⏳ Rolling back...
            </span>
          </div>

          <button
            v-if="!isRunning && phase !== 'confirm'"
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
import { ref, computed } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  migrationName: {
    type: String,
    default: null,
  },
});

const emit = defineEmits(['close', 'complete', 'error']);

const phase = ref('confirm'); // confirm, processing, success, error
const isRunning = ref(false);
const durationMs = ref(0);
const successMessage = ref('');
const errorMessage = ref('');
const rolledBack = ref([]);

const rolledBackCount = computed(() => {
  if (props.migrationName) return 1;
  return rolledBack.value.length;
});

const formatMigrationName = (name) => {
  return name.replace(/^\d{4}_\d{2}_\d{2}_\d{6}_/, '').replace(/_/g, ' ');
};

const confirmRollback = async () => {
  phase.value = 'processing';
  isRunning.value = true;

  try {
    const requestBody = props.migrationName
      ? { migration: props.migrationName }
      : { step: 1 };

    const response = await fetch('/api/smart-migration/migrations/undo-safe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(requestBody),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Rollback failed');
    }

    // Success!
    phase.value = 'success';
    durationMs.value = data.duration_ms || 0;
    successMessage.value = data.message || 'Rollback completed successfully';
    rolledBack.value = data.rolled_back || (props.migrationName ? [props.migrationName] : []);

    emit('complete', data);
  } catch (err) {
    console.error('Error performing safe rollback:', err);
    phase.value = 'error';
    errorMessage.value = err.message || 'Failed to rollback migration';
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
</script>
