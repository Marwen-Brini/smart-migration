import { ref, computed } from 'vue';
import api from '../utils/api';

export function useSchema() {
    const schema = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const tables = computed(() => {
        return schema.value?.tables || [];
    });

    const tableCount = computed(() => {
        return tables.value.length;
    });

    const totalColumns = computed(() => {
        return tables.value.reduce((total, table) => {
            return total + (table.columns?.length || 0);
        }, 0);
    });

    const totalRows = computed(() => {
        return tables.value.reduce((total, table) => {
            return total + (table.row_count || 0);
        }, 0);
    });

    async function fetchSchema() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getSchema();
            schema.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch schema';
            console.error('Error fetching schema:', e);
        } finally {
            loading.value = false;
        }
    }

    async function fetchTable(tableName) {
        loading.value = true;
        error.value = null;

        try {
            return await api.getTable(tableName);
        } catch (e) {
            error.value = e.message || 'Failed to fetch table';
            console.error('Error fetching table:', e);
            return null;
        } finally {
            loading.value = false;
        }
    }

    return {
        schema,
        loading,
        error,
        tables,
        tableCount,
        totalColumns,
        totalRows,
        fetchSchema,
        fetchTable,
    };
}

export function useDrift() {
    const drift = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const hasDrift = computed(() => {
        return drift.value?.has_drift || false;
    });

    const driftCount = computed(() => {
        if (!drift.value?.differences) return 0;
        return drift.value.differences.length;
    });

    async function fetchDrift() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getDrift();
            drift.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch drift status';
            console.error('Error fetching drift:', e);
        } finally {
            loading.value = false;
        }
    }

    return {
        drift,
        loading,
        error,
        hasDrift,
        driftCount,
        fetchDrift,
    };
}

export function useSnapshots() {
    const snapshots = ref([]);
    const loading = ref(false);
    const error = ref(null);

    async function fetchSnapshots() {
        loading.value = true;
        error.value = null;

        try {
            const data = await api.getSnapshots();
            snapshots.value = data;
        } catch (e) {
            error.value = e.message || 'Failed to fetch snapshots';
            console.error('Error fetching snapshots:', e);
        } finally {
            loading.value = false;
        }
    }

    async function fetchSnapshot(name) {
        loading.value = true;
        error.value = null;

        try {
            return await api.getSnapshot(name);
        } catch (e) {
            error.value = e.message || 'Failed to fetch snapshot';
            console.error('Error fetching snapshot:', e);
            return null;
        } finally {
            loading.value = false;
        }
    }

    return {
        snapshots,
        loading,
        error,
        fetchSnapshots,
        fetchSnapshot,
    };
}
