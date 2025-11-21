# Claude Code Development Guide for Smart Migration Package

## Test Laravel Projects

For testing the Smart Migration Package, use these Laravel projects:

- **payshuttle-api**: Laravel 11 project located at `../payshuttle-api/`
- **accounting-api**: Laravel 12 project located at `../accounting-api/` ✅ Wired up and tested

## Database Configuration for Testing

The `accounting-api` project is configured to test against multiple database drivers. Switch between them by updating the `.env` file:

### MySQL (default - database: s35)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=s35
DB_USERNAME=marwen
DB_PASSWORD=Marwanism123
```

### PostgreSQL (database: s11)
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=s11
DB_USERNAME=marwen
DB_PASSWORD=Marwanism123
```

### SQLite (database: database/database.sqlite)
```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

**Note**: We frequently switch between MySQL, PostgreSQL, and SQLite to ensure full multi-database compatibility. Always test features across all three drivers before considering them complete.

## Testing Commands

When testing Smart Migration commands, navigate to the appropriate Laravel project:

```bash
# For Laravel 11 testing
cd ../payshuttle-api
php artisan migrate:plan
php artisan migrate:safe
php artisan migrate:undo
php artisan migrate:check
php artisan migrate:snapshot

# For Laravel 12 testing (if accounting-api is available)
cd ../accounting-api
# Run same commands
```

## Development Workflow

1. Make changes in the `smart-migration` package directory
2. Test changes in `payshuttle-api` (Laravel 11)
3. Test changes in `accounting-api` (Laravel 12) if available
4. Ensure compatibility with both Laravel versions

## Package Installation in Test Projects

To install the local development version in test projects:

```bash
# In payshuttle-api or accounting-api
composer require marwen-brini/smart-migration --dev
# Or for local development
composer config repositories.smart-migration path ../smart-migration
composer require marwen-brini/smart-migration:@dev
```

## Current Version

Working on v0.3.0 (MVP) with features:
- PostgreSQL support ✅
- Configuration file system ✅
- migrate:check (drift detection) ✅
- migrate:snapshot (schema versioning) ✅
- migrate:diff (auto-generate migrations from DB changes) ✅
- Auto-cleanup for archived data ✅

### Recent Additions (v0.3.0)
- **migrate:diff** - Comprehensive auto-diff feature with:
  - Smart column rename detection (Levenshtein algorithm)
  - Auto-increment detection for all databases
  - Decimal precision/scale extraction
  - Enum values extraction
  - timestamps() and softDeletes() detection
  - FULLTEXT and SPATIAL index support
  - Foreign key constraint handling

## Known Issues & Upcoming Work

See **[KNOWN_ISSUES.md](./KNOWN_ISSUES.md)** for:
- Snapshot format versioning (Medium-term fix planned for next PR)
- Type normalization layer (Medium-term improvement)
- Detailed workarounds and solutions

## Test Fixing Documentation

### Comprehensive Test Architecture Overhaul ✅ COMPLETED
- **[COMPREHENSIVE_TEST_FIX_DOCUMENTATION.md](./COMPREHENSIVE_TEST_FIX_DOCUMENTATION.md)** - Complete guide to the dependency injection refactoring that reduced test failures from 39-49 to 31 through systematic elimination of mock conflicts
- **[REFACTORING_DI.md](./REFACTORING_DI.md)** - Detailed dependency injection refactoring guide and progress tracking

### Key Results Achieved
- ✅ **37-42% reduction in test failures** (39-49 → 31 stable failures)
- ✅ **100% elimination of overload mock conflicts** (no more `Mockery::mock('overload:')`)
- ✅ **Complete dependency injection architecture** implemented across all 8 classes
- ✅ **Architectural consistency** established between classes and tests
- ✅ **MigrationAnalyzerTest completely fixed** (9/9 tests passing)

### Next Steps When Resuming Work
1. **Priority 1**: Fix CleanupCommandTest output method call mismatches (`line()`, `newLine()` count issues)
2. **Priority 2**: Fix SafeMigratorTest adapter factory injection issues
3. **Priority 3**: Address remaining 31 isolated mock expectation issues

### Test Commands
```bash
# Check overall progress
composer test

# Focus on specific remaining issues
composer test tests/Unit/Commands/CleanupCommandTest.php
composer test tests/Unit/Safety/SafeMigratorTest.php
```