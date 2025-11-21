<template>
  <div v-if="isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="close">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <span class="text-3xl">🧪</span>
            <div>
              <h3 class="text-lg font-semibold text-white">Test Migration</h3>
              <p class="text-sm text-purple-100">{{ migrationName }}</p>
            </div>
          </div>
          <button @click="close" class="text-white hover:text-purple-200 transition-colors">
            <span class="text-2xl">×</span>
          </button>
        </div>
      </div>

      <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
        <!-- Configuration Phase -->
        <div v-if="phase === 'config'">
          <div class="space-y-4">
            <p class="text-gray-700">
              Test this migration on a temporary database before running it in production.
            </p>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 class="font-medium text-blue-900 mb-2">Test Options</h4>
              <div class="space-y-3">
                <label class="flex items-center space-x-2">
                  <input type="checkbox" v-model="testRollback" class="rounded border-gray-300">
                  <span class="text-sm text-blue-800">Test rollback (down) migration</span>
                </label>
                <label class="flex items-center space-x-2">
                  <input type="checkbox" v-model="withData" class="rounded border-gray-300">
                  <span class="text-sm text-blue-800">Seed with test data</span>
                </label>
              </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <h4 class="font-medium text-yellow-900 mb-2">What will be tested:</h4>
              <ul class="text-sm text-yellow-800 space-y-1">
                <li>✓ Migration up() method execution</li>
                <li v-if="testRollback">✓ Migration down() method execution</li>
                <li>✓ Database schema changes</li>
                <li>✓ Integrity checks</li>
                <li>✓ Execution time measurement</li>
              </ul>
            </div>

            <div class="flex justify-end space-x-3">
              <button @click="close" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                Cancel
              </button>
              <button @click="runTest" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                Run Test
              </button>
            </div>
          </div>
        </div>

        <!-- Testing Phase -->
        <div v-else-if="phase === 'testing'" class="text-center py-8">
          <div class="text-6xl mb-4 animate-spin">🧪</div>
          <h4 class="text-xl font-semibold text-gray-900 mb-2">Testing Migration...</h4>
          <p class="text-gray-600">Running on temporary database</p>
          <div class="mt-4 flex justify-center space-x-2">
            <div class="w-2 h-2 bg-purple-600 rounded-full animate-bounce"></div>
            <div class="w-2 h-2 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
            <div class="w-2 h-2 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
          </div>
        </div>

        <!-- Success Phase -->
        <div v-else-if="phase === 'success'" class="space-y-4">
          <div class="text-center py-4">
            <div class="text-6xl mb-4">✅</div>
            <h4 class="text-xl font-semibold text-green-900 mb-2">Test Passed!</h4>
            <p class="text-green-700">Migration is safe to run in production</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <div class="text-sm text-gray-600 mb-1">Execution Time</div>
              <div class="text-2xl font-bold text-gray-900">{{ result.duration_ms }}ms</div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <div class="text-sm text-gray-600 mb-1">Rollback Test</div>
              <div class="text-2xl font-bold text-gray-900">
                {{ result.tested_rollback ? '✓ Passed' : 'Skipped' }}
              </div>
            </div>
          </div>

          <div v-if="result.tables_added && result.tables_added.length > 0" class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h5 class="text-sm font-semibold text-green-900 mb-2">Tables Added:</h5>
            <div class="flex flex-wrap gap-2">
              <span v-for="table in result.tables_added" :key="table" class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                {{ table }}
              </span>
            </div>
          </div>

          <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <h5 class="text-sm font-semibold text-gray-700 mb-2">Test Output:</h5>
            <pre class="text-xs font-mono text-gray-600 whitespace-pre-wrap max-h-40 overflow-y-auto">{{ result.output }}</pre>
          </div>

          <div class="flex justify-end space-x-3">
            <button @click="close" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
              Close
            </button>
            <button @click="$emit('run-migration', migrationName)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
              Run in Production
            </button>
          </div>
        </div>

        <!-- Error Phase -->
        <div v-else-if="phase === 'error'" class="space-y-4">
          <div class="text-center py-4">
            <div class="text-6xl mb-4">❌</div>
            <h4 class="text-xl font-semibold text-red-900 mb-2">Test Failed</h4>
            <p class="text-red-700">{{ errorMessage }}</p>
          </div>

          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h5 class="text-sm font-semibold text-red-900 mb-2">Error Details:</h5>
            <pre class="text-xs font-mono text-red-700 whitespace-pre-wrap max-h-60 overflow-y-auto">{{ result.output || errorMessage }}</pre>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h5 class="text-sm font-semibold text-yellow-900 mb-2">⚠️ Recommendations:</h5>
            <ul class="text-sm text-yellow-800 space-y-1">
              <li>• Review the migration file for syntax errors</li>
              <li>• Check database connection settings</li>
              <li>• Verify all referenced tables/columns exist</li>
              <li>• Fix the issues before running in production</li>
            </ul>
          </div>

          <div class="flex justify-end">
            <button @click="close" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  migrationName: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['close', 'run-migration']);

const phase = ref('config');
const testRollback = ref(true);
const withData = ref(false);
const result = ref({});
const errorMessage = ref('');

const close = () => {
  phase.value = 'config';
  testRollback.value = true;
  withData.value = false;
  result.value = {};
  errorMessage.value = '';
  emit('close');
};

const runTest = async () => {
  phase.value = 'testing';

  try {
    const response = await fetch('/api/smart-migration/migrations/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        migration: props.migrationName,
        test_rollback: testRollback.value,
        with_data: withData.value,
      }),
    });

    const data = await response.json();

    if (data.success) {
      result.value = data;
      phase.value = 'success';
    } else {
      result.value = data;
      errorMessage.value = data.message || 'Test failed';
      phase.value = 'error';
    }
  } catch (err) {
    console.error('Error testing migration:', err);
    errorMessage.value = err.message || 'Failed to test migration';
    phase.value = 'error';
  }
};
</script>
