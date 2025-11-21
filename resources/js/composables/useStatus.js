import { ref } from 'vue';
import api from '../utils/api';

export function useStatus() {
    const status = ref(null);
    const loading = ref(false);
    const error = ref(null);

    async function fetchStatus() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getStatus();
            status.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch status';
            console.error('Error fetching status:', e);
        } finally {
            loading.value = false;
        }
    }

    return {
        status,
        loading,
        error,
        fetchStatus,
    };
}
