# Package Merge: Shoutbomb Models Migration Guide

## Overview

As of version 1.x, the following models and database migrations have been **moved from `dcplibrary/shoutbomb-reports` to `dcplibrary/notices`** to consolidate all notice-related data models in one package:

- ✅ `NoticeFailureReport` - Shoutbomb failure reports (opted-out, invalid, undelivered)
- ✅ `ShoutbombMonthlyStat` - Monthly statistics from Shoutbomb comprehensive reports

## What Changed

### In `dcplibrary/notices` (this package)

**Added:**

**NoticeFailureReport:**
- ✅ `src/Models/NoticeFailureReport.php` - Full model with all functionality
- ✅ `src/Database/Migrations/2025_11_23_000001_create_notice_failure_reports_table.php` - Table creation
- ✅ `src/Database/factories/NoticeFailureReportFactory.php` - Factory for testing
- Features: All scopes (optedOut, invalid, forPatron), helper methods (markAsProcessed, isOptedOut)

**ShoutbombMonthlyStat:**
- ✅ `src/Models/ShoutbombMonthlyStat.php` - Full model with all functionality
- ✅ `src/Database/Migrations/2025_11_23_000002_create_shoutbomb_monthly_stats_table.php` - Table creation
- ✅ `src/Database/factories/ShoutbombMonthlyStatFactory.php` - Factory for testing
- Features: All scopes (forMonth, forBranch, recent), virtual attributes (total_notices, text_percentage)

### In `dcplibrary/shoutbomb-reports`

**Changed:**
- ⚠️ Now **requires** `dcplibrary/notices` as a dependency
- ⚠️ `NoticeFailureReport` and `ShoutbombMonthlyStat` models replaced with class aliases
- ⚠️ All migrations replaced with deprecation stubs
- ✅ `CheckReportsCommand` updated to import both models from notices package

## Migration Path

### For New Installations

If you're installing fresh:

```bash
# Install both packages
composer require dcplibrary/notices
composer require dcplibrary/shoutbomb-reports

# Run migrations (notices package creates the table)
php artisan migrate
```

The migration will automatically create the `notice_failure_reports` table.

### For Existing Installations

If you already have `dcplibrary/shoutbomb-reports` installed:

#### Option 1: Update Both Packages (Recommended)

```bash
# Update to latest versions
composer update dcplibrary/shoutbomb-reports dcplibrary/notices

# The table already exists, so new migrations will be skipped
php artisan migrate
```

The migration will detect that the table exists and skip creation.

#### Option 2: Fresh Installation (Advanced)

If you want to start fresh:

```bash
# Backup your data first!
php artisan db:backup  # or your preferred backup method

# Drop the old table (if safe to do so)
# php artisan migrate:rollback --path=vendor/dcplibrary/shoutbomb-reports/src/database/migrations

# Update packages
composer update dcplibrary/shoutbomb-reports dcplibrary/notices

# Run new migration
php artisan migrate
```

## Code Changes Required

### No Changes Needed (Class Aliases)

If you're using the old namespaces, they will continue to work via class aliases:

```php
// These still work (class aliases)
use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Dcplibrary\ShoutbombFailureReports\Models\ShoutbombMonthlyStat;

$failures = NoticeFailureReport::optedOut()->recent(7)->get();
$stats = ShoutbombMonthlyStat::forMonth(2024, 11)->first();
```

### Recommended: Update Imports

For clarity and future compatibility, update your imports to the new namespace:

```php
// Recommended: Use new namespaces
use Dcplibrary\Notices\Models\NoticeFailureReport;
use Dcplibrary\Notices\Models\ShoutbombMonthlyStat;

$failures = NoticeFailureReport::optedOut()->recent(7)->get();
$stats = ShoutbombMonthlyStat::forMonth(2024, 11)->first();
```

## Configuration

### Table Name Configuration

The table name is configurable in both packages for backwards compatibility:

**notices package** (`config/notices.php`):
```php
'integrations' => [
    'shoutbomb_reports' => [
        'table' => env('SHOUTBOMB_FAILURE_TABLE', 'notice_failure_reports'),
    ],
],
```

**shoutbomb-reports package** (`config/shoutbomb-reports.php`):
```php
'storage' => [
    'table_name' => env('SHOUTBOMB_FAILURE_TABLE', 'notice_failure_reports'),
],
```

Both packages check the same environment variable, so existing configurations continue to work.

## Breaking Changes

### BREAKING: Namespace Changes

**NoticeFailureReport:**
- **Old:** `Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport`
- **New:** `Dcplibrary\Notices\Models\NoticeFailureReport`

**ShoutbombMonthlyStat:**
- **Old:** `Dcplibrary\ShoutbombFailureReports\Models\ShoutbombMonthlyStat`
- **New:** `Dcplibrary\Notices\Models\ShoutbombMonthlyStat`

**Mitigation:** Class aliases provide backwards compatibility. Update imports at your convenience.

### BREAKING: Migration Location

- **Old:** `vendor/dcplibrary/shoutbomb-reports/src/database/migrations/`
- **New:** `vendor/dcplibrary/notices/src/Database/Migrations/`

**Mitigation:** Old migrations replaced with deprecation stubs. New installations use notices package migrations.

### BREAKING: Composer Dependency

- **Change:** `dcplibrary/shoutbomb-reports` now requires `dcplibrary/notices`

**Mitigation:** Run `composer update` to install both packages.

## Deprecation Timeline

- **v1.x (Current):** Class alias provides backwards compatibility
- **v2.0 (Future):** Class alias and deprecation stubs will be removed

## FAQ

### Q: Do I need to change my code immediately?

**A:** No. The class alias ensures backwards compatibility. Update at your convenience.

### Q: Will my existing data be affected?

**A:** No. The table structure is identical. Your data remains unchanged.

### Q: What if I only use shoutbomb-reports?

**A:** The notices package will be installed automatically as a dependency when you update shoutbomb-reports.

### Q: Can I use both old and new namespaces simultaneously?

**A:** Yes, during the v1.x lifecycle. However, use one consistently to avoid confusion.

### Q: What if I have a custom table name?

**A:** Both packages check the same configuration keys, so custom table names continue to work.

## Testing

After upgrading, verify the integration:

```bash
# Check that both models are accessible
php artisan tinker
>>> \Dcplibrary\Notices\Models\NoticeFailureReport::count();
>>> \Dcplibrary\Notices\Models\ShoutbombMonthlyStat::count();

# Run a test import
php artisan shoutbomb:check-reports --dry-run

# Verify data is being written
>>> \Dcplibrary\Notices\Models\NoticeFailureReport::latest()->first();
>>> \Dcplibrary\Notices\Models\ShoutbombMonthlyStat::latest()->first();
```

## Support

If you encounter issues during migration:

1. Check that both packages are updated: `composer show dcplibrary/notices dcplibrary/shoutbomb-reports`
2. Verify tables exist: `php artisan db:show notice_failure_reports` and `php artisan db:show shoutbomb_monthly_stats`
3. Check configuration: `config/notices.php` and `config/shoutbomb-reports.php`
4. Review logs: `storage/logs/laravel.log`

## Summary

✅ **Backwards compatible** - Class alias ensures existing code works
✅ **Data preserved** - No data migration needed
✅ **Consolidated** - All notice models in one package
✅ **Gradual migration** - Update namespaces at your own pace
✅ **Future-proof** - Clear deprecation timeline

The merge simplifies the architecture and makes maintenance easier while preserving full backwards compatibility during the v1.x lifecycle.
