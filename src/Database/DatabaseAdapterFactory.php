<?php

namespace Flux\Database;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\Adapters\MySQLAdapter;
use Flux\Database\Adapters\PostgreSQLAdapter;
use Flux\Database\Adapters\SQLiteAdapter;
use Illuminate\Support\Facades\DB;

class DatabaseAdapterFactory
{
    /**
     * Available adapters
     */
    protected static array $adapters = [
        'mysql' => MySQLAdapter::class,
        'pgsql' => PostgreSQLAdapter::class,
        'sqlite' => SQLiteAdapter::class,
    ];

    /**
     * Cached adapter instance
     */
    protected static ?DatabaseAdapter $instance = null;

    /**
     * Get the appropriate database adapter for the current connection
     */
    public static function create(?string $connection = null): DatabaseAdapter
    {
        if (self::$instance !== null && $connection === null) {
            return self::$instance;
        }

        $driver = $connection
            ? DB::connection($connection)->getDriverName()
            : DB::connection()->getDriverName();

        // Check if driver is enabled in config
        if (!SmartMigrationConfig::isDriverEnabled($driver)) {
            throw new \Exception("Database driver '{$driver}' is not enabled in Smart Migration configuration");
        }

        // Check if we have an adapter for this driver
        if (!isset(self::$adapters[$driver])) {
            throw new \Exception("No adapter available for database driver: {$driver}");
        }

        $adapterClass = self::$adapters[$driver];
        $adapter = new $adapterClass();

        // Verify the adapter supports the connection
        if (!$adapter->supports()) {
            throw new \Exception("Adapter does not support the current database connection");
        }

        // Cache the instance if it's for the default connection
        if ($connection === null) {
            self::$instance = $adapter;
        }

        return $adapter;
    }

    /**
     * Register a custom adapter
     */
    public static function register(string $driver, string $adapterClass): void
    {
        if (!is_subclass_of($adapterClass, DatabaseAdapter::class)) {
            throw new \Exception("Adapter must extend " . DatabaseAdapter::class);
        }

        self::$adapters[$driver] = $adapterClass;
    }

    /**
     * Get all registered adapters
     */
    public static function getAdapters(): array
    {
        return self::$adapters;
    }

    /**
     * Check if an adapter is available for a driver
     */
    public static function hasAdapter(string $driver): bool
    {
        return isset(self::$adapters[$driver]);
    }

    /**
     * Clear cached instance
     */
    public static function clearCache(): void
    {
        self::$instance = null;
    }
}