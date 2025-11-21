<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-900">📸 Snapshots</h2>
      <span class="text-sm text-gray-500">{{ snapshots.length }} snapshot(s)</span>
    </div>

    <!-- Empty State -->
    <div v-if="!snapshots || snapshots.length === 0" class="text-center py-8">
      <div class="text-4xl mb-2">📦</div>
      <p class="text-gray-600">No snapshots available</p>
      <p class="text-sm text-gray-500 mt-1">Create a snapshot to save the current database state</p>
    </div>

    <!-- Snapshots List -->
    <div v-else class="space-y-3">
      <div
        v-for="snapshot in snapshots"
        :key="snapshot.name"
        class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center space-x-2">
              <h3 class="font-medium text-gray-900">{{ snapshot.name }}</h3>
              <span v-if="snapshot.is_latest" class="badge badge-safe text-xs">Latest</span>
            </div>
            <p class="text-sm text-gray-600 mt-1">
              Created: {{ formatDate(snapshot.timestamp) }}
            </p>
            <div class="text-xs text-gray-500 mt-1">
              {{ snapshot.table_count || 0 }} tables •
              {{ formatSize(snapshot.size) }}
            </div>
          </div>

          <div class="flex items-center space-x-2">
            <button
              @click="handleViewDetails(snapshot)"
              class="btn btn-secondary text-xs"
            >
              👁 View
            </button>
            <button
              @click="handleDelete(snapshot)"
              :disabled="deletingSnapshot === snapshot.name"
              class="btn text-xs bg-red-50 text-red-700 border-red-200 hover:bg-red-100"
            >
              <span v-if="deletingSnapshot === snapshot.name">⏳</span>
              <span v-else>🗑</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Details Modal -->
    <div
      v-if="showDetailsModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeDetailsModal"
    >
      <div class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-hidden m-4 flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h3 class="text-xl font-semibold text-gray-900">📸 {{ selectedSnapshot?.name }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ formatDate(selectedSnapshot?.timestamp) }}</p>
          </div>
          <button @click="closeDetailsModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">
            &times;
          </button>
        </div>

        <!-- Content -->
        <div v-if="selectedSnapshot" class="flex-1 overflow-y-auto p-6">
          <!-- Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4">
              <p class="text-sm font-medium text-blue-900">Total Tables</p>
              <p class="text-2xl font-bold text-blue-600 mt-1">{{ selectedSnapshot.table_count || 0 }}</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
              <p class="text-sm font-medium text-green-900">Snapshot Size</p>
              <p class="text-2xl font-bold text-green-600 mt-1">{{ formatSize(selectedSnapshot.size) }}</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
              <p class="text-sm font-medium text-purple-900">Format Version</p>
              <p class="text-2xl font-bold text-purple-600 mt-1">{{ selectedSnapshot.format_version || 'v1' }}</p>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4">
              <p class="text-sm font-medium text-yellow-900">Status</p>
              <p class="text-sm font-semibold mt-1">
                <span v-if="selectedSnapshot.is_latest" class="text-green-600">✓ Latest</span>
                <span v-else class="text-gray-600">Archived</span>
              </p>
            </div>
          </div>

          <!-- Tables List -->
          <div class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-900">Database Schema</h4>

            <div v-if="selectedSnapshot.schema?.tables" class="space-y-3">
              <div
                v-for="(table, tableName) in selectedSnapshot.schema.tables"
                :key="tableName"
                class="border border-gray-200 rounded-lg overflow-hidden"
              >
                <!-- Table Header -->
                <button
                  @click="toggleTableExpanded(tableName)"
                  class="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors"
                >
                  <div class="flex items-center space-x-3">
                    <span class="text-lg">{{ expandedTables.has(tableName) ? '▼' : '▶' }}</span>
                    <div class="text-left">
                      <h5 class="font-semibold text-gray-900">{{ tableName }}</h5>
                      <p class="text-xs text-gray-500">
                        {{ table.columns?.length || 0 }} columns
                        <span v-if="table.indexes?.length"> • {{ table.indexes.length }} indexes</span>
                        <span v-if="table.foreign_keys?.length"> • {{ table.foreign_keys.length }} foreign keys</span>
                      </p>
                    </div>
                  </div>
                </button>

                <!-- Expanded Table Details -->
                <div v-if="expandedTables.has(tableName)" class="border-t border-gray-200">
                  <!-- Columns -->
                  <div class="p-4">
                    <h6 class="text-sm font-semibold text-gray-700 mb-3">Columns</h6>
                    <div class="overflow-x-auto">
                      <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                          <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nullable</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Extra</th>
                          </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                          <tr v-for="column in table.columns" :key="column.name">
                            <td class="px-3 py-2 font-medium text-gray-900">
                              {{ column.name }}
                              <span v-if="column.primary" class="ml-1 text-xs text-blue-600">🔑</span>
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ column.type }}</td>
                            <td class="px-3 py-2">
                              <span :class="column.nullable ? 'text-green-600' : 'text-red-600'">
                                {{ column.nullable ? 'YES' : 'NO' }}
                              </span>
                            </td>
                            <td class="px-3 py-2 text-gray-600">
                              <code v-if="column.default" class="text-xs bg-gray-100 px-1 py-0.5 rounded">
                                {{ column.default }}
                              </code>
                              <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-3 py-2 text-gray-600">
                              <span v-if="column.auto_increment" class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">
                                AUTO_INCREMENT
                              </span>
                              <span v-else class="text-gray-400">-</span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- Indexes -->
                  <div v-if="table.indexes && table.indexes.length > 0" class="p-4 bg-gray-50 border-t border-gray-200">
                    <h6 class="text-sm font-semibold text-gray-700 mb-3">Indexes</h6>
                    <div class="space-y-2">
                      <div
                        v-for="index in table.indexes"
                        :key="index.name"
                        class="flex items-center justify-between text-sm bg-white p-2 rounded border border-gray-200"
                      >
                        <div>
                          <span class="font-medium text-gray-900">{{ index.name }}</span>
                          <span class="text-gray-500 ml-2">({{ index.columns?.join(', ') || 'N/A' }})</span>
                        </div>
                        <div class="flex items-center space-x-2">
                          <span
                            v-if="index.unique"
                            class="text-xs bg-purple-100 text-purple-800 px-2 py-0.5 rounded"
                          >
                            UNIQUE
                          </span>
                          <span
                            v-if="index.primary"
                            class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded"
                          >
                            PRIMARY
                          </span>
                          <span class="text-xs text-gray-500">{{ index.type || 'BTREE' }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Foreign Keys -->
                  <div v-if="table.foreign_keys && table.foreign_keys.length > 0" class="p-4 border-t border-gray-200">
                    <h6 class="text-sm font-semibold text-gray-700 mb-3">Foreign Keys</h6>
                    <div class="space-y-2">
                      <div
                        v-for="fk in table.foreign_keys"
                        :key="fk.name"
                        class="text-sm bg-gray-50 p-3 rounded border border-gray-200"
                      >
                        <div class="font-medium text-gray-900 mb-1">{{ fk.name }}</div>
                        <div class="text-gray-600">
                          <span class="font-mono text-xs bg-white px-1.5 py-0.5 rounded border">{{ fk.column }}</span>
                          →
                          <span class="font-mono text-xs bg-white px-1.5 py-0.5 rounded border">{{ fk.foreign_table }}.{{ fk.foreign_column }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                          <span v-if="fk.on_delete">ON DELETE: {{ fk.on_delete }}</span>
                          <span v-if="fk.on_update" class="ml-2">ON UPDATE: {{ fk.on_update }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-gray-500">
              No schema data available
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="cancelDelete"
    >
      <div class="bg-white rounded-lg p-6 max-w-md w-full m-4">
        <h3 class="text-lg font-semibold mb-4">Delete Snapshot?</h3>
        <p class="text-sm text-gray-600 mb-6">
          Are you sure you want to delete snapshot "{{ snapshotToDelete?.name }}"?
          This action cannot be undone.
        </p>
        <div class="flex justify-end space-x-3">
          <button @click="cancelDelete" class="btn btn-secondary">
            Cancel
          </button>
          <button
            @click="confirmDelete"
            :disabled="deletingSnapshot"
            class="btn bg-red-600 text-white hover:bg-red-700 border-red-600"
          >
            <span v-if="deletingSnapshot">⏳ Deleting...</span>
            <span v-else>Delete</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import api from '../utils/api';
import { useToast } from '../composables/useToast';

const props = defineProps({
  snapshots: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['refresh']);

const { success, error } = useToast();
const showDetailsModal = ref(false);
const showDeleteConfirm = ref(false);
const selectedSnapshot = ref(null);
const snapshotToDelete = ref(null);
const deletingSnapshot = ref(null);
const expandedTables = ref(new Set());

const formatDate = (timestamp) => {
  if (!timestamp) return 'Unknown';
  return new Date(timestamp).toLocaleString();
};

const formatSize = (size) => {
  if (!size) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let unitIndex = 0;
  let fileSize = size;

  while (fileSize >= 1024 && unitIndex < units.length - 1) {
    fileSize /= 1024;
    unitIndex++;
  }

  return `${fileSize.toFixed(1)} ${units[unitIndex]}`;
};

const toggleTableExpanded = (tableName) => {
  if (expandedTables.value.has(tableName)) {
    expandedTables.value.delete(tableName);
  } else {
    expandedTables.value.add(tableName);
  }
  // Trigger reactivity
  expandedTables.value = new Set(expandedTables.value);
};

const handleViewDetails = (snapshot) => {
  selectedSnapshot.value = snapshot;
  showDetailsModal.value = true;
  expandedTables.value = new Set(); // Reset expanded state
};

const closeDetailsModal = () => {
  showDetailsModal.value = false;
  selectedSnapshot.value = null;
  expandedTables.value = new Set(); // Reset expanded state
};

const handleDelete = (snapshot) => {
  snapshotToDelete.value = snapshot;
  showDeleteConfirm.value = true;
};

const cancelDelete = () => {
  showDeleteConfirm.value = false;
  snapshotToDelete.value = null;
};

const confirmDelete = async () => {
  if (!snapshotToDelete.value || deletingSnapshot.value) return;

  try {
    deletingSnapshot.value = snapshotToDelete.value.name;
    const result = await api.deleteSnapshot(snapshotToDelete.value.name);

    if (result.success) {
      success(`Snapshot "${snapshotToDelete.value.name}" deleted successfully`);
      showDeleteConfirm.value = false;
      snapshotToDelete.value = null;
      emit('refresh');
    } else {
      error(result.message || 'Failed to delete snapshot');
    }
  } catch (err) {
    console.error('Error deleting snapshot:', err);
    error(err.response?.data?.message || 'Failed to delete snapshot');
  } finally {
    deletingSnapshot.value = null;
  }
};
</script>
