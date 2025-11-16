<template>
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-900">Migration History</h2>
      <span class="text-sm text-gray-600">{{ historyCount }} migrations</span>
    </div>

    <!-- Timeline -->
    <div v-if="sortedHistory.length > 0" class="space-y-4">
      <div v-for="(item, index) in sortedHistory" :key="item.id || index" class="relative pl-8">
        <!-- Timeline dot -->
        <div class="absolute left-0 top-1 w-3 h-3 rounded-full bg-green-500 border-2 border-white"></div>

        <!-- Timeline line -->
        <div v-if="index < sortedHistory.length - 1" class="absolute left-1.5 top-4 w-0.5 h-full bg-gray-300"></div>

        <!-- Content -->
        <div class="pb-6">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h4 class="font-medium text-gray-900">{{ formatName(item.migration) }}</h4>
              <p class="text-xs text-gray-500 mt-1">{{ item.migration }}</p>
              <div class="flex items-center space-x-3 mt-2">
                <span class="text-xs text-gray-600">
                  {{ formatDate(item.applied_at) }}
                </span>
                <span v-if="item.execution_time" class="text-xs text-gray-600">
                  ⏱️ {{ formatDuration(item.execution_time) }}
                </span>
                <span v-if="item.batch" class="text-xs text-gray-500">
                  Batch #{{ item.batch }}
                </span>
              </div>
            </div>
            <div class="text-lg">✅</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8">
      <div class="text-4xl mb-3">📜</div>
      <p class="text-gray-600">No migration history</p>
      <p class="text-sm text-gray-500 mt-1">Run migrations to see history here</p>
    </div>

    <!-- View All Link -->
    <div v-if="sortedHistory.length > 10" class="mt-6 text-center">
      <button class="text-sm text-blue-600 hover:text-blue-700 font-medium">
        View All History →
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatMigrationName, formatDate, formatDuration } from '../utils/formatters';

const props = defineProps({
  history: {
    type: Array,
    default: () => [],
  },
});

const sortedHistory = computed(() => {
  return [...props.history]
    .sort((a, b) => new Date(b.applied_at) - new Date(a.applied_at))
    .slice(0, 10);
});

const historyCount = computed(() => props.history.length);

function formatName(name) {
  return formatMigrationName(name);
}
</script>
