<template>
  <div class="bg-red-50 border border-red-200 rounded-lg p-6">
    <div class="flex items-start space-x-4">
      <div class="text-3xl">⚠️</div>
      <div class="flex-1">
        <h3 class="text-lg font-semibold text-red-900 mb-2">Schema Drift Detected</h3>
        <p class="text-red-700 mb-4">
          Your database schema has diverged from your migrations. This may cause issues with future migrations.
        </p>

        <!-- Differences Summary -->
        <div class="space-y-2 mb-4">
          <div v-if="tablesToCreate.length > 0" class="text-sm">
            <span class="font-medium text-red-900">Tables to create:</span>
            <span class="text-red-800 ml-2">{{ tablesToCreate.length }} table(s)</span>
            <span class="text-gray-600 text-xs ml-2">({{ tablesToCreate.join(', ') }})</span>
          </div>

          <div v-if="tablesToDrop.length > 0" class="text-sm">
            <span class="font-medium text-red-900">Tables to drop:</span>
            <span class="text-red-800 ml-2">{{ tablesToDrop.length }} table(s)</span>
            <span class="text-gray-600 text-xs ml-2">({{ tablesToDrop.join(', ') }})</span>
          </div>

          <div v-if="tablesToModify.length > 0" class="text-sm">
            <span class="font-medium text-red-900">Tables to modify:</span>
            <span class="text-red-800 ml-2">{{ tablesToModify.length }} table(s)</span>
            <span class="text-gray-600 text-xs ml-2">({{ tablesToModify.join(', ') }})</span>
          </div>
        </div>

        <!-- Detailed View Toggle -->
        <div v-if="showDetails" class="bg-white border border-red-200 rounded p-4 mb-4 max-h-96 overflow-y-auto">
          <h4 class="font-semibold text-red-900 mb-3">Detailed Differences:</h4>

          <!-- Tables to Create -->
          <div v-if="tablesToCreate.length > 0" class="mb-4">
            <h5 class="font-medium text-sm text-red-800 mb-2">Tables to Create:</h5>
            <div v-for="table in tablesToCreate" :key="table" class="ml-4 mb-3">
              <p class="font-medium text-sm">{{ table }}</p>
              <p class="text-xs text-gray-600 ml-2">
                {{ getTableDetails(table, 'create').columns }} columns,
                {{ getTableDetails(table, 'create').indexes }} indexes,
                {{ getTableDetails(table, 'create').foreign_keys }} foreign keys
              </p>
            </div>
          </div>

          <!-- Tables to Drop -->
          <div v-if="tablesToDrop.length > 0" class="mb-4">
            <h5 class="font-medium text-sm text-red-800 mb-2">Tables to Drop:</h5>
            <ul class="ml-4">
              <li v-for="table in tablesToDrop" :key="table" class="text-sm">{{ table }}</li>
            </ul>
          </div>

          <!-- Tables to Modify -->
          <div v-if="tablesToModify.length > 0" class="mb-4">
            <h5 class="font-medium text-sm text-red-800 mb-2">Tables to Modify:</h5>
            <div v-for="table in tablesToModify" :key="table" class="ml-4 mb-3">
              <p class="font-medium text-sm">{{ table }}</p>
              <div class="ml-2 text-xs text-gray-700">
                <p v-if="getModifications(table).columns_to_add">
                  + Add {{ getModifications(table).columns_to_add }} column(s)
                </p>
                <p v-if="getModifications(table).columns_to_drop">
                  - Drop {{ getModifications(table).columns_to_drop }} column(s)
                </p>
                <p v-if="getModifications(table).columns_to_rename">
                  ↔ Rename {{ getModifications(table).columns_to_rename }} column(s)
                </p>
                <p v-if="getModifications(table).columns_to_modify">
                  ✎ Modify {{ getModifications(table).columns_to_modify }} column(s)
                </p>
                <p v-if="getModifications(table).indexes_to_add">
                  + Add {{ getModifications(table).indexes_to_add }} index(es)
                </p>
                <p v-if="getModifications(table).indexes_to_drop">
                  - Drop {{ getModifications(table).indexes_to_drop }} index(es)
                </p>
              </div>
            </div>
          </div>

          <p class="text-xs text-gray-500 mt-4">
            Snapshot created: {{ snapshotDate }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center space-x-3">
          <button @click="toggleDetails" class="btn btn-secondary text-sm">
            {{ showDetails ? 'Hide Details' : 'View Details' }}
          </button>
          <button
            @click="handleGenerateFix"
            :disabled="isGenerating"
            class="btn btn-primary text-sm"
          >
            <span v-if="isGenerating">⏳ Generating...</span>
            <span v-else">🔧 Generate Fix Migration</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '../utils/api';
import { useToast } from '../composables/useToast';

const props = defineProps({
  drift: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['refresh']);

const { success, error } = useToast();
const showDetails = ref(false);
const isGenerating = ref(false);

const toggleDetails = () => {
  showDetails.value = !showDetails.value;
};

const handleGenerateFix = async () => {
  if (isGenerating.value) return;

  try {
    isGenerating.value = true;
    const result = await api.generateFixMigration();

    if (result.success) {
      success(result.message || 'Fix migration generated successfully!');
      emit('refresh');
    } else {
      error(result.message || 'Failed to generate fix migration');
    }
  } catch (err) {
    console.error('Error generating fix migration:', err);
    error(err.response?.data?.message || 'Failed to generate fix migration');
  } finally {
    isGenerating.value = false;
  }
};

const differences = computed(() => props.drift?.differences || {});

const tablesToCreate = computed(() => {
  return Object.keys(differences.value.tables_to_create || {});
});

const tablesToDrop = computed(() => {
  return differences.value.tables_to_drop || [];
});

const tablesToModify = computed(() => {
  return Object.keys(differences.value.tables_to_modify || {});
});

const snapshotDate = computed(() => {
  const date = props.drift?.snapshot_created_at;
  if (!date) return 'Unknown';
  return new Date(date).toLocaleString();
});

const getTableDetails = (tableName, type) => {
  if (type === 'create') {
    const table = differences.value.tables_to_create?.[tableName];
    return {
      columns: table?.columns?.length || 0,
      indexes: table?.indexes?.length || 0,
      foreign_keys: table?.foreign_keys?.length || 0,
    };
  }
  return { columns: 0, indexes: 0, foreign_keys: 0 };
};

const getModifications = (tableName) => {
  const mods = differences.value.tables_to_modify?.[tableName] || {};
  return {
    columns_to_add: mods.columns_to_add?.length || 0,
    columns_to_drop: mods.columns_to_drop?.length || 0,
    columns_to_rename: mods.columns_to_rename?.length || 0,
    columns_to_modify: mods.columns_to_modify?.length || 0,
    indexes_to_add: mods.indexes_to_add?.length || 0,
    indexes_to_drop: mods.indexes_to_drop?.length || 0,
  };
};
</script>
