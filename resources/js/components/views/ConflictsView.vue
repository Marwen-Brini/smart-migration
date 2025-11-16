<template>
  <div class="p-8">
    <div class="card">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-900">🔍 Migration Conflicts</h2>
        <button
          @click="checkConflicts"
          :disabled="loading"
          class="btn btn-primary text-sm"
        >
          <span v-if="loading">⏳ Checking...</span>
          <span v-else>🔄 Check Conflicts</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="text-center">
          <div class="text-5xl mb-4 animate-spin">🔄</div>
          <p class="text-gray-600">Analyzing migrations for conflicts...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-center space-x-3">
          <div class="text-2xl">❌</div>
          <div>
            <h3 class="text-lg font-semibold text-red-900">Error Checking Conflicts</h3>
            <p class="text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- No Conflicts State -->
      <div v-else-if="!conflicts || conflicts.length === 0" class="bg-green-50 border border-green-200 rounded-lg p-8 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h3 class="text-xl font-semibold text-green-900 mb-2">No Conflicts Detected</h3>
        <p class="text-green-700">Your migrations are conflict-free and can be safely executed.</p>
      </div>

      <!-- Conflicts List -->
      <div v-else class="space-y-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
          <div class="flex items-center space-x-2">
            <span class="text-2xl">⚠️</span>
            <div>
              <h3 class="font-semibold text-red-900">{{ conflicts.length }} Conflict(s) Found</h3>
              <p class="text-sm text-red-700">These migrations have conflicting operations that may cause issues.</p>
            </div>
          </div>
        </div>

        <div
          v-for="(conflict, index) in conflicts"
          :key="index"
          class="border border-red-200 rounded-lg overflow-hidden"
        >
          <div class="bg-red-50 p-4">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center space-x-2 mb-2">
                  <span class="px-2 py-1 bg-red-600 text-white text-xs font-semibold rounded">
                    {{ conflict.type || 'CONFLICT' }}
                  </span>
                  <h4 class="font-semibold text-red-900">{{ conflict.table }}</h4>
                </div>
                <p class="text-sm text-red-800">{{ conflict.description || 'Conflicting operations detected' }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white p-4 border-t border-red-200">
            <h5 class="text-sm font-semibold text-gray-700 mb-3">Affected Migrations:</h5>
            <div class="space-y-2">
              <div
                v-for="(migration, mIndex) in conflict.migrations"
                :key="mIndex"
                class="text-sm font-mono bg-gray-50 p-2 rounded border border-gray-200"
              >
                {{ migration }}
              </div>
            </div>
          </div>

          <div v-if="conflict.resolution" class="bg-blue-50 p-4 border-t border-red-200">
            <h5 class="text-sm font-semibold text-blue-900 mb-2">💡 Suggested Resolution:</h5>
            <p class="text-sm text-blue-800">{{ conflict.resolution }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const loading = ref(false);
const error = ref(null);
const conflicts = ref([]);

const checkConflicts = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await fetch('/api/smart-migration/migrations/conflicts');
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to check conflicts');
    }

    conflicts.value = data.conflicts || [];
  } catch (err) {
    console.error('Error checking conflicts:', err);
    error.value = err.message || 'Failed to check for conflicts';
  } finally {
    loading.value = false;
  }
};

// Check conflicts on mount
onMounted(() => {
  checkConflicts();
});
</script>
