<template>
  <div :class="['stat-card', riskClass]">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-600">{{ title }}</p>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ value }}</p>
      </div>
      <div class="text-4xl">{{ icon }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  risk: {
    type: String,
    default: 'info',
    validator: (value) => ['safe', 'warning', 'danger', 'info'].includes(value),
  },
  icon: {
    type: String,
    default: '📊',
  },
});

const riskClass = computed(() => {
  return `stat-card-${props.risk}`;
});
</script>
