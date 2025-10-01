<?php

use Flux\Database\Adapters\MySQLAdapter;
use Flux\Database\Adapters\PostgreSQLAdapter;
use Flux\Database\Adapters\SQLiteAdapter;
use Flux\Database\DatabaseAdapterFactory;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create a new factory instance for each test
    $this->factory = new DatabaseAdapterFactory;
});

afterEach(function () {
    Mockery::close();
});

it('creates mysql adapter', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    // Mock both calls - for factory and for supports() method
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable MySQL
    config(['smart-migration.drivers.mysql.enabled' => true]);

    $adapter = $this->factory->create();

    expect($adapter)->toBeInstanceOf(MySQLAdapter::class);
});

it('creates postgresql adapter', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');

    // Mock both calls - for factory and for supports() method
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable PostgreSQL
    config(['smart-migration.drivers.pgsql.enabled' => true]);

    $adapter = $this->factory->create();

    expect($adapter)->toBeInstanceOf(PostgreSQLAdapter::class);
});

it('creates sqlite adapter', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlite');

    // Mock both calls - for factory and for supports() method
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable SQLite
    config(['smart-migration.drivers.sqlite.enabled' => true]);

    $adapter = $this->factory->create();

    expect($adapter)->toBeInstanceOf(SQLiteAdapter::class);
});

it('creates adapter for specific connection', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    // Need to mock both calls - one for specific connection and one for supports() check
    DB::shouldReceive('connection')->with('testing')->andReturn($connection);
    DB::shouldReceive('connection')->with()->andReturn($connection);

    // Mock config to enable MySQL
    config(['smart-migration.drivers.mysql.enabled' => true]);

    $adapter = $this->factory->create('testing');

    expect($adapter)->toBeInstanceOf(MySQLAdapter::class);
});

it('throws exception for unsupported driver', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mongodb');

    // Mock both calls - for factory and for supports() method
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable the driver (but adapter doesn't exist)
    config(['smart-migration.drivers.mongodb.enabled' => true]);

    expect(fn () => $this->factory->create())
        ->toThrow(\Exception::class, 'No adapter available for database driver: mongodb');
});

it('throws exception for disabled driver', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    // Mock both calls - for factory and for supports() method
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to disable MySQL
    config(['smart-migration.drivers.mysql.enabled' => false]);

    expect(fn () => $this->factory->create())
        ->toThrow(\Exception::class, "Database driver 'mysql' is not enabled in Smart Migration configuration");
});

it('caches adapter instance for default connection', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    // Mock both calls
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable MySQL
    config(['smart-migration.drivers.mysql.enabled' => true]);

    $adapter1 = $this->factory->create();
    $adapter2 = $this->factory->create();

    expect($adapter1)->toBe($adapter2);
});

it('does not cache adapter for specific connection', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    // Mock both calls
    DB::shouldReceive('connection')->with('testing')->andReturn($connection);
    DB::shouldReceive('connection')->with()->andReturn($connection);

    // Mock config to enable MySQL
    config(['smart-migration.drivers.mysql.enabled' => true]);

    $adapter1 = $this->factory->create('testing');
    $adapter2 = $this->factory->create('testing');

    expect($adapter1)->not->toBe($adapter2);
});

it('can register custom adapter', function () {
    // Create a mock adapter class
    $customAdapter = new class extends \Flux\Database\DatabaseAdapter
    {
        public function supports(): bool
        {
            return true;
        }

        public function getDriverName(): string
        {
            return 'custom';
        }

        public function getAllTables(): array
        {
            return [];
        }

        public function getTableColumns(string $table): array
        {
            return [];
        }

        public function getTableIndexes(string $table): array
        {
            return [];
        }

        public function getTableForeignKeys(string $table): array
        {
            return [];
        }

        public function getTableRowCount(string $table): int
        {
            return 0;
        }

        public function getTableData(string $table, int $limit = 1000): array
        {
            return [];
        }

        public function getTableStructure(string $table): string
        {
            return '';
        }

        public function archiveTable(string $table, string $newName): bool
        {
            return true;
        }

        public function archiveColumn(string $table, string $column, string $newName): bool
        {
            return true;
        }

        public function getCreateTableSQL(string $table, array $columns): string
        {
            return '';
        }

        public function getDropTableSQL(string $table): string
        {
            return '';
        }

        public function getAddColumnSQL(string $table, string $column, string $type): string
        {
            return '';
        }

        public function getDropColumnSQL(string $table, string $column): string
        {
            return '';
        }

        public function getRenameColumnSQL(string $table, string $oldName, string $newName): string
        {
            return '';
        }

        public function getCreateIndexSQL(string $table, string $indexName, array $columns): string
        {
            return '';
        }

        public function getDropIndexSQL(string $table, string $indexName): string
        {
            return '';
        }

        public function getRenameTableSQL(string $oldName, string $newName): string
        {
            return '';
        }

        public function quoteIdentifier(string $identifier): string
        {
            return $identifier;
        }
    };

    $this->factory->registerAdapter('custom', get_class($customAdapter));

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('custom');

    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable custom driver
    config(['smart-migration.drivers.custom.enabled' => true]);

    $adapter = $this->factory->create();

    expect($adapter)->toBeInstanceOf(get_class($customAdapter));
});

it('can clear cache', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    // Mock both calls
    DB::shouldReceive('connection')->andReturn($connection);

    // Mock config to enable MySQL
    config(['smart-migration.drivers.mysql.enabled' => true]);

    $adapter1 = $this->factory->create();
    $this->factory->clearCache();
    $adapter2 = $this->factory->create();

    expect($adapter1)->not->toBe($adapter2);
});

it('implements DatabaseAdapterFactoryInterface', function () {
    expect($this->factory)->toBeInstanceOf(DatabaseAdapterFactoryInterface::class);
});

it('throws exception when adapter does not support connection', function () {
    // Create a custom adapter that returns false for supports()
    $unsupportedAdapter = new class extends \Flux\Database\DatabaseAdapter
    {
        public function supports(): bool
        {
            return false;
        }

        public function getDriverName(): string
        {
            return 'testunsupported';
        }

        public function getAllTables(): array
        {
            return [];
        }

        public function getTableColumns(string $table): array
        {
            return [];
        }

        public function getTableIndexes(string $table): array
        {
            return [];
        }

        public function getTableForeignKeys(string $table): array
        {
            return [];
        }

        public function getTableRowCount(string $table): int
        {
            return 0;
        }

        public function getTableData(string $table, int $limit = 1000): array
        {
            return [];
        }

        public function getTableStructure(string $table): string
        {
            return '';
        }

        public function archiveTable(string $table, string $newName): bool
        {
            return true;
        }

        public function archiveColumn(string $table, string $column, string $newName): bool
        {
            return true;
        }

        public function getCreateTableSQL(string $table, array $columns): string
        {
            return '';
        }

        public function getDropTableSQL(string $table): string
        {
            return '';
        }

        public function getAddColumnSQL(string $table, string $column, string $type): string
        {
            return '';
        }

        public function getDropColumnSQL(string $table, string $column): string
        {
            return '';
        }

        public function getRenameColumnSQL(string $table, string $oldName, string $newName): string
        {
            return '';
        }

        public function getCreateIndexSQL(string $table, string $indexName, array $columns): string
        {
            return '';
        }

        public function getDropIndexSQL(string $table, string $indexName): string
        {
            return '';
        }

        public function getRenameTableSQL(string $oldName, string $newName): string
        {
            return '';
        }

        public function quoteIdentifier(string $identifier): string
        {
            return $identifier;
        }
    };

    $this->factory->registerAdapter('testunsupported', get_class($unsupportedAdapter));

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('testunsupported');
    DB::shouldReceive('connection')->andReturn($connection);
    config(['smart-migration.drivers.testunsupported.enabled' => true]);

    expect(fn () => $this->factory->create())
        ->toThrow(\Exception::class, 'Adapter does not support the current database connection');
});

it('throws exception when registering invalid adapter class', function () {
    expect(fn () => $this->factory->registerAdapter('invalid', 'NotAnAdapter'))
        ->toThrow(\Exception::class, 'Adapter must extend '.Flux\Database\DatabaseAdapter::class);
});

it('returns all registered adapters', function () {
    $adapters = $this->factory->getAdapters();

    expect($adapters)->toBeArray();
    expect($adapters)->toHaveKey('mysql');
    expect($adapters)->toHaveKey('pgsql');
    expect($adapters)->toHaveKey('sqlite');
    expect($adapters['mysql'])->toBe(MySQLAdapter::class);
});

it('checks if adapter is available for driver', function () {
    expect($this->factory->hasAdapter('mysql'))->toBeTrue();
    expect($this->factory->hasAdapter('pgsql'))->toBeTrue();
    expect($this->factory->hasAdapter('sqlite'))->toBeTrue();
    expect($this->factory->hasAdapter('nonexistent'))->toBeFalse();
});

it('can use static create method', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    DB::shouldReceive('connection')->andReturn($connection);
    config(['smart-migration.drivers.mysql.enabled' => true]);

    // Test the static create by setting up a custom factory that forces static behavior
    $factory = new class extends DatabaseAdapterFactory
    {
        public static function createStatic(?string $connection = null): \Flux\Database\DatabaseAdapter
        {
            // Skip DI check and go directly to static implementation
            if (self::$staticInstance !== null && $connection === null) {
                return self::$staticInstance;
            }

            $driver = $connection
                ? DB::connection($connection)->getDriverName()
                : DB::connection()->getDriverName();

            if (! \Flux\Config\SmartMigrationConfig::isDriverEnabled($driver)) {
                throw new \Exception("Database driver '{$driver}' is not enabled in Smart Migration configuration");
            }

            if (! isset(self::$staticAdapters[$driver])) {
                throw new \Exception("No adapter available for database driver: {$driver}");
            }

            $adapterClass = self::$staticAdapters[$driver];
            $adapter = new $adapterClass;

            if (! $adapter->supports()) {
                throw new \Exception('Adapter does not support the current database connection');
            }

            if ($connection === null) {
                self::$staticInstance = $adapter;
            }

            return $adapter;
        }
    };

    $adapter = $factory::createStatic();

    expect($adapter)->toBeInstanceOf(MySQLAdapter::class);
});

it('static create method uses DI container when available', function () {
    // Mock the app container
    $mockFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
    $mockAdapter = Mockery::mock(MySQLAdapter::class);
    $mockFactory->shouldReceive('create')->with(null)->andReturn($mockAdapter);

    // Mock app to return true for bound check and return our mock factory
    app()->instance(DatabaseAdapterFactoryInterface::class, $mockFactory);

    $adapter = DatabaseAdapterFactory::createStatic();

    expect($adapter)->toBe($mockAdapter);

    // Clean up
    app()->forgetInstance(DatabaseAdapterFactoryInterface::class);
});

it('static create method caches instances', function () {
    // Clear DI container to force static implementation
    app()->forgetInstance(DatabaseAdapterFactoryInterface::class);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    DB::shouldReceive('connection')->andReturn($connection);
    config(['smart-migration.drivers.mysql.enabled' => true]);

    // Clear any existing static cache
    DatabaseAdapterFactory::clearStaticCache();

    $adapter1 = DatabaseAdapterFactory::createStatic();
    $adapter2 = DatabaseAdapterFactory::createStatic();

    expect($adapter1)->toBe($adapter2);
});

it('static create method throws exception for disabled driver', function () {
    // Clear DI container to force static implementation
    app()->forgetInstance(DatabaseAdapterFactoryInterface::class);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    DB::shouldReceive('connection')->andReturn($connection);
    config(['smart-migration.drivers.mysql.enabled' => false]);

    // Clear any existing static cache
    DatabaseAdapterFactory::clearStaticCache();

    expect(fn () => DatabaseAdapterFactory::createStatic())
        ->toThrow(\Exception::class, "Database driver 'mysql' is not enabled in Smart Migration configuration");
});

it('static create method throws exception for unsupported driver', function () {
    // Clear DI container to force static implementation
    app()->forgetInstance(DatabaseAdapterFactoryInterface::class);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mongodb');

    DB::shouldReceive('connection')->andReturn($connection);
    config(['smart-migration.drivers.mongodb.enabled' => true]);

    // Clear any existing static cache
    DatabaseAdapterFactory::clearStaticCache();

    expect(fn () => DatabaseAdapterFactory::createStatic())
        ->toThrow(\Exception::class, 'No adapter available for database driver: mongodb');
});

it('can register static adapter', function () {
    $customAdapter = new class extends \Flux\Database\DatabaseAdapter
    {
        public function supports(): bool
        {
            return true;
        }

        public function getDriverName(): string
        {
            return 'staticcustom';
        }

        public function getAllTables(): array
        {
            return [];
        }

        public function getTableColumns(string $table): array
        {
            return [];
        }

        public function getTableIndexes(string $table): array
        {
            return [];
        }

        public function getTableForeignKeys(string $table): array
        {
            return [];
        }

        public function getTableRowCount(string $table): int
        {
            return 0;
        }

        public function getTableData(string $table, int $limit = 1000): array
        {
            return [];
        }

        public function getTableStructure(string $table): string
        {
            return '';
        }

        public function archiveTable(string $table, string $newName): bool
        {
            return true;
        }

        public function archiveColumn(string $table, string $column, string $newName): bool
        {
            return true;
        }

        public function getCreateTableSQL(string $table, array $columns): string
        {
            return '';
        }

        public function getDropTableSQL(string $table): string
        {
            return '';
        }

        public function getAddColumnSQL(string $table, string $column, string $type): string
        {
            return '';
        }

        public function getDropColumnSQL(string $table, string $column): string
        {
            return '';
        }

        public function getRenameColumnSQL(string $table, string $oldName, string $newName): string
        {
            return '';
        }

        public function getCreateIndexSQL(string $table, string $indexName, array $columns): string
        {
            return '';
        }

        public function getDropIndexSQL(string $table, string $indexName): string
        {
            return '';
        }

        public function getRenameTableSQL(string $oldName, string $newName): string
        {
            return '';
        }

        public function quoteIdentifier(string $identifier): string
        {
            return $identifier;
        }
    };

    DatabaseAdapterFactory::register('staticcustom', get_class($customAdapter));

    $adapters = DatabaseAdapterFactory::getStaticAdapters();
    expect($adapters)->toHaveKey('staticcustom');
    expect($adapters['staticcustom'])->toBe(get_class($customAdapter));
});

it('static register throws exception for invalid adapter class', function () {
    expect(fn () => DatabaseAdapterFactory::register('invalid', 'NotAnAdapter'))
        ->toThrow(\Exception::class, 'Adapter must extend '.Flux\Database\DatabaseAdapter::class);
});

it('can get static adapters', function () {
    $adapters = DatabaseAdapterFactory::getStaticAdapters();

    expect($adapters)->toBeArray();
    expect($adapters)->toHaveKey('mysql');
    expect($adapters)->toHaveKey('pgsql');
    expect($adapters)->toHaveKey('sqlite');
});

it('can check if static adapter exists', function () {
    expect(DatabaseAdapterFactory::hasStaticAdapter('mysql'))->toBeTrue();
    expect(DatabaseAdapterFactory::hasStaticAdapter('pgsql'))->toBeTrue();
    expect(DatabaseAdapterFactory::hasStaticAdapter('sqlite'))->toBeTrue();
    expect(DatabaseAdapterFactory::hasStaticAdapter('nonexistent'))->toBeFalse();
});
