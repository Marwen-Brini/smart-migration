# Dependency Injection Refactoring Guide

## Overview
This document tracks the refactoring of static classes to dependency injection to improve testability and resolve test mock conflicts.

## Problem Statement
Current issues with static class approach:
- `Mockery::mock('overload:ClassName')` persists across tests causing failures
- Static methods on `DatabaseAdapterFactory` are difficult to mock properly
- Config and DB facade mocks interfere between tests
- 39-49 tests failing due to mock conflicts

## Refactoring Plan

### Phase 1: DatabaseAdapterFactory Refactoring

#### Current Implementation (Static)
```php
class DatabaseAdapterFactory
{
    public static function create(?string $connection = null): DatabaseAdapter
    {
        // Static implementation
    }
}
```

#### Target Implementation (Dependency Injection)
```php
interface DatabaseAdapterFactoryInterface
{
    public function create(?string $connection = null): DatabaseAdapter;
}

class DatabaseAdapterFactory implements DatabaseAdapterFactoryInterface
{
    public function create(?string $connection = null): DatabaseAdapter
    {
        // Instance implementation
    }
}
```

### Phase 2: Update Service Provider
Register factory in the service container:
```php
// FluxServiceProvider.php
$this->app->singleton(DatabaseAdapterFactoryInterface::class, DatabaseAdapterFactory::class);
```

### Phase 3: Update Classes Using Factory

#### Classes to Update:
1. **SnapshotManager**
   - Current: `DatabaseAdapterFactory::create()`
   - Target: Inject factory via constructor

2. **ArchiveCleanupService**
   - Current: `DatabaseAdapterFactory::create()`
   - Target: Inject factory via constructor

3. **CheckCommand**
   - Current: `DatabaseAdapterFactory::create()`
   - Target: Inject factory via constructor

4. **SnapshotCommand**
   - Current: `DatabaseAdapterFactory::create()`
   - Target: Inject factory via constructor

5. **SafeMigrator**
   - Current: `DatabaseAdapterFactory::create()`
   - Target: Inject factory via constructor

### Phase 4: Update Tests
Convert from overload mocks to constructor injection:
```php
// Before
Mockery::mock('overload:' . DatabaseAdapterFactory::class)
    ->shouldReceive('create')
    ->andReturn($mockAdapter);

// After
$mockFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
$mockFactory->shouldReceive('create')->andReturn($mockAdapter);
$service = new ServiceClass($mockFactory);
```

## Implementation Progress

### Completed
- [ ] Create DatabaseAdapterFactoryInterface
- [ ] Update DatabaseAdapterFactory to implement interface
- [ ] Register in service provider
- [ ] Update SnapshotManager
- [ ] Update ArchiveCleanupService
- [ ] Update CheckCommand
- [ ] Update SnapshotCommand
- [ ] Update SafeMigrator
- [ ] Update all related tests
- [ ] Remove static methods from factory
- [ ] Run full test suite

### Test Results
- Before: 39-49 failing tests
- After: (To be updated)

## Benefits
1. **Better Testability**: Mock injection instead of global overrides
2. **Test Isolation**: Each test gets its own mock instance
3. **Cleaner Architecture**: Follows SOLID principles
4. **Easier Maintenance**: Clear dependencies
5. **No Mock Conflicts**: Eliminates overload persistence issues

## Migration Guide for Developers

### Using the New Factory
```php
// Old way (static)
$adapter = DatabaseAdapterFactory::create();

// New way (injected)
class YourService
{
    public function __construct(
        private DatabaseAdapterFactoryInterface $adapterFactory
    ) {}

    public function yourMethod()
    {
        $adapter = $this->adapterFactory->create();
    }
}
```

### Testing with DI
```php
public function test_your_feature()
{
    $mockFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
    $mockAdapter = Mockery::mock(DatabaseAdapter::class);

    $mockFactory->shouldReceive('create')
        ->once()
        ->andReturn($mockAdapter);

    $service = new YourService($mockFactory);
    // Test your service
}
```

## Notes
- Maintain backward compatibility during transition
- Consider using Laravel's container resolution for commands
- Update documentation after completion