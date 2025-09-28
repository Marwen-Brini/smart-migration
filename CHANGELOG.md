# Changelog

All notable changes to `smart-migration` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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