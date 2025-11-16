import { ref, computed } from 'vue';
import api from '../utils/api';

export function useMigrations() {
    const migrations = ref([]);
    const loading = ref(false);
    const error = ref(null);

    const pendingMigrations = computed(() => {
        return migrations.value.filter(m => m.status === 'pending');
    });

    const appliedMigrations = computed(() => {
        return migrations.value.filter(m => m.status === 'applied');
    });

    const hasPendingMigrations = computed(() => {
        return pendingMigrations.value.length > 0;
    });

    async function fetchMigrations() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getMigrations();
            migrations.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch migrations';
            console.error('Error fetching migrations:', e);
        } finally {
            loading.value = false;
        }
    }

    async function fetchMigration(name) {
        loading.value = true;
        error.value = null;

        try {
            return await api.getMigration(name);
        } catch (e) {
            error.value = e.message || 'Failed to fetch migration';
            console.error('Error fetching migration:', e);
            return null;
        } finally {
            loading.value = false;
        }
    }

    return {
        migrations,
        loading,
        error,
        pendingMigrations,
        appliedMigrations,
        hasPendingMigrations,
        fetchMigrations,
        fetchMigration,
    };
}

export function useMigrationHistory() {
    const history = ref([]);
    const loading = ref(false);
    const error = ref(null);

    async function fetchHistory() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getHistory();
            history.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch migration history';
            console.error('Error fetching history:', e);
        } finally {
            loading.value = false;
        }
    }

    return {
        history,
        loading,
        error,
        fetchHistory,
    };
}
