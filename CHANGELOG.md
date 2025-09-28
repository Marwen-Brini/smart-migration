# Changelog

All notable changes to `smart-migration` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2025-09-28

### 🚀 Enhanced User Experience Release

This release focuses on improving the developer experience with better visual output, progress tracking, and Laravel 11 compatibility fixes.

### Added
- **Colored CLI Output** - Enhanced visual feedback across all commands
  - Risk-based color coding (green for safe, yellow for warning, red for danger)
  - Emojis for better visual recognition of operations
  - Professional box drawing characters for borders
  - Syntax highlighting for SQL commands
  - Colored status messages and progress indicators

- **Progress Bars** - Visual progress tracking for batch operations
  - Dynamic progress bars for multiple migration execution
  - Real-time status updates during migration processing
  - Progress tracking for batch rollback operations
  - Percentage completion and ETA display
  - Graceful fallback for single operations

### Fixed
- **Laravel 11 Compatibility** - Full support for anonymous migration classes
  - Added `resolveMigration()` method to handle both named and anonymous classes
  - Fixed transaction handling issues with proper commit/rollback logic
  - Updated method calls from deprecated `note()` to `write()`
  - Improved error handling for migration class resolution

### Improved
- **Visual Feedback** - Better command output formatting
  - Clear operation status with colored indicators
  - Structured output with proper spacing and borders
  - Enhanced error messages with contextual information
  - Better separation between different operations
  - Improved readability of migration plans and results

- **User Experience** - Smoother interaction flow
  - More intuitive confirmation prompts
  - Better progress indication for long-running operations
  - Clearer success/failure messages
  - Improved formatting of data loss warnings

### Technical Details
- Updated dependencies for better compatibility
- Improved error handling in SafeMigrator
- Better transaction state management
- Enhanced visual styling using Laravel's console output methods

## [0.1.0] - 2025-09-28

### 🎉 Initial POC Release

This is the Proof of Concept release of Smart Migration Package for Laravel, demonstrating the core value proposition of safe, visible, and confident database migrations.

### Added

#### Core Commands
- **`migrate:plan`** - Preview migrations before execution
  - Parse migration files and extract operations
  - Generate SQL preview for each operation
  - Risk assessment with three levels (SAFE/WARNING/DANGER)
  - Impact analysis showing affected row counts
  - Time estimation for migration duration
  - Support for create, drop, rename table operations
  - Support for add, drop, rename column operations
  - Support for index and foreign key operations

- **`migrate:safe`** - Run migrations with safety features
  - Automatic backup of affected tables before migration
  - Transaction wrapping for atomic operations
  - Automatic rollback on failure with data restoration
  - Data loss warnings before execution
  - Interactive confirmation prompts
  - Production environment protection
  - Progress tracking with timing information
  - Integration with migration analyzer for risk display

- **`migrate:undo`** - Safe rollback without data loss
  - Non-destructive rollback using archival strategy
  - Tables renamed with timestamp suffix instead of dropping
  - Columns renamed with timestamp suffix instead of dropping
  - Preserved data retention for 7 days
  - Rollback plan preview before execution
  - Support for batch and step-based rollbacks
  - Clear indication of archived items after rollback

#### Core Components
- **MigrationAnalyzer** - Parsing and analysis engine
  - Regex-based migration file parsing
  - Operation extraction and categorization
  - Risk level assessment logic
  - SQL generation for operations
  - Impact calculation with row counts
  - Duration estimation algorithms

- **SafeMigrator** - Enhanced migrator with safety features
  - Extends Laravel's base Migrator class
  - Table and data backup functionality
  - Transactional migration execution
  - Automatic restore on failure
  - Archive-based rollback system
  - Data loss estimation

#### Testing
- Full test coverage for all commands
- Command registration tests
- Migration analysis tests
- Production safety tests
- CI/CD with GitHub Actions
- Support for Laravel 11 and 12 in test matrix

#### Documentation
- Comprehensive README with examples
- Command reference documentation
- Quick start guide with real output examples
- Installation and requirements documentation
- Roadmap for future development

### Technical Details
- **Laravel Compatibility**: 11.x and 12.x
- **PHP Compatibility**: 8.3 and 8.4
- **Database Support**: MySQL 5.7+
- **Dependencies**:
  - spatie/laravel-package-tools ^1.16
  - illuminate/support ^11.0 || ^12.0
  - illuminate/contracts ^11.0 || ^12.0

### Known Limitations (POC)
- MySQL only (PostgreSQL coming in v0.2.0)
- Basic CLI output (colored output coming in v0.2.0)
- No configuration file (coming in v0.2.0)
- Limited to local migrations (drift detection coming in v0.2.0)
- Manual cleanup of archived data (auto-cleanup coming in v0.2.0)

### Contributors
- Marwen Brini (@Marwen-Brini) - Initial implementation

---

**Note**: This is a Proof of Concept release. While fully functional, it's recommended for development environments only until the stable v1.0.0 release.