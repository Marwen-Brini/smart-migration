import axios from 'axios';

const api = axios.create({
    baseURL: '/api/smart-migration',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request interceptor
api.interceptors.request.use(
    (config) => {
        // Add any auth tokens or custom headers here if needed
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor
api.interceptors.response.use(
    (response) => {
        return response.data;
    },
    (error) => {
        // Handle errors globally
        console.error('API Error:', error);
        return Promise.reject(error);
    }
);

export default {
    // Status & Overview
    getStatus() {
        return api.get('/status');
    },

    // Migrations
    getMigrations() {
        return api.get('/migrations');
    },

    getMigration(migration) {
        return api.get(`/migrations/${migration}`);
    },

    // History
    getHistory() {
        return api.get('/history');
    },

    // Schema
    getSchema() {
        return api.get('/schema');
    },

    getTable(table) {
        return api.get(`/schema/table/${table}`);
    },

    // Drift
    getDrift() {
        return api.get('/drift');
    },

    // Snapshots
    getSnapshots() {
        return api.get('/snapshots');
    },

    getSnapshot(snapshot) {
        return api.get(`/snapshots/${snapshot}`);
    },

    // Metrics
    getMetrics() {
        return api.get('/metrics');
    },

    // Actions
    generateFixMigration() {
        return api.post('/drift/fix');
    },

    createSnapshot(name = null) {
        return api.post('/snapshots', { name });
    },

    deleteSnapshot(name) {
        return api.delete(`/snapshots/${name}`);
    },

    runMigrations(options = {}) {
        return api.post('/migrations/run', options);
    },

    rollbackMigrations(options = {}) {
        return api.post('/migrations/rollback', options);
    },
};
