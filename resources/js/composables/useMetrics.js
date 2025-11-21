import { ref, computed } from 'vue';
import api from '../utils/api';

export function useMetrics() {
    const metrics = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const executionTimes = computed(() => {
        return metrics.value?.execution_times || [];
    });

    const riskDistribution = computed(() => {
        return metrics.value?.risk_distribution || {
            safe: 0,
            warning: 0,
            danger: 0,
        };
    });

    const archiveSizes = computed(() => {
        return metrics.value?.archive_sizes || [];
    });

    const tableSizes = computed(() => {
        return metrics.value?.table_sizes || [];
    });

    const averageExecutionTime = computed(() => {
        if (!executionTimes.value.length) return 0;
        const total = executionTimes.value.reduce((sum, item) => sum + item.time, 0);
        return total / executionTimes.value.length;
    });

    async function fetchMetrics() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getMetrics();
            metrics.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch metrics';
            console.error('Error fetching metrics:', e);
        } finally {
            loading.value = false;
        }
    }

    return {
        metrics,
        loading,
        error,
        executionTimes,
        riskDistribution,
        archiveSizes,
        tableSizes,
        averageExecutionTime,
        fetchMetrics,
    };
}
