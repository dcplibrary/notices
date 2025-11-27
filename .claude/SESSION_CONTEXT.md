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

## Current State (as of 2025-11-26)

### Package Configuration
- **Package Name**: `dcplibrary/notices`
- **Command Prefix**: `notices:*`
- **Config File**: `config/notices.php`
- **PHP Version**: 8.1+
- **Laravel Version**: 10.x or 11.x

### Core Commands (public surface)
- `notices:test-connections` – Test Polaris MSSQL and Shoutbomb FTP/email connections
- `notices:import-polaris` – Import notifications directly from Polaris DB (MSSQL)
- `notices:import-ftp-files` – Unified FTP importer (PhoneNotices, Shoutbomb submissions, patrons, future file types)
- `notices:import-shoutbomb` – Back-compat / focused Shoutbomb submissions import (still available but not exposed in the UI)
- `notices:import-email-reports` – Import Shoutbomb failure/email reports via Microsoft Graph (EMAIL_* envs)
- `notices:sync-from-logs` – Project `notification_logs` into master `notifications` + `notification_events`
- `notices:sync-shoutbomb-to-logs` – Sync Shoutbomb/PhoneNotices into `notification_logs` when needed
- `notices:aggregate` – Aggregate master and log data into summary tables (for dashboards)
- `notices:sync-all` – High-level pipeline (Polaris import → Shoutbomb sync → aggregate)

### Database Tables (core)
1. `notification_logs` – Cached Polaris NotificationLog records (what Polaris created)
2. `notifications` – Master, channel-agnostic notification records (projected from logs + FTP + email)
3. `notification_events` – Lifecycle events tied to a `notification` (queued/exported/submitted/phonenotices_recorded/delivered/failed/verified, etc.)
4. `shoutbomb_deliveries` – SMS/Voice delivery tracking from Shoutbomb reports (FTP/email)
5. `shoutbomb_keyword_usage` – Patron keyword interactions (RHL, RA, OI, etc.)
6. `shoutbomb_registrations` – Subscriber statistics
7. `polaris_phone_notices` – Imported PhoneNotices.csv (verification that Polaris sent to Shoutbomb)
8. `notice_failure_reports` – Parsed Shoutbomb email/Graph-based failure reports
9. `daily_notification_summary` – Aggregated daily summaries for dashboards
10. `sync_logs` – Tracks all sync/import/aggregate operations for the Sync & Import UI

(See `docs/MASTER_NOTIFICATIONS.md` and `docs/NOVEMBER_2025_ARCHITECTURE_UPDATE.md` for current schema details.)

### Architecture (high level)
- **ServiceProvider**: `NoticesServiceProvider` – Auto-loads config, views, migrations, Livewire components, and commands
- **HTTP Controllers**:
  - `DashboardController` – Patron- and staff-facing dashboards, analytics, verification/troubleshooting views
  - `SettingsController` – Settings index, reference data, Sync & Import page
  - `SyncController` – JSON endpoints backing the Sync & Import UI (`/notices/sync/*`)
  - `ExportController` – Data/export + backup endpoints
  - API controllers under `src/Http/Controllers/Api` for programmatic access
- **Livewire Components**:
  - `SyncAndImport` – Shared Sync & Import UI (Polaris, FTP, Email, Sync All, etc.) with a unified date/all control and log modal
- **Models**: Notification, NotificationEvent, NotificationLog, DailyNotificationSummary, Shoutbomb*, PolarisPhoneNotice, SyncLog, NotificationSetting, etc.
- **Services**: PolarisImportService, ImportFTPFiles service(s), ShoutbombSubmissionImporter, ShoutbombFTP/Graph services, NotificationAggregatorService, NoticeVerificationService, SettingsManager, etc.
- **Migrations**: Auto-loaded (no publish required) for all tables listed above.

### Key Features
✅ Direct MSSQL connection to Polaris ILS
✅ Unified FTP importer for PhoneNotices, Shoutbomb submissions, patron lists, and future file types
✅ Graph-based email/Report importer for Shoutbomb failures (Microsoft Graph API)
✅ Master `notifications` + `notification_events` model that unifies all channels
✅ Livewire-based Sync & Import UI with shared date/all controls and log modal (driven by `SyncLog`)
✅ Automated daily imports via scheduler (see `docs/IMPORT_SCHEDULE.md`)
✅ Dashboard, verification, and troubleshooting tools for staff
✅ Historical trend analysis using `daily_notification_summary`
✅ Comprehensive Artisan commands
✅ Auto-loading (migrations, configs, commands + Livewire work without vendor:publish)

## Development Branch

The original Claude refactor work started on `claude/composer-package-refactor-011CUuQNMm5JBJfjP7hfrTc3`, but current development may be on a different branch in the main repo. Treat this file as **package-level context**, not as the source of truth for the active Git branch.

## Important Notes for Future Sessions

### Naming Convention
- The package was renamed from `dcplibrary/polaris-notifications` to `dcplibrary/notices`
- Commands were first changed from `polaris:*` → `notifications:*` and are now standardized as `notices:*`
- Config file changed from `polaris-notifications.php` to `notices.php`
- Config key changed from `polaris-notifications` to `notices`

### ServiceProvider Auto-Loading
The `NoticesServiceProvider` is configured to auto-load:
- **Config** via `mergeConfigFrom()`
- **Migrations** via `loadMigrationsFrom()`
- **Views** via `loadViewsFrom()`
- **Livewire components** via `Livewire::component()`
- **Commands** via `$this->commands([...])`

This means users can install the package and immediately run:
- `php artisan migrate` (migrations auto-discovered)
- `php artisan notices:*` commands (commands auto-registered)
- Use Livewire components (e.g. `<livewire:sync-and-import />`) once routes/views are wired
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
php artisan notices:test-connections
```
This validates Polaris MSSQL and Shoutbomb FTP/email connectivity before attempting imports.
### File Structure (high level)

```
dcplibrary/notices/
├── .claude/                        # Claude session documentation
│   ├── SESSION_CONTEXT.md         # This file - current state
│   └── DEVELOPMENT_TIMELINE.md    # Project history
├── .github/                        # GitHub Actions workflows
├── config/
│   └── notices.php                # Main configuration file
├── database/
│   ├── migrations/                # Package migrations
│   └── factories/                 # Model factories (e.g. SyncLogFactory)
├── docs/                          # Project documentation
│   ├── ARCHITECTURE.md
│   ├── MASTER_NOTIFICATIONS.md
│   ├── IMPORT_SCHEDULE.md
│   ├── NOVEMBER_2025_ARCHITECTURE_UPDATE.md
│   ├── DEVELOPMENT_ROADMAP.md
│   └── help/
│       ├── USER_GUIDE.md
│       └── SETTINGS_USER_GUIDE.md
├── resources/
│   └── views/
│       └── settings/
│           ├── index.blade.php
│           └── sync-livewire.blade.php (hosts <livewire:sync-and-import />)
├── src/
│   ├── Commands/                  # Artisan commands
│   ├── Console/Commands/          # Legacy/console-specific commands
│   ├── Http/
│   │   ├── Controllers/           # Dashboard, Settings, Sync, Export, API
│   │   └── Livewire/SyncAndImport.php
│   ├── Models/                    # Eloquent models (Notification*, Shoutbomb*, etc.)
│   ├── Services/                  # Import, aggregation, verification, settings
│   └── NoticesServiceProvider.php
├── tests/
│   ├── Feature/
│   │   └── SyncLogResultsTest.php
│   └── Unit/
├── composer.json
├── README.md                      # User-facing documentation
└── LICENSE
```
```

## Common Tasks

### Adding a New Command
1. Create command class in `src/Commands/`
2. Use signature `notices:command-name`
3. Register in `NoticesServiceProvider` in the `$this->commands([...])` array
4. Update README.md and `docs/ARCHITECTURE.md` with usage examples

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
php artisan notices:test-connections
```

## Next Steps / TODO

See `DEVELOPMENT_TIMELINE.md` for planned features and upcoming work.

## Getting Help

- **GitHub Issues**: https://github.com/dcplibrary/notices/issues
- **Documentation**: See `docs/` directory for detailed analysis
- **README**: User-facing documentation with examples
