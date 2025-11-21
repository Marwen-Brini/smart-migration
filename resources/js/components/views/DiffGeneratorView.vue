<template>
  <div class="p-8">
    <div class="card">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-xl font-semibold text-gray-900">🔍 Auto-Diff Generator</h2>
          <p class="text-sm text-gray-600 mt-1">Generate migrations from database schema changes</p>
        </div>
        <button
          @click="detectDifferences"
          :disabled="loading"
          class="btn btn-primary text-sm"
        >
          <span v-if="loading">⏳ Detecting...</span>
          <span v-else>🔄 Detect Changes</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="text-center">
          <div class="text-5xl mb-4 animate-spin">🔍</div>
          <p class="text-gray-600">Analyzing database schema...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-center space-x-3">
          <div class="text-2xl">❌</div>
          <div>
            <h3 class="text-lg font-semibold text-red-900">Error Detecting Differences</h3>
            <p class="text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- No Differences State -->
      <div v-else-if="diffResult && !diffResult.has_differences" class="bg-green-50 border border-green-200 rounded-lg p-8 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h3 class="text-xl font-semibold text-green-900 mb-2">No Differences Detected</h3>
        <p class="text-green-700">Your database schema is in sync with migrations.</p>
      </div>

      <!-- Differences Found -->
      <div v-else-if="diffResult && diffResult.has_differences" class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
          <div class="flex items-center space-x-2">
            <span class="text-2xl">📋</span>
            <div>
              <h3 class="font-semibold text-blue-900">Schema Differences Detected</h3>
              <p class="text-sm text-blue-700">The following changes were found between your database and migrations</p>
            </div>
          </div>
        </div>

        <!-- Tables Added -->
        <div v-if="diffResult.tables_added && diffResult.tables_added.length > 0" class="border border-green-200 bg-green-50 rounded-lg p-4">
          <h4 class="font-semibold text-green-900 mb-3 flex items-center">
            <span class="text-xl mr-2">➕</span>
            Tables Added ({{ diffResult.tables_added.length }})
          </h4>
          <div class="flex flex-wrap gap-2">
            <span v-for="table in diffResult.tables_added" :key="table" class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded font-mono">
              {{ table }}
            </span>
          </div>
        </div>

        <!-- Tables Modified -->
        <div v-if="diffResult.tables_modified && diffResult.tables_modified.length > 0" class="border border-yellow-200 bg-yellow-50 rounded-lg p-4">
          <h4 class="font-semibold text-yellow-900 mb-3 flex items-center">
            <span class="text-xl mr-2">✏️</span>
            Tables Modified ({{ diffResult.tables_modified.length }})
          </h4>
          <div class="flex flex-wrap gap-2">
            <span v-for="table in diffResult.tables_modified" :key="table" class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded font-mono">
              {{ table }}
            </span>
          </div>
        </div>

        <!-- Tables Removed -->
        <div v-if="diffResult.tables_removed && diffResult.tables_removed.length > 0" class="border border-red-200 bg-red-50 rounded-lg p-4">
          <h4 class="font-semibold text-red-900 mb-3 flex items-center">
            <span class="text-xl mr-2">🗑️</span>
            Tables Removed ({{ diffResult.tables_removed.length }})
          </h4>
          <div class="flex flex-wrap gap-2">
            <span v-for="table in diffResult.tables_removed" :key="table" class="px-3 py-1 bg-red-100 text-red-800 text-sm rounded font-mono">
              {{ table }}
            </span>
          </div>
        </div>

        <!-- Raw Output -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <h4 class="text-sm font-semibold text-gray-700 mb-2">Detailed Output:</h4>
          <pre class="text-xs font-mono text-gray-600 whitespace-pre-wrap max-h-60 overflow-y-auto">{{ diffResult.output }}</pre>
        </div>

        <!-- Generate Migration Form -->
        <div class="bg-white border border-gray-300 rounded-lg p-6">
          <h4 class="font-semibold text-gray-900 mb-4">Generate Migration</h4>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Migration Name (optional)
              </label>
              <input
                v-model="migrationName"
                type="text"
                placeholder="e.g., sync_database_changes"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <p class="text-xs text-gray-500 mt-1">Leave empty for auto-generated name</p>
            </div>

            <div class="flex justify-end space-x-3">
              <button
                @click="diffResult = null"
                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                @click="generateMigration"
                :disabled="generating"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
              >
                <span v-if="generating">⏳ Generating...</span>
                <span v-else>🔨 Generate Migration</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Generation Success -->
      <div v-else-if="generatedMigration" class="space-y-4">
        <div class="text-center py-6">
          <div class="text-6xl mb-4">✅</div>
          <h3 class="text-xl font-semibold text-green-900 mb-2">Migration Generated!</h3>
          <p class="text-green-700">Your migration file has been created successfully</p>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <h4 class="text-sm font-semibold text-green-900 mb-2">Migration File:</h4>
          <p class="text-sm font-mono text-green-800">{{ generatedMigration.migration_path }}</p>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <h4 class="text-sm font-semibold text-gray-700 mb-2">Output:</h4>
          <pre class="text-xs font-mono text-gray-600 whitespace-pre-wrap">{{ generatedMigration.output }}</pre>
        </div>

        <div class="flex justify-end space-x-3">
          <button
            @click="reset"
            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors"
          >
            Done
          </button>
          <button
            @click="$emit('refresh')"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Refresh Migrations
          </button>
        </div>
      </div>

      <!-- Initial State -->
      <div v-else class="text-center py-12">
        <div class="text-5xl mb-4">🔍</div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Auto-Diff Generator</h3>
        <p class="text-gray-600 mb-6">Automatically generate migrations from database schema changes</p>
        <button
          @click="detectDifferences"
          class="btn btn-primary"
        >
          🔄 Detect Changes
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const loading = ref(false);
const error = ref(null);
const diffResult = ref(null);
const migrationName = ref('');
const generating = ref(false);
const generatedMigration = ref(null);

const emit = defineEmits(['refresh']);

const detectDifferences = async () => {
  loading.value = true;
  error.value = null;
  diffResult.value = null;

  try {
    const response = await fetch('/api/smart-migration/migrations/diff');
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to detect differences');
    }

    diffResult.value = data;
  } catch (err) {
    console.error('Error detecting differences:', err);
    error.value = err.message || 'Failed to detect differences';
  } finally {
    loading.value = false;
  }
};

const generateMigration = async () => {
  generating.value = true;

  try {
    const response = await fetch('/api/smart-migration/migrations/diff/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: migrationName.value || null,
      }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to generate migration');
    }

    generatedMigration.value = data;
    diffResult.value = null;
  } catch (err) {
    console.error('Error generating migration:', err);
    error.value = err.message || 'Failed to generate migration';
  } finally {
    generating.value = false;
  }
};

const reset = () => {
  diffResult.value = null;
  generatedMigration.value = null;
  migrationName.value = '';
  error.value = null;
};
</script>
