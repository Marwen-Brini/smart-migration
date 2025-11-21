<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg max-w-5xl w-full max-h-[90vh] overflow-hidden m-4 flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Migration Preview</h3>
          <p class="text-sm text-gray-500 mt-1">{{ migrationName }}</p>
        </div>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">
          &times;
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex-1 flex items-center justify-center py-20">
        <div class="text-center">
          <div class="text-5xl mb-4">⏳</div>
          <p class="text-gray-600">Analyzing migration...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex-1 p-6">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
          <div class="flex items-center space-x-3">
            <div class="text-2xl">❌</div>
            <div>
              <h4 class="text-lg font-semibold text-red-900">Error Loading Preview</h4>
              <p class="text-red-700">{{ error }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview Content -->
      <div v-else-if="analysis" class="flex-1 overflow-y-auto">
        <!-- Summary Cards -->
        <div class="p-6 border-b border-gray-200">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-green-50 rounded-lg p-4">
              <p class="text-sm font-medium text-green-900">Safe Operations</p>
              <p class="text-2xl font-bold text-green-600 mt-1">
                {{ analysis.summary?.safe || 0 }}
              </p>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4">
              <p class="text-sm font-medium text-yellow-900">Warnings</p>
              <p class="text-2xl font-bold text-yellow-600 mt-1">
                {{ analysis.summary?.warnings || 0 }}
              </p>
            </div>
            <div class="bg-red-50 rounded-lg p-4">
              <p class="text-sm font-medium text-red-900">Dangerous Operations</p>
              <p class="text-2xl font-bold text-red-600 mt-1">
                {{ analysis.summary?.dangerous || 0 }}
              </p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
              <p class="text-sm font-medium text-purple-900">Estimated Time</p>
              <p class="text-lg font-bold text-purple-600 mt-1">
                {{ analysis.estimated_time || 'Unknown' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Operations List -->
        <div class="p-6">
          <h4 class="text-lg font-semibold text-gray-900 mb-4">Operations</h4>

          <div v-if="!analysis.operations || analysis.operations.length === 0" class="text-center py-8 text-gray-500">
            No operations detected in this migration
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="(operation, index) in analysis.operations"
              :key="index"
              class="border rounded-lg overflow-hidden"
              :class="getOperationBorderClass(operation.risk)"
            >
              <!-- Operation Header -->
              <div class="p-4" :class="getOperationBgClass(operation.risk)">
                <div class="flex items-start justify-between">
                  <div class="flex items-start space-x-3 flex-1">
                    <span class="text-2xl">{{ getRiskIcon(operation.risk) }}</span>
                    <div class="flex-1">
                      <div class="flex items-center space-x-2">
                        <span
                          class="px-2 py-1 text-xs font-semibold rounded"
                          :class="getRiskBadgeClass(operation.risk)"
                        >
                          {{ getRiskLabel(operation.risk) }}
                        </span>
                        <h5 class="font-medium text-gray-900">{{ operation.description }}</h5>
                      </div>

                      <!-- SQL Statement -->
                      <div v-if="operation.sql" class="mt-3">
                        <p class="text-xs font-medium text-gray-500 mb-1">SQL Statement:</p>
                        <div class="bg-gray-900 text-green-400 p-3 rounded font-mono text-sm overflow-x-auto">
                          {{ operation.sql }}
                        </div>
                      </div>

                      <!-- Impact -->
                      <div v-if="operation.impact" class="mt-2 text-sm text-gray-600">
                        <span class="font-medium">Impact:</span> {{ operation.impact }}
                      </div>

                      <!-- Duration -->
                      <div v-if="operation.duration" class="mt-2 text-sm text-purple-600">
                        <span class="font-medium">Duration:</span> {{ operation.duration }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="border-t border-gray-200 p-6 flex items-center justify-between bg-gray-50">
        <div class="text-sm text-gray-600">
          <span v-if="analysis?.summary">
            Total: {{ getTotalOperations() }} operation(s)
          </span>
        </div>
        <div class="flex items-center space-x-3">
          <button
            @click="$emit('close')"
            class="btn btn-secondary"
          >
            Close
          </button>
          <button
            @click="$emit('run-migration', migrationName)"
            :disabled="!canRun"
            class="btn bg-blue-600 text-white hover:bg-blue-700 border-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Run Migration
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
});

const emit = defineEmits(['close', 'run-migration']);

const loading = ref(false);
const error = ref(null);
const analysis = ref(null);

const canRun = computed(() => {
  return analysis.value && !loading.value && !error.value;
});

const getTotalOperations = () => {
  if (!analysis.value?.summary) return 0;
  const summary = analysis.value.summary;
  return (summary.safe || 0) + (summary.warnings || 0) + (summary.dangerous || 0);
};

const getRiskIcon = (risk) => {
  const icons = {
    safe: '✅',
    warning: '⚠️',
    danger: '🔴',
  };
  return icons[risk] || '❓';
};

const getRiskLabel = (risk) => {
  const labels = {
    safe: 'SAFE',
    warning: 'WARNING',
    danger: 'DANGER',
  };
  return labels[risk] || 'UNKNOWN';
};

const getRiskBadgeClass = (risk) => {
  const classes = {
    safe: 'bg-green-100 text-green-800',
    warning: 'bg-yellow-100 text-yellow-800',
    danger: 'bg-red-100 text-red-800',
  };
  return classes[risk] || 'bg-gray-100 text-gray-800';
};

const getOperationBgClass = (risk) => {
  const classes = {
    safe: 'bg-green-50',
    warning: 'bg-yellow-50',
    danger: 'bg-red-50',
  };
  return classes[risk] || 'bg-gray-50';
};

const getOperationBorderClass = (risk) => {
  const classes = {
    safe: 'border-green-200',
    warning: 'border-yellow-200',
    danger: 'border-red-200',
  };
  return classes[risk] || 'border-gray-200';
};

const fetchPreview = async () => {
  if (!props.migrationName) return;

  loading.value = true;
  error.value = null;
  analysis.value = null;

  try {
    const response = await fetch(`/api/smart-migration/migrations/preview/${props.migrationName}`);
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to load migration preview');
    }

    analysis.value = data.analysis;
  } catch (err) {
    console.error('Error fetching migration preview:', err);
    error.value = err.message || 'Failed to load migration preview';
  } finally {
    loading.value = false;
  }
};

// Watch for modal opening
watch(() => props.show, (newShow) => {
  if (newShow) {
    fetchPreview();
  }
});
</script>
