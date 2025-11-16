/**
 * Format a date to a human-readable string
 */
export function formatDate(date) {
    if (!date) return 'N/A';

    const d = new Date(date);
    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Format a relative time (e.g., "2 hours ago")
 */
export function formatRelativeTime(date) {
    if (!date) return 'N/A';

    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
    if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    return 'Just now';
}

/**
 * Format bytes to human-readable size
 */
export function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Format duration in milliseconds to human-readable string
 */
export function formatDuration(ms) {
    if (!ms) return '0ms';

    if (ms < 1000) return `${ms}ms`;
    if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
    if (ms < 3600000) return `${(ms / 60000).toFixed(1)}m`;
    return `${(ms / 3600000).toFixed(1)}h`;
}

/**
 * Format number with thousands separator
 */
export function formatNumber(num) {
    if (num === null || num === undefined) return 'N/A';
    return num.toLocaleString('en-US');
}

/**
 * Get risk level color class
 */
export function getRiskColorClass(risk) {
    const colors = {
        safe: 'text-green-600',
        warning: 'text-yellow-600',
        danger: 'text-red-600',
    };
    return colors[risk?.toLowerCase()] || 'text-gray-600';
}

/**
 * Get risk level background class
 */
export function getRiskBgClass(risk) {
    const colors = {
        safe: 'bg-green-100',
        warning: 'bg-yellow-100',
        danger: 'bg-red-100',
    };
    return colors[risk?.toLowerCase()] || 'bg-gray-100';
}

/**
 * Get risk level icon
 */
export function getRiskIcon(risk) {
    const icons = {
        safe: '✅',
        warning: '⚠️',
        danger: '🔴',
    };
    return icons[risk?.toLowerCase()] || '❓';
}

/**
 * Truncate string with ellipsis
 */
export function truncate(str, length = 50) {
    if (!str) return '';
    if (str.length <= length) return str;
    return str.substring(0, length) + '...';
}

/**
 * Format migration name (remove timestamp and underscores)
 */
export function formatMigrationName(name) {
    if (!name) return '';
    // Remove timestamp prefix (e.g., 2024_01_15_143022_)
    return name.replace(/^\d{4}_\d{2}_\d{2}_\d{6}_/, '')
               .replace(/_/g, ' ')
               .replace(/\b\w/g, l => l.toUpperCase());
}
