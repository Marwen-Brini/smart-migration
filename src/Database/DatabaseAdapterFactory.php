<?php

namespace Flux\Database;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\Adapters\MySQLAdapter;
use Flux\Database\Adapters\PostgreSQLAdapter;
use Flux\Database\Adapters\SQLiteAdapter;
use Illuminate\Support\Facades\DB;

class DatabaseAdapterFactory implements DatabaseAdapterFactoryInterface
{
    /**
     * Available adapters
     */
    protected array $adapters = [
        'mysql' => MySQLAdapter::class,
        'pgsql' => PostgreSQLAdapter::class,
        'sqlite' => SQLiteAdapter::class,
    ];

    /**
     * Static adapters for backward compatibility
     */
    protected static array $staticAdapters = [
        'mysql' => MySQLAdapter::class,
        'pgsql' => PostgreSQLAdapter::class,
        'sqlite' => SQLiteAdapter::class,
    ];

    /**
     * Cached adapter instance
     */
    protected ?DatabaseAdapter $instance = null;

    /**
     * Static cached instance for backward compatibility
     */
    protected static ?DatabaseAdapter $staticInstance = null;

    /**
     * Get the appropriate database adapter for the current connection (instance method)
     */
    public function create(?string $connection = null): DatabaseAdapter
    {
        if ($this->instance !== null && $connection === null) {
            return $this->instance;
        }

        $driver = $connection
            ? DB::connection($connection)->getDriverName()
            : DB::connection()->getDriverName();

        // Check if driver is enabled in config
        if (! SmartMigrationConfig::isDriverEnabled($driver)) {
            throw new \Exception("Database driver '{$driver}' is not enabled in Smart Migration configuration");
        }

        // Check if we have an adapter for this driver
        if (! isset($this->adapters[$driver])) {
            throw new \Exception("No adapter available for database driver: {$driver}");
        }

        $adapterClass = $this->adapters[$driver];
        $adapter = new $adapterClass;

        // Verify the adapter supports the connection
        if (! $adapter->supports()) {
            throw new \Exception('Adapter does not support the current database connection');
        }

        // Cache the instance if it's for the default connection
        if ($connection === null) {
            $this->instance = $adapter;
        }

        return $adapter;
    }

    /**
     * Static create method for backward compatibility
     *
     * @deprecated Use dependency injection instead
     *
     * @codeCoverageIgnore
     */
    public static function createStatic(?string $connection = null): DatabaseAdapter
    {
        // Use the singleton instance from the container if available
        if (app()->bound(DatabaseAdapterFactoryInterface::class)) {
            return app(DatabaseAdapterFactoryInterface::class)->create($connection);
        }

        // Fallback to static implementation
        if (self::$staticInstance !== null && $connection === null) {
            return self::$staticInstance;
        }

        $driver = $connection
            ? DB::connection($connection)->getDriverName()
            : DB::connection()->getDriverName();

        // Check if driver is enabled in config
        if (! SmartMigrationConfig::isDriverEnabled($driver)) {
            throw new \Exception("Database driver '{$driver}' is not enabled in Smart Migration configuration");
        }

        // Check if we have an adapter for this driver
        if (! isset(self::$staticAdapters[$driver])) {
            throw new \Exception("No adapter available for database driver: {$driver}");
        }

        $adapterClass = self::$staticAdapters[$driver];
        $adapter = new $adapterClass;

        // Verify the adapter supports the connection
        if (! $adapter->supports()) {
            throw new \Exception('Adapter does not support the current database connection');
        }

        // Cache the instance if it's for the default connection
        if ($connection === null) {
            self::$staticInstance = $adapter;
        }

        return $adapter;
    }

    /**
     * Register a custom adapter (instance method)
     */
    public function registerAdapter(string $driver, string $adapterClass): void
    {
        if (! is_subclass_of($adapterClass, DatabaseAdapter::class)) {
            throw new \Exception('Adapter must extend '.DatabaseAdapter::class);
        }

        $this->adapters[$driver] = $adapterClass;
    }

    /**
     * Clear cached instance
     */
    public function clearCache(): void
    {
        $this->instance = null;
    }

    /**
     * Get all registered adapters
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * Check if an adapter is available for a driver
     */
    public function hasAdapter(string $driver): bool
    {
        return isset($this->adapters[$driver]);
    }

    // Static methods for backward compatibility

    /**
     * @deprecated Use instance method via DI
     */
    public static function register(string $driver, string $adapterClass): void
    {
        if (! is_subclass_of($adapterClass, DatabaseAdapter::class)) {
            throw new \Exception('Adapter must extend '.DatabaseAdapter::class);
        }

        self::$staticAdapters[$driver] = $adapterClass;
    }

    /**
     * @deprecated Use instance method via DI
     */
    public static function clearStaticCache(): void
    {
        self::$staticInstance = null;
    }

    /**
     * @deprecated Use instance method via DI
     */
    public static function getStaticAdapters(): array
    {
        return self::$staticAdapters;
    }

    /**
     * @deprecated Use instance method via DI
     */
    public static function hasStaticAdapter(string $driver): bool
    {
        return isset(self::$staticAdapters[$driver]);
    }
}
