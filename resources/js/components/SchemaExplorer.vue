<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-900">Database Schema</h2>
      <span class="text-sm text-gray-600">{{ tableCount }} tables</span>
    </div>

    <!-- Search -->
    <div class="mb-4">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search tables..."
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      />
    </div>

    <!-- Tables Grid -->
    <div v-if="filteredTables.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="table in filteredTables"
        :key="table.name"
        class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
        @click="selectTable(table)"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h3 class="font-semibold text-gray-900">{{ table.name }}</h3>
            <div class="flex items-center space-x-4 mt-2 text-sm text-gray-600">
              <span>📊 {{ table.column_count || 0 }} columns</span>
              <span>📝 {{ formatNumber(table.row_count || 0) }} rows</span>
            </div>
            <div v-if="table.indexes && table.indexes.length > 0" class="mt-2 text-xs text-gray-500">
              {{ table.indexes.length }} indexes
            </div>
          </div>
          <div class="text-2xl">📁</div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="searchQuery" class="text-center py-8">
      <div class="text-4xl mb-3">🔍</div>
      <p class="text-gray-600">No tables found matching "{{ searchQuery }}"</p>
    </div>

    <div v-else class="text-center py-8">
      <div class="text-4xl mb-3">📂</div>
      <p class="text-gray-600">No tables in database</p>
    </div>

    <!-- Selected Table Details Modal (simplified for now) -->
    <div v-if="selectedTable" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="selectedTable = null">
      <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto" @click.stop>
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-2xl font-bold text-gray-900">{{ selectedTable.name }}</h3>
          <button @click="selectedTable = null" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
        </div>

        <!-- Table Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
          <div class="bg-gray-50 rounded p-4">
            <p class="text-sm text-gray-600">Columns</p>
            <p class="text-2xl font-bold text-gray-900">{{ selectedTable.column_count || 0 }}</p>
          </div>
          <div class="bg-gray-50 rounded p-4">
            <p class="text-sm text-gray-600">Rows</p>
            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(selectedTable.row_count || 0) }}</p>
          </div>
          <div class="bg-gray-50 rounded p-4">
            <p class="text-sm text-gray-600">Indexes</p>
            <p class="text-2xl font-bold text-gray-900">{{ selectedTable.indexes?.length || 0 }}</p>
          </div>
        </div>

        <!-- Columns List -->
        <div class="mb-6">
          <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900">Columns</h4>
            <input
              v-model="columnSearch"
              type="text"
              placeholder="Search columns..."
              class="px-3 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nullable</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Extra</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="column in filteredColumns" :key="column.name" class="hover:bg-gray-50">
                  <td class="px-4 py-2 text-sm">
                    <div class="flex items-center space-x-2">
                      <span class="font-medium text-gray-900">{{ column.name }}</span>
                      <span v-if="isPrimaryKey(column)" class="badge badge-warning text-xs">PK</span>
                      <span v-if="column.auto_increment" class="badge bg-purple-100 text-purple-800 text-xs">AUTO</span>
                    </div>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-600">{{ column.type }}</td>
                  <td class="px-4 py-2 text-sm text-gray-600">
                    <span :class="column.nullable ? 'text-green-600' : 'text-gray-400'">
                      {{ column.nullable ? 'Yes' : 'No' }}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-600">
                    <span class="font-mono text-xs">{{ column.default || '-' }}</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">{{ column.extra || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Indexes -->
        <div v-if="selectedTable.indexes && selectedTable.indexes.length > 0" class="mb-6">
          <h4 class="font-semibold text-gray-900 mb-3">Indexes</h4>
          <div class="space-y-2">
            <div v-for="index in selectedTable.indexes" :key="index.name" class="bg-gray-50 rounded p-3">
              <div class="flex items-center justify-between">
                <span class="font-medium text-gray-900">{{ index.name }}</span>
                <div class="flex items-center space-x-2">
                  <span v-if="index.primary" class="text-xs badge badge-warning">PRIMARY</span>
                  <span v-else-if="index.unique" class="text-xs badge bg-blue-100 text-blue-800">UNIQUE</span>
                  <span v-else class="text-xs badge bg-gray-200 text-gray-700">INDEX</span>
                </div>
              </div>
              <p class="text-sm text-gray-600 mt-1">Columns: {{ index.columns?.join(', ') || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <!-- Foreign Keys -->
        <div v-if="selectedTable.foreign_keys && selectedTable.foreign_keys.length > 0">
          <h4 class="font-semibold text-gray-900 mb-3">Foreign Keys</h4>
          <div class="space-y-2">
            <div v-for="(fk, index) in selectedTable.foreign_keys" :key="index" class="bg-gray-50 rounded p-3">
              <div class="flex items-center space-x-2 mb-1">
                <span class="font-medium text-gray-900">{{ fk.constraint_name || 'FK_' + index }}</span>
                <span class="text-xs badge bg-indigo-100 text-indigo-800">FK</span>
              </div>
              <div class="text-sm text-gray-600 space-y-1">
                <p>
                  <span class="font-medium">Column:</span>
                  <span class="font-mono ml-1">{{ fk.column || fk.columns?.join(', ') }}</span>
                </p>
                <p>
                  <span class="font-medium">References:</span>
                  <span class="font-mono ml-1">{{ fk.foreign_table || fk.referenced_table }}</span>
                  <span class="mx-1">→</span>
                  <span class="font-mono">{{ fk.foreign_column || fk.referenced_column }}</span>
                </p>
                <div v-if="fk.on_delete || fk.on_update" class="flex items-center space-x-3 text-xs">
                  <span v-if="fk.on_delete">ON DELETE: {{ fk.on_delete }}</span>
                  <span v-if="fk.on_update">ON UPDATE: {{ fk.on_update }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { formatNumber } from '../utils/formatters';

const props = defineProps({
  schema: {
    type: Object,
    default: () => ({ tables: [] }),
  },
});

const searchQuery = ref('');
const columnSearch = ref('');
const selectedTable = ref(null);

const tables = computed(() => props.schema?.tables || []);
const tableCount = computed(() => tables.value.length);

const filteredTables = computed(() => {
  if (!searchQuery.value) return tables.value;

  const query = searchQuery.value.toLowerCase();
  return tables.value.filter(table =>
    table.name.toLowerCase().includes(query)
  );
});

const filteredColumns = computed(() => {
  if (!selectedTable.value?.columns) return [];
  if (!columnSearch.value) return selectedTable.value.columns;

  const query = columnSearch.value.toLowerCase();
  return selectedTable.value.columns.filter(column =>
    column.name.toLowerCase().includes(query) ||
    column.type.toLowerCase().includes(query)
  );
});

function selectTable(table) {
  selectedTable.value = table;
  columnSearch.value = ''; // Reset column search when selecting new table
}

function isPrimaryKey(column) {
  if (!selectedTable.value?.indexes) return false;

  return selectedTable.value.indexes.some(index =>
    index.primary && index.columns?.includes(column.name)
  );
}
</script>
