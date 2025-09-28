<?php

namespace Flux\Tests\Unit\Database;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\Adapters\MySQLAdapter;
use Flux\Database\Adapters\PostgreSQLAdapter;
use Flux\Database\Adapters\SQLiteAdapter;
use Flux\Database\DatabaseAdapter;
use Flux\Database\DatabaseAdapterFactory;
use Flux\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;

class DatabaseAdapterFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached instances
        DatabaseAdapterFactory::clearCache();
    }

    public function test_creates_mysql_adapter()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');

        // Mock both calls - for factory and for supports() method
        DB::shouldReceive('connection')->andReturn($connection);

        // Mock config to enable MySQL
        config(['smart-migration.drivers.mysql.enabled' => true]);

        $adapter = DatabaseAdapterFactory::create();

        $this->assertInstanceOf(MySQLAdapter::class, $adapter);
    }

    public function test_creates_postgresql_adapter()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');

        // Mock both calls - for factory and for supports() method
        DB::shouldReceive('connection')->andReturn($connection);

        // Mock config to enable PostgreSQL
        config(['smart-migration.drivers.pgsql.enabled' => true]);

        $adapter = DatabaseAdapterFactory::create();

        $this->assertInstanceOf(PostgreSQLAdapter::class, $adapter);
    }

    public function test_creates_sqlite_adapter()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('sqlite');

        // Mock both calls - for factory and for supports() method
        DB::shouldReceive('connection')->andReturn($connection);

        // Mock config to enable SQLite
        config(['smart-migration.drivers.sqlite.enabled' => true]);

        $adapter = DatabaseAdapterFactory::create();

        $this->assertInstanceOf(SQLiteAdapter::class, $adapter);
    }

    public function test_creates_adapter_for_specific_connection()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');

        // Need to mock both calls - one for specific connection and one for supports() check
        DB::shouldReceive('connection')->with('testing')->andReturn($connection);
        DB::shouldReceive('connection')->with()->andReturn($connection);

        // Mock config to enable MySQL
        config(['smart-migration.drivers.mysql.enabled' => true]);

        $adapter = DatabaseAdapterFactory::create('testing');

        $this->assertInstanceOf(MySQLAdapter::class, $adapter);
    }

    public function test_throws_exception_for_unsupported_driver()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mongodb');

        // Mock both calls - for factory and for supports() method
        DB::shouldReceive('connection')->andReturn($connection);

        // Mock config to enable the driver (but adapter doesn't exist)
        config(['smart-migration.drivers.mongodb.enabled' => true]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("No adapter available for database driver: mongodb");

        DatabaseAdapterFactory::create();
    }

    public function test_throws_exception_for_disabled_driver()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');

        // Mock both calls - for factory and for supports() method
        DB::shouldReceive('connection')->andReturn($connection);

        // Mock config to disable MySQL
        config(['smart-migration.drivers.mysql.enabled' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Database driver 'mysql' is not enabled in Smart Migration configuration");

        DatabaseAdapterFactory::create();
    }
}