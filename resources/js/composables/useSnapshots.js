import { ref } from 'vue';
import api from '../utils/api';

export function useSnapshots() {
    const snapshots = ref([]);
    const loading = ref(false);
    const error = ref(null);

    const fetchSnapshots = async () => {
        loading.value = true;
        error.value = null;
        try {
            snapshots.value = await api.getSnapshots();
        } catch (e) {
            error.value = e.message || 'Failed to fetch snapshots';
            console.error('Error fetching snapshots:', e);
        } finally {
            loading.value = false;
        }
    };

    return {
        snapshots,
        loading,
        error,
        fetchSnapshots,
    };
}
