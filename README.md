# 🛡️ Smart Migration Package for Laravel

[![Latest Version](https://img.shields.io/badge/version-v2.0.0--dev-blue?style=flat-square)](https://github.com/marwen-brini/smart-migration)
[![PHP Version](https://img.shields.io/badge/php-8.3%20|%208.4-777BB4?style=flat-square&logo=php)](https://github.com/marwen-brini/smart-migration)
[![Laravel](https://img.shields.io/badge/laravel-11%20|%2012-FF2D20?style=flat-square&logo=laravel)](https://github.com/marwen-brini/smart-migration)
[![Tests](https://img.shields.io/badge/tests-592%20passing-brightgreen?style=flat-square)](https://github.com/marwen-brini/smart-migration)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen?style=flat-square)](https://github.com/marwen-brini/smart-migration)
[![License](https://img.shields.io/badge/license-MIT-brightgreen?style=flat-square)](LICENSE.md)

**Never fear migrations again!** Smart Migration provides safety, visibility, and confidence when running Laravel migrations. Preview changes before they happen, automatically backup data, and rollback without data loss.

> **🚀 Current Version**: v2.0.0-dev - Now with Web Dashboard! 🎉
>
> Beautiful Vue 3 dashboard for visual migration management, real-time monitoring, and interactive schema exploration.

## ✨ Features

### Core Safety Features
- 🔍 **Preview migrations** - See exact SQL and impact before running
- 🛡️ **Automatic backups** - Never lose data during migrations
- ↩️ **Safe rollbacks** - Archive instead of dropping data
- ⚠️ **Risk assessment** - Know which operations are dangerous
- ⏱️ **Time estimation** - Understand how long migrations will take

### Advanced Features
- 🐘 **Multi-Database Support** - Full support for MySQL, PostgreSQL, and SQLite
- 🔎 **Drift Detection** - Detect schema differences between migrations and database
- 📸 **Schema Snapshots** - Version control for your database schema with format versioning
- 🧹 **Auto-cleanup** - Automatic cleanup of old archived data
- ⚙️ **Configuration System** - Comprehensive configuration options
- 🔄 **Database Abstraction** - Unified interface across different database engines
- ✨ **Auto-Diff** - Automatically generate migrations from database changes
- 🔖 **Snapshot Versioning** - Prevent false positives when upgrading package versions

### 🎨 Web Dashboard (NEW!)
- 📊 **Visual Migration Management** - Beautiful web interface for managing migrations
- 🔄 **Real-time Monitoring** - Auto-refreshing dashboard with live status updates
- 🗂️ **Interactive Schema Explorer** - Browse and search your database structure
- 📈 **Performance Metrics** - Charts and graphs for migration performance
- ⏱️ **Migration Timeline** - Visual history of all applied migrations
- ⚠️ **Drift Alerts** - Visual warnings when schema diverges from migrations
- 🎯 **Risk Indicators** - Color-coded risk levels for pending migrations

## 📦 Requirements

- PHP 8.3 or 8.4
- Laravel 11.0 or 12.0 (fully tested on both versions)
- Database: MySQL 5.7+, PostgreSQL 10+, or SQLite 3.8+

## Installation

You can install the package via composer:

```bash
composer require marwen-brini/smart-migration --dev
```

> **Note**: v1.0.0 is a stable release, fully tested and ready for production use.

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="smart-migration-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="smart-migration-config"
```

The config file includes comprehensive settings for:

- **Safety Features**: Auto-backup, safe rollback, confirmation requirements
- **Backup Settings**: Storage path, retention, compression options
- **Archive Configuration**: Retention periods, naming conventions
- **Snapshot Management**: Storage, format (json/yaml/php), rotation
- **Drift Detection**: Ignored tables/columns patterns
- **Risk Assessment**: Customizable operation classifications
- **Display Options**: Verbosity, emojis, progress bars
- **Database Drivers**: Driver-specific settings (MySQL, PostgreSQL, SQLite)
- **Notifications**: Slack, webhooks, email configuration
- **Performance**: Chunk sizes, timeout settings

View the [full configuration file](config/smart-migration.php) for all available options.

## 🚀 Quick Start

### 1. Preview what will happen before running migrations

```bash
php artisan migrate:plan
```

Output:
```
🔍 Smart Migration Plan Analysis
─────────────────────────────────────────────────────────

Migration: 2025_01_15_000000_create_users_table
─────────────────────────────────────────────────────────
✅ SAFE    | Create table 'users'
           | SQL: CREATE TABLE users (...)
           | Impact: New table will be created
           | Duration: ~10ms

✅ SAFE    | Add column 'email' to 'users'
           | SQL: ALTER TABLE users ADD email VARCHAR(255)
           | Impact: 0 rows will get new column
           | Duration: ~10ms

Summary:
- 2 safe operations
- Estimated total time: ~20ms
```

### 2. Run migrations with automatic backups

```bash
php artisan migrate:safe
```

Output:
```
🛡️ Smart Migration - Safe Mode
─────────────────────────────────────────────────────────

Migration Plan:
─────────────────────────────────────────────────────────
📄 2025_01_15_000000_create_users_table
   ✅ 2 safe
   ⏱️ Estimated time: ~20ms

Do you want to proceed with these migrations? (yes/no) [no]: yes

Processing: 2025_01_15_000000_create_users_table
Tables to backup: users
✅ Completed in 25ms
```

### 3. Safely rollback without losing data

```bash
php artisan migrate:undo
```

Output:
```
↩️ Smart Migration - Safe Undo
─────────────────────────────────────────────────────────
Data will be preserved by archiving tables/columns instead of dropping them.

Rollback Plan:
─────────────────────────────────────────────────────────
📄 2025_01_15_000000_create_users_table
   📦 Table 'users' will be archived (150 rows)

Do you want to proceed with this safe rollback? (yes/no) [no]: yes

Rolling back: 2025_01_15_000000_create_users_table
✅ Rolled back in 30ms

📦 Archived items (preserved with timestamp 20250115_143022):
  - Table: _archived_users_20250115_143022 (150 rows)

💡 Tip: Archived data will be kept for 7 days before automatic cleanup.
   You can restore it manually if needed using SQL commands.
```

## 🎨 Web Dashboard

Launch the visual dashboard for a complete migration management experience:

```bash
php artisan migrate:ui
```

The dashboard will be available at `http://localhost:8080`

### Dashboard Features

**Migration Management**:
- ✅ Preview migrations before running with SQL and risk assessment
- ✅ Run migrations safely with visual progress tracking
- ✅ Safe rollback with data preservation confirmations
- ✅ Test migrations on temporary database
- ✅ Detect and resolve migration conflicts

**Schema Tools**:
- ✅ Interactive schema explorer with table/column browsing
- ✅ Drift detection with visual alerts
- ✅ Auto-diff generator to create migrations from changes
- ✅ Schema snapshots management

**Monitoring & Analytics**:
- ✅ Real-time migration status dashboard
- ✅ Performance metrics and charts
- ✅ Migration history timeline
- ✅ Export functionality (HTML, CSV, JSON)

**User Experience**:
- ✅ Auto-refresh every 30 seconds
- ✅ Toast notifications for actions
- ✅ Color-coded risk indicators
- ✅ Responsive design with Tailwind CSS

### Dashboard Navigation

- **Overview** - Dashboard summary and quick statistics
- **Migrations** - Manage and execute database migrations
- **Schema** - Explore your database structure
- **Schema Drift** - Detect and fix schema drift
- **Snapshots** - Manage schema snapshots
- **Metrics** - View migration performance metrics
- **History** - Migration execution timeline
- **Performance** - Performance monitoring and baselines
- **Conflicts** - Detect migration conflicts
- **Auto-Diff** - Generate migrations from database changes

### API Endpoints

The dashboard is built on a comprehensive REST API:

```
GET  /api/smart-migration/status
GET  /api/smart-migration/migrations
GET  /api/smart-migration/migrations/preview/{migration}
POST /api/smart-migration/migrations/run-safe
POST /api/smart-migration/migrations/undo-safe
POST /api/smart-migration/migrations/test
GET  /api/smart-migration/migrations/conflicts
GET  /api/smart-migration/migrations/diff
POST /api/smart-migration/migrations/diff/generate
GET  /api/smart-migration/schema
GET  /api/smart-migration/drift
GET  /api/smart-migration/snapshots
POST /api/smart-migration/snapshots
DELETE /api/smart-migration/snapshots/{name}
GET  /api/smart-migration/metrics
GET  /api/smart-migration/history
GET  /api/smart-migration/performance/baselines
GET  /api/smart-migration/performance/report
```

## 📘 Commands Reference

### Core Commands

#### `migrate:plan`
Preview exactly what a migration will do before running it.

```bash
# Preview all pending migrations
php artisan migrate:plan

# Preview a specific migration
php artisan migrate:plan 2025_01_15_000000_create_users_table
```

#### `migrate:safe`
Run migrations with automatic backups and rollback on failure.

```bash
# Run pending migrations safely
php artisan migrate:safe

# Force run in production
php artisan migrate:safe --force

# See SQL without executing
php artisan migrate:safe --pretend
```

#### `migrate:undo`
Rollback migrations without data loss by archiving instead of dropping.

```bash
# Rollback last migration
php artisan migrate:undo

# Rollback multiple migrations
php artisan migrate:undo --step=3

# Rollback specific batch
php artisan migrate:undo --batch=5
```

### Advanced Commands

#### `migrate:diff`
Automatically generate migrations from database changes (smart column rename detection included).

```bash
# Auto-generate migration from database changes
php artisan migrate:diff

# Preview differences without generating migration
php artisan migrate:diff --dry-run

# Generate without confirmation
php artisan migrate:diff --force

# Generate with custom name
php artisan migrate:diff --name=update_user_schema

# Check specific tables only
php artisan migrate:diff --tables=users,posts

# Ignore snapshot version warnings
php artisan migrate:diff --ignore-version-mismatch
```

#### `migrate:check`
Detect schema drift between your migrations and database.

```bash
# Check for schema drift
php artisan migrate:check

# Show detailed comparison
php artisan migrate:check --details

# Auto-generate fix migration
php artisan migrate:check --fix

# Ignore snapshot version warnings
php artisan migrate:check --ignore-version-mismatch
```

#### `migrate:snapshot`
Manage database schema snapshots for versioning.

```bash
# Create a snapshot
php artisan migrate:snapshot create [name]

# List all snapshots
php artisan migrate:snapshot list

# Show snapshot details
php artisan migrate:snapshot show <name>

# Compare two snapshots
php artisan migrate:snapshot compare <snapshot1> --compare-with=<snapshot2>

# Delete a snapshot
php artisan migrate:snapshot delete <name>
```

#### `migrate:cleanup`
Clean up old archived tables and columns.

```bash
# Clean up old archives
php artisan migrate:cleanup

# Preview cleanup without deleting
php artisan migrate:cleanup --dry-run

# Show archive statistics
php artisan migrate:cleanup --stats
```

#### `migrate:config`
Display current Smart Migration configuration.

```bash
# Show all configuration
php artisan migrate:config

# Show specific section
php artisan migrate:config --section=safety
```

#### `migrate:ui`
Launch the web dashboard for visual migration management.

```bash
# Start dashboard server
php artisan migrate:ui

# Custom port and host
php artisan migrate:ui --port=3000 --host=127.0.0.1
```

**Dashboard Features:**
- 📊 Real-time migration status overview
- 🔍 Interactive schema explorer
- 📈 Performance metrics and charts
- ⏱️ Migration history timeline
- ⚠️ Schema drift alerts
- 🎨 Beautiful, responsive UI

**First Time Setup:**
```bash
# Navigate to package directory
cd vendor/marwen-brini/smart-migration

# Install frontend dependencies
npm install

# Return to your project
cd ../../../
```

Then run `php artisan migrate:ui` to launch the dashboard at `http://localhost:8080`

See [DASHBOARD_IMPLEMENTATION.md](DASHBOARD_IMPLEMENTATION.md) for complete documentation.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Marwen-Brini](https://github.com/Marwen-Brini)
- [All Contributors](../../contributors)

## 🗺️ Roadmap

### ✅ POC (v0.1.0) - Complete!
- [x] `migrate:plan` - Preview migrations with SQL and impact
- [x] `migrate:safe` - Run with automatic backups
- [x] `migrate:undo` - Rollback without data loss
- [x] Risk assessment (SAFE/WARNING/DANGER)
- [x] Time estimation
- [x] Full test coverage

### ✅ MVP (v0.3.0) - Complete!
- [x] `migrate:check` - Detect schema drift
- [x] `migrate:snapshot` - Save schema state
- [x] `migrate:config` - Display configuration
- [x] `migrate:cleanup` - Clean archived data
- [x] PostgreSQL support
- [x] SQLite support
- [x] Database abstraction layer
- [x] Configuration file system
- [x] Auto-cleanup with scheduled jobs
- [x] Comprehensive test coverage
- [x] Laravel 11 & 12 compatibility

### ✅ Stable (v1.0.0) - Current Release!
- [x] `migrate:diff` - Auto-generate migrations from database changes
- [x] Smart column rename detection (Levenshtein algorithm)
- [x] Snapshot format versioning
- [x] Comprehensive multi-database support (MySQL, PostgreSQL, SQLite)
- [x] 100% test coverage (592 tests)
- [x] Production-ready stability

### ✅ Dashboard (v2.0.0) - JUST COMPLETED! 🎉
- [x] `migrate:ui` - Web dashboard command
- [x] Real-time migration status overview
- [x] Interactive schema explorer with search
- [x] Performance metrics and charts
- [x] Migration history timeline
- [x] Schema drift alerts
- [x] Vue 3 + Vite + Tailwind CSS
- [x] RESTful API endpoints
- [x] Auto-refresh functionality
- [x] Responsive design

### 🔮 Future (v2.1+)
- [ ] WebSocket real-time updates
- [ ] Team features and approvals
- [ ] CI/CD integrations
- [ ] Cloud backup integration
- [ ] Manual migration execution from dashboard
- [ ] Drag-and-drop migration ordering
- [ ] Export reports (PDF, CSV)
- [ ] Dark mode theme
- [ ] Advanced rollback strategies
- [ ] Migration performance profiling

See the full [Development Roadmap](smart-migration-roadmap.md) for detailed plans.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🐛 Issues & Feedback

Found a bug or have a suggestion? Please [open an issue](https://github.com/marwen-brini/smart-migration/issues) on GitHub.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
