# Changelog

All notable changes to `smart-migration` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added (v1.1.0 - Missing v1.0 Features)
- **`migrate:history` Command**: Visual timeline of all schema changes
  - Shows applied and pending migrations in chronological order
  - Displays batch information and migration metadata
  - Supports `--json` output for programmatic access
  - Supports `--reverse` to show oldest first
  - Supports `--limit` to control number of migrations shown
  - Shows version/tag information from migration docblocks

- **`migrate:test` Command**: Test migrations on temporary database
  - Tests migrations in isolated environment before production
  - Automatic test database setup and teardown
  - Tests both forward migration and rollback
  - Captures pre/post migration state for comparison
  - Runs integrity checks and detects schema changes
  - Supports `--with-data` to seed test database
  - Supports `--keep` to preserve test database for inspection
  - Supports `--rollback` to test down() methods
  - Works with SQLite `:memory:` for fast testing

- **`migrate:conflicts` Command**: Detect migration conflicts
  - Detects duplicate table creations
  - Detects modifications before table creation
  - Detects concurrent modifications to same table
  - Detects create-after-drop patterns
  - Provides detailed conflict analysis and recommendations
  - Supports `--json` output for CI/CD integration
  - Supports `--auto-resolve` flag (manual resolution still required for most conflicts)
  - Shows impact and resolution strategies for each conflict type

### Technical Improvements
- Enhanced service provider with 3 new command registrations
- Improved migration analysis for conflict detection
- Better error messages and user guidance for test setup

## [1.0.0] - 2025-01-29

### 🎉 Stable Release

This marks the first stable release of Smart Migration with production-ready features and comprehensive testing.

### Added
- **Auto-Diff Command** (`migrate:diff`): Automatically generate migrations from database changes
  - Smart column rename detection using Levenshtein algorithm
  - Auto-increment detection across all database types
  - Decimal precision/scale extraction
  - Enum values extraction
  - `timestamps()` and `softDeletes()` detection
  - FULLTEXT and SPATIAL index support
  - Foreign key constraint handling
  - Dry-run mode with `--dry-run` flag
  - Force generation with `--force` flag
  - Custom migration name with `--name` option
  - Table filtering with `--tables` option
  - Version mismatch warnings with `--ignore-version-mismatch` flag

- **Snapshot Format Versioning**: Prevent false positives when upgrading package
  - Added `CURRENT_FORMAT_VERSION` constant (1.0.0) to SnapshotManager
  - Snapshots now include `format_version` in metadata
  - Automatic version mismatch detection when loading old snapshots
  - User-friendly warnings when snapshot format differs from current version
  - `--ignore-version-mismatch` flag for both `migrate:diff` and `migrate:check` commands
  - Prevents false drift detection after package upgrades

### Improvements
- **Enhanced Test Coverage**: 592 tests with 100% code coverage
- **Production Stability**: All core features thoroughly tested and stable
- **Documentation**: Comprehensive README and command reference

### Technical Details
- Improved schema comparison algorithms
- Better type normalization across database engines
- Enhanced error handling and user feedback
- Optimized performance for large schemas

## [0.3.0] - 2025-01-28

### Added
- **PostgreSQL Support**: Full support for PostgreSQL databases with dedicated adapter
- **SQLite Support**: Full support for SQLite databases with dedicated adapter
- **Database Abstraction Layer**: Flexible adapter pattern for multiple database engines
- **Drift Detection Command** (`migrate:check`): Detect schema differences between migrations and database
  - Auto-generate fix migrations with `--fix` flag
  - Detailed comparison with `--details` flag
  - Configurable ignored tables and columns
- **Schema Snapshot Command** (`migrate:snapshot`): Version control for database schemas
  - Create, list, show, compare, and delete snapshots
  - Automatic snapshot rotation based on max_snapshots config
  - Include row counts in snapshots
- **Auto-cleanup System** (`migrate:cleanup`): Automatic cleanup of archived data
  - Scheduled cleanup via Laravel's task scheduler
  - Configurable retention periods
  - Dry-run mode for preview
  - Statistics view with `--stats` flag
- **Configuration Command** (`migrate:config`): Display current configuration
- **Comprehensive Configuration System**: Full configuration file with:
  - Safety settings
  - Backup configuration
  - Archive settings
  - Risk assessment rules
  - Display preferences
  - Database driver settings
  - Snapshot configuration
  - Drift detection settings
  - Notification settings
  - Performance tuning
- **Laravel 12 Compatibility**: Full support and testing with Laravel 12
- **Notification System**: Support for Slack and webhook notifications
- **Scheduled Jobs**: Background job for automatic archive cleanup

### Changed
- Improved SafeMigrator to use database adapters
- Enhanced backup system with configurable formats and compression
- Updated all commands to use the new configuration system
- Improved error handling and logging throughout
- Made `resolveMigration()` method public for Laravel 12 compatibility

### Fixed
- Laravel 12 compatibility issues with protected Migrator methods
- DateTime formatting issues (replaced `toIso8601String()` with `format('c')`)
- Force flag not working properly in confirmation prompts

### Developer Experience
- Added comprehensive test coverage for all new features
- Created tests for configuration, adapters, and all new commands
- Updated CLAUDE.md documentation for development workflow
- Improved error messages and user feedback

## [0.2.0] - 2025-01-20

### Added
- Initial implementation of core commands
- Basic test structure

## [0.1.0] - 2025-01-15

### Added
- **Core Commands**:
  - `migrate:plan`: Preview migrations with SQL and impact analysis
  - `migrate:safe`: Run migrations with automatic backups
  - `migrate:undo`: Safe rollback with data archiving
- **Risk Assessment**: Automatic classification of operations (SAFE/WARNING/DANGER)
- **Time Estimation**: Predict migration duration
- **Data Archiving**: Preserve data instead of dropping during rollbacks
- **Automatic Backups**: Create backups before risky operations
- **Full Test Coverage**: Comprehensive test suite

### Initial Release
- Proof of Concept implementation
- Basic Laravel 11 support
- MySQL support only