<template>
  <div class="fixed top-4 right-4 z-50 space-y-2">
    <transition-group name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[
          'px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md',
          getToastClass(toast.type)
        ]"
      >
        <div class="flex-shrink-0 text-2xl">
          {{ getIcon(toast.type) }}
        </div>
        <div class="flex-1 text-sm font-medium">
          {{ toast.message }}
        </div>
        <button
          @click="removeToast(toast.id)"
          class="flex-shrink-0 text-xl hover:opacity-70"
        >
          ×
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useToast } from '../composables/useToast';

const { toasts, removeToast } = useToast();

const getToastClass = (type) => {
  const classes = {
    success: 'bg-green-50 border border-green-200 text-green-800',
    error: 'bg-red-50 border border-red-200 text-red-800',
    warning: 'bg-yellow-50 border border-yellow-200 text-yellow-800',
    info: 'bg-blue-50 border border-blue-200 text-blue-800',
  };
  return classes[type] || classes.info;
};

const getIcon = (type) => {
  const icons = {
    success: '✓',
    error: '✗',
    warning: '⚠',
    info: 'ℹ',
  };
  return icons[type] || icons.info;
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}
</style>
