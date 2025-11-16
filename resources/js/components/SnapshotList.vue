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
      <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto m-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Snapshot Details</h3>
          <button @click="closeDetailsModal" class="text-2xl hover:opacity-70">&times;</button>
        </div>

        <div v-if="selectedSnapshot" class="space-y-4">
          <div>
            <p class="text-sm font-medium text-gray-700">Name</p>
            <p class="text-sm text-gray-900">{{ selectedSnapshot.name }}</p>
          </div>

          <div>
            <p class="text-sm font-medium text-gray-700">Created</p>
            <p class="text-sm text-gray-900">{{ formatDate(selectedSnapshot.timestamp) }}</p>
          </div>

          <div>
            <p class="text-sm font-medium text-gray-700">Tables ({{ selectedSnapshot.table_count }})</p>
            <div class="mt-2 max-h-64 overflow-y-auto border border-gray-200 rounded p-2">
              <div v-if="selectedSnapshot.schema?.tables">
                <div
                  v-for="(table, tableName) in selectedSnapshot.schema.tables"
                  :key="tableName"
                  class="text-sm py-1 border-b border-gray-100 last:border-0"
                >
                  <span class="font-medium">{{ tableName }}</span>
                  <span class="text-gray-500 ml-2">
                    ({{ table.columns?.length || 0 }} columns)
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedSnapshot.format_version">
            <p class="text-sm font-medium text-gray-700">Format Version</p>
            <p class="text-sm text-gray-900">{{ selectedSnapshot.format_version }}</p>
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

const handleViewDetails = (snapshot) => {
  selectedSnapshot.value = snapshot;
  showDetailsModal.value = true;
};

const closeDetailsModal = () => {
  showDetailsModal.value = false;
  selectedSnapshot.value = null;
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
