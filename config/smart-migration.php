<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smart Migration Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of Smart Migration package including safety
    | features, backup settings, and database-specific options.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Safety Features
    |--------------------------------------------------------------------------
    |
    | Configure safety-related settings for migrations.
    |
    */
    'safety' => [
        // Enable automatic backups before migrations
        'auto_backup' => env('SMART_MIGRATION_AUTO_BACKUP', true),

        // Enable safe rollback (archiving instead of dropping)
        'safe_rollback' => env('SMART_MIGRATION_SAFE_ROLLBACK', true),

        // Require confirmation in production
        'require_confirmation' => env('SMART_MIGRATION_REQUIRE_CONFIRM', true),

        // Allow destructive operations in production (drops, deletes)
        'allow_destructive_in_production' => env('SMART_MIGRATION_ALLOW_DESTRUCTIVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure how backups are created and managed.
    |
    */
    'backup' => [
        // Backup storage path (relative to storage/app)
        'path' => env('SMART_MIGRATION_BACKUP_PATH', 'smart-migration-backups'),

        // Maximum backup file size in MB (0 = unlimited)
        'max_size' => env('SMART_MIGRATION_MAX_BACKUP_SIZE', 100),

        // Backup format: 'sql', 'csv', 'json'
        'format' => env('SMART_MIGRATION_BACKUP_FORMAT', 'sql'),

        // Compress backup files
        'compress' => env('SMART_MIGRATION_COMPRESS_BACKUPS', true),

        // Backup retention in days (0 = keep forever)
        'retention_days' => env('SMART_MIGRATION_BACKUP_RETENTION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Archive Settings
    |--------------------------------------------------------------------------
    |
    | Configure how data is archived during safe rollbacks.
    |
    */
    'archive' => [
        // Archive table prefix
        'table_prefix' => env('SMART_MIGRATION_ARCHIVE_PREFIX', '_archived_'),

        // Archive column prefix
        'column_prefix' => env('SMART_MIGRATION_ARCHIVE_COLUMN_PREFIX', '__backup_'),

        // Include timestamp in archive names
        'include_timestamp' => env('SMART_MIGRATION_ARCHIVE_TIMESTAMP', true),

        // Archive retention in days (0 = keep forever)
        'retention_days' => env('SMART_MIGRATION_ARCHIVE_RETENTION', 7),

        // Auto cleanup archived data
        'auto_cleanup' => env('SMART_MIGRATION_AUTO_CLEANUP', false),

        // Cleanup schedule (cron expression)
        'cleanup_schedule' => env('SMART_MIGRATION_CLEANUP_SCHEDULE', '0 2 * * *'), // 2 AM daily
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Assessment
    |--------------------------------------------------------------------------
    |
    | Configure risk assessment thresholds and behaviors.
    |
    */
    'risk' => [
        // Table size thresholds (number of rows)
        'small_table' => 1000,
        'medium_table' => 10000,
        'large_table' => 100000,

        // Operation risk levels
        'operations' => [
            'create_table' => 'safe',
            'add_column' => 'safe',
            'add_index' => 'warning',
            'modify_column' => 'warning',
            'rename_column' => 'warning',
            'drop_column' => 'danger',
            'drop_table' => 'danger',
            'drop_index' => 'warning',
        ],

        // Block dangerous operations in production
        'block_danger_in_production' => env('SMART_MIGRATION_BLOCK_DANGER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    |
    | Configure how information is displayed in the CLI.
    |
    */
    'display' => [
        // Use colored output
        'colors' => env('SMART_MIGRATION_COLORS', true),

        // Show emojis in output
        'emojis' => env('SMART_MIGRATION_EMOJIS', true),

        // Show progress bars
        'progress_bars' => env('SMART_MIGRATION_PROGRESS', true),

        // Verbosity level: 'quiet', 'normal', 'verbose'
        'verbosity' => env('SMART_MIGRATION_VERBOSITY', 'normal'),

        // Show SQL queries
        'show_sql' => env('SMART_MIGRATION_SHOW_SQL', true),

        // Show execution time
        'show_timing' => env('SMART_MIGRATION_SHOW_TIMING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Drivers
    |--------------------------------------------------------------------------
    |
    | Configure database-specific settings for different drivers.
    |
    */
    'drivers' => [
        'mysql' => [
            'enabled' => true,
            'lock_timeout' => 30, // seconds
            'online_ddl' => true,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'pgsql' => [
            'enabled' => true,
            'lock_timeout' => 30, // seconds
            'statement_timeout' => 0, // milliseconds, 0 = no timeout
            'idle_in_transaction_timeout' => 0, // milliseconds
        ],

        'sqlite' => [
            'enabled' => true,
            'foreign_keys' => true,
            'journal_mode' => 'WAL',
        ],

        'sqlsrv' => [
            'enabled' => false,
            'lock_timeout' => 30000, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Settings
    |--------------------------------------------------------------------------
    |
    | Configure schema snapshot settings.
    |
    */
    'snapshots' => [
        // Snapshot storage path (relative to database/)
        'path' => 'snapshots',

        // Snapshot format: 'json', 'yaml', 'php'
        'format' => 'json',

        // Include data in snapshots (careful with large databases)
        'include_data' => false,

        // Auto-create snapshots after migrations
        'auto_snapshot' => env('SMART_MIGRATION_AUTO_SNAPSHOT', false),

        // Keep maximum number of snapshots (0 = unlimited)
        'max_snapshots' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Drift Detection
    |--------------------------------------------------------------------------
    |
    | Configure drift detection settings.
    |
    */
    'drift' => [
        // Enable automatic drift detection
        'auto_detect' => env('SMART_MIGRATION_AUTO_DRIFT_DETECT', true),

        // Check for drift before migrations
        'check_before_migrate' => env('SMART_MIGRATION_CHECK_DRIFT', true),

        // Warn about drift but continue
        'warn_only' => env('SMART_MIGRATION_DRIFT_WARN_ONLY', true),

        // Ignored tables for drift detection (regex patterns)
        'ignored_tables' => [
            'migrations',
            'password_resets',
            'password_reset_tokens',
            'failed_jobs',
            'personal_access_tokens',
            'telescope_*',
            'horizon_*',
        ],

        // Ignored columns for drift detection
        'ignored_columns' => [
            'created_at',
            'updated_at',
            'deleted_at',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notifications for migration events.
    |
    */
    'notifications' => [
        // Enable notifications
        'enabled' => env('SMART_MIGRATION_NOTIFICATIONS', false),

        // Notification channels: 'mail', 'slack', 'discord', 'webhook'
        'channels' => explode(',', env('SMART_MIGRATION_NOTIFY_CHANNELS', 'mail')),

        // Events to notify about
        'events' => [
            'migration_started' => true,
            'migration_completed' => true,
            'migration_failed' => true,
            'rollback_started' => true,
            'rollback_completed' => true,
            'drift_detected' => true,
            'backup_created' => false,
            'archive_created' => false,
        ],

        // Webhook URL for notifications
        'webhook_url' => env('SMART_MIGRATION_WEBHOOK_URL'),

        // Slack webhook URL
        'slack_webhook' => env('SMART_MIGRATION_SLACK_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings.
    |
    */
    'performance' => [
        // Chunk size for batch operations
        'chunk_size' => 1000,

        // Memory limit for operations (MB)
        'memory_limit' => 512,

        // Maximum execution time (seconds, 0 = unlimited)
        'max_execution_time' => 300,

        // Enable query caching
        'enable_cache' => true,

        // Cache TTL in seconds
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure how migration activities are logged.
    |
    */
    'logging' => [
        // Enable detailed logging
        'enabled' => env('SMART_MIGRATION_LOGGING', true),

        // Log channel to use
        'channel' => env('SMART_MIGRATION_LOG_CHANNEL', 'stack'),

        // Log level: 'debug', 'info', 'warning', 'error'
        'level' => env('SMART_MIGRATION_LOG_LEVEL', 'info'),

        // Log SQL queries
        'log_queries' => env('SMART_MIGRATION_LOG_QUERIES', false),

        // Log file path (relative to storage/logs)
        'file' => 'smart-migration.log',
    ],
];
