# Claude Session Context

## Project Overview

**Name**: dcplibrary/notices
**Type**: Laravel Composer Package
**Purpose**: Track and analyze Polaris ILS notifications with Shoutbomb SMS/Voice integration
**Author**: Brian Lashbrook - Daviess County Public Library
**Repository**: https://github.com/dcplibrary/notices

## What This Package Does

This Laravel package provides a complete solution for tracking library notification delivery across multiple channels (Email, SMS, Voice, Mail) by:

1. **Importing notification data from Polaris ILS** (MSSQL database)
2. **Importing delivery confirmation reports from Shoutbomb** (FTP)
3. **Aggregating data** for dashboard analytics
4. **Providing Artisan commands** for automated imports and aggregation
5. **Auto-loading migrations, configs, and commands** without requiring vendor:publish

## Current State (as of 2025-11-07)

### Package Configuration
- **Package Name**: `dcplibrary/notices`
- **Command Prefix**: `notifications:*`
- **Config File**: `config/notices.php`
- **PHP Version**: 8.1+
- **Laravel Version**: 10.x or 11.x

### Available Commands
- `notifications:test-connections` - Test Polaris MSSQL and Shoutbomb FTP connections
- `notifications:import-notifications` - Import notifications from Polaris ILS
- `notifications:import-shoutbomb` - Import Shoutbomb delivery reports from FTP
- `notifications:aggregate-notifications` - Aggregate notification data into daily summaries

### Database Tables
1. `notification_logs` - Main notification tracking (imported from Polaris)
2. `shoutbomb_deliveries` - SMS/Voice delivery tracking
3. `shoutbomb_keyword_usage` - Patron keyword interactions (RHL, RA, OI, etc.)
4. `shoutbomb_registrations` - Subscriber statistics
5. `daily_notification_summary` - Aggregated daily summaries for dashboards

### Architecture
- **ServiceProvider**: `NotificationsServiceProvider` - Auto-loads everything
- **Commands**: 4 Artisan commands in `src/Commands/`
- **Models**: 6 Eloquent models in `src/Models/`
- **Services**: 4 service classes for import/aggregation logic
- **Migrations**: 5 migration files (auto-loaded, no publish needed)

### Key Features
✅ Direct MSSQL connection to Polaris ILS
✅ FTP integration for Shoutbomb reports
✅ Automated daily imports via scheduled tasks
✅ Real-time notification tracking
✅ Historical trend analysis
✅ Dashboard-ready data models
✅ Comprehensive Artisan commands
✅ Auto-loading (migrations, configs, commands work without vendor:publish)

## Development Branch

**Current Branch**: `claude/composer-package-refactor-011CUuQNMm5JBJfjP7hfrTc3`

This is a feature branch for Claude-assisted development. All work should be committed here and pushed regularly.

## Important Notes for Future Sessions

### Naming Convention
- The package was recently renamed from `dcplibrary/polaris-notifications` to `dcplibrary/notices`
- All commands changed from `polaris:*` to `notifications:*`
- Config file changed from `polaris-notifications.php` to `notifications.php`
- Config key changed from `polaris-notifications` to `notifications`

### ServiceProvider Auto-Loading
The ServiceProvider (`NotificationsServiceProvider`) is configured to auto-load:
- **Migrations**: `loadMigrationsFrom()` on line 60
- **Commands**: `commands()` on lines 51-56
- **Config**: `mergeConfigFrom()` on lines 20-23

This means users can install the package and immediately run:
- `php artisan migrate` (migrations auto-discovered)
- `notifications:*` commands (commands auto-registered)
- Access config via `config('notices.*')` (config auto-merged)

Optionally, users can publish config with:
```bash
php artisan vendor:publish --tag=notices-config
```

### External Dependencies
- **Polaris ILS**: MSSQL Server database (read-only access needed)
- **Shoutbomb**: FTP server for report files (optional)
- **PHP Extensions**: `sqlsrv` for MSSQL, `ftp` for Shoutbomb

### Testing/Debugging
Users should first run:
```bash
php artisan notifications:test-connections
```
This validates Polaris MSSQL and Shoutbomb FTP connectivity before attempting imports.

## File Structure

```
dcplibrary/notices/
├── .claude/                    # Claude session documentation
│   ├── SESSION_CONTEXT.md     # This file - current state
│   └── DEVELOPMENT_TIMELINE.md # Project history
├── .github/                    # GitHub Actions workflows
├── config/
│   └── notifications.php      # Main configuration file
├── database/
│   └── migrations/            # 5 migration files
├── docs/                      # Project documentation
│   ├── COMBINED_DOCUMENTATION.md
│   ├── NOTIFICATION_FLOW_DIAGRAM.md
│   ├── DATA_GENERATION_PLAN.md
│   └── [other analysis docs]
├── src/
│   ├── Commands/              # 4 Artisan commands
│   ├── Models/                # 6 Eloquent models
│   ├── Services/              # 4 service classes
│   └── NotificationsServiceProvider.php
├── composer.json
├── README.md                  # User-facing documentation
└── LICENSE
```

## Common Tasks

### Adding a New Command
1. Create command class in `src/Commands/`
2. Use signature `notifications:command-name`
3. Register in `NotificationsServiceProvider::boot()` line 51-56
4. Update README.md with usage example

### Adding a New Migration
1. Create migration in `database/migrations/`
2. Use naming: `YYYY_MM_DD_NNNNNN_description.php`
3. No need to register - auto-loaded by ServiceProvider

### Adding a New Config Option
1. Update `config/notices.php`
2. Access via `config('notices.key')`
3. Update README.md configuration section

### Testing Changes Locally
When users install this package in their Laravel app:
```bash
composer require dcplibrary/notices
php artisan migrate
php artisan notifications:test-connections
```

## Next Steps / TODO

See `DEVELOPMENT_TIMELINE.md` for planned features and upcoming work.

## Getting Help

- **GitHub Issues**: https://github.com/dcplibrary/notices/issues
- **Documentation**: See `docs/` directory for detailed analysis
- **README**: User-facing documentation with examples
