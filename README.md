# 🛡️ Smart Migration Package for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marwen-brini/smart-migration.svg?style=flat-square)](https://packagist.org/packages/marwen-brini/smart-migration)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/marwen-brini/smart-migration/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/marwen-brini/smart-migration/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/marwen-brini/smart-migration/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/marwen-brini/smart-migration/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/marwen-brini/smart-migration.svg?style=flat-square)](https://packagist.org/packages/marwen-brini/smart-migration)

**Never fear migrations again!** Smart Migration provides safety, visibility, and confidence when running Laravel migrations. Preview changes before they happen, automatically backup data, and rollback without data loss.

> **🎯 POC Status**: v0.1.0 - Proof of Concept Complete! All three core commands (`migrate:plan`, `migrate:safe`, `migrate:undo`) are fully implemented and tested.

## ✨ Features

- 🔍 **Preview migrations** - See exact SQL and impact before running
- 🛡️ **Automatic backups** - Never lose data during migrations
- ↩️ **Safe rollbacks** - Archive instead of dropping data
- ⚠️ **Risk assessment** - Know which operations are dangerous
- ⏱️ **Time estimation** - Understand how long migrations will take
- 🎯 **Zero configuration** - Works out of the box

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/smart-migration.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/smart-migration)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## 📦 Requirements

- PHP 8.3 or 8.4
- Laravel 11.0 or 12.0
- MySQL 5.7+ (PostgreSQL support coming in v0.2.0)

## Installation

You can install the package via composer:

```bash
composer require marwen-brini/smart-migration --dev
```

> **Note**: This is a POC release (v0.1.0). Use in development environments only until v1.0.0 stable release.

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="smart-migration-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="smart-migration-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="smart-migration-views"
```

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

## 📘 Commands Reference

### `migrate:plan`
Preview exactly what a migration will do before running it.

```bash
# Preview all pending migrations
php artisan migrate:plan

# Preview a specific migration
php artisan migrate:plan 2025_01_15_000000_create_users_table
```

### `migrate:safe`
Run migrations with automatic backups and rollback on failure.

```bash
# Run pending migrations safely
php artisan migrate:safe

# Force run in production
php artisan migrate:safe --force

# See SQL without executing
php artisan migrate:safe --pretend
```

### `migrate:undo`
Rollback migrations without data loss by archiving instead of dropping.

```bash
# Rollback last migration
php artisan migrate:undo

# Rollback multiple migrations
php artisan migrate:undo --step=3

# Rollback specific batch
php artisan migrate:undo --batch=5
```

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

### 🚧 MVP (v0.2.0) - Next
- [ ] `migrate:check` - Detect schema drift
- [ ] `migrate:snapshot` - Save schema state
- [ ] PostgreSQL support
- [ ] Colored CLI output
- [ ] Progress bars
- [ ] Configuration file

### 🔮 Future (v1.0.0+)
- [ ] `migrate:diff` - Auto-generate migrations
- [ ] Web dashboard
- [ ] Team features
- [ ] CI/CD integrations
- [ ] Multi-database support

See the full [Development Roadmap](smart-migration-roadmap.md) for detailed plans.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🐛 Issues & Feedback

Found a bug or have a suggestion? Please [open an issue](https://github.com/marwen-brini/smart-migration/issues) on GitHub.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
