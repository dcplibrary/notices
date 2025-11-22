# Changelog

All notable changes to the Polaris Notices Package will be documented in this file.

## [Unreleased]

### Added - Console Commands for Manual Imports (2025-11-11)

#### New Artisan Commands
- Created `ImportPolarisCommand` (notices:import-polaris)
  - Wraps `PolarisImportService` with CLI interface
  - Options: --days, --start-date, --end-date
  - Output formatted for SyncController parsing
- Created `ImportShoutbombCommand` (notices:import-shoutbomb)
  - Wraps `ShoutbombSubmissionImporter` with CLI interface  
  - Options: --start-date
  - Reports breakdown by type (holds, overdues, renewals)
- Created `AggregateNotificationsCommand` (notices:aggregate)
  - Wraps `NotificationAggregatorService` with CLI interface
  - Options: --yesterday, --date, --start-date, --end-date, --all
  - Provides date range and combination statistics

#### Critical Bug Fixes
- **CRITICAL**: Fixed commands not available to web requests
  - Root cause: Commands were registered inside `if ($this->app->runningInConsole())`
  - Solution: Moved command registration outside that check
  - Impact: `Artisan::call()` from web requests (SyncController) can now find commands
  - This is a **critical pattern** for all Laravel packages with commands
- Fixed HTTPS/mixed content errors behind nginx-proxy
  - Changed JavaScript fetch URLs from `route()` helpers to relative URLs
  - Relative URLs automatically use same protocol as page
- Fixed duplicate aggregate command registration
  - Commented out old `AggregateNotifications::class` to prevent conflict
- Removed stray backslashes from HTML comment in sync view

#### Files Modified
- Created: `src/Console/Commands/ImportPolarisCommand.php`
- Created: `src/Console/Commands/ImportShoutbombCommand.php`  
- Created: `src/Console/Commands/AggregateNotificationsCommand.php`
- Modified: `src/NoticesServiceProvider.php` (critical command registration fix)
- Modified: `resources/views/dashboard/index.blade.php` (relative URLs)
- Modified: `resources/views/settings/sync.blade.php` (relative URLs, backslash fix)

#### Documentation
- Created `SESSION_2025-11-11_console_commands_sync.md` with complete session details
- Documents critical Laravel package pattern for command registration

### Added - Namespace Migration & Verification System (2025-11-10)

### Added - Namespace Migration & Verification System (2025-11-10)

#### Repository Rename
- **BREAKING CHANGE**: Renamed repository from `notifications` to `notices`
- Updated all PHP namespaces from `Dcplibrary\Notifications\` to `Dcplibrary\Notices\`
- Updated all view namespaces from `notifications::` to `notices::`
- Updated all route prefixes from `notifications.*` to `notices.*`
- Updated all API route prefixes from `notifications.api.*` to `notices.api.*`

#### Verification System - Phase 1: Verification Core
- Created `NoticeVerificationService` for tracking notice delivery lifecycle
- Implemented 4-step verification: Created → Submitted → Verified → Delivered
- Added `VerificationResult` value object for standardized verification data
- Created verification API endpoints for programmatic access
- Integrated with Shoutbomb submission, phone notice, and delivery tables

#### Verification System - Phase 2: Timeline & Details UI
- Created verification search page (`/notices/verification`)
  - Search by patron barcode, phone, email, or item barcode
  - Date range filtering
  - Result summary with verification statistics
- Created timeline view (`/notices/verification/{id}`)
  - Visual timeline of notice lifecycle
  - Step-by-step verification status
  - Detailed failure information
- Created patron history view (`/notices/verification/patron/{barcode}`)
  - Patron-specific verification statistics
  - Success rate tracking
  - Notice history by type
  - Configurable date ranges (30/90/180 days)
- Updated navigation to include "Verification" menu item

#### Verification System - Phase 3: Troubleshooting
- Created troubleshooting dashboard (`/notices/troubleshooting`)
  - Failure analysis by reason and type
  - Mismatch detection (submitted-not-verified, verified-not-delivered)
  - Recent failures table with details
  - Configurable date ranges (7/14/30 days)
- Added troubleshooting methods to `NoticeVerificationService`:
  - `getFailuresByReason()` - Group failures by failure reason
  - `getFailuresByType()` - Group failures by notification type
  - `getMismatches()` - Detect verification gaps
  - `getTroubleshootingSummary()` - Overall troubleshooting statistics
- Added troubleshooting API endpoints
- Updated navigation to include "Troubleshooting" menu item

#### Verification System - Phase 4: Plugin Architecture
- Created `NotificationPlugin` interface for modular channel support
- Implemented `PluginRegistry` service for plugin management
- Created `ShoutbombPlugin` as first plugin implementation
  - Encapsulates all Shoutbomb-specific verification logic
  - Maps delivery option IDs (3=Voice, 8=SMS)
  - Provides statistics and failure reporting
- Updated `NoticeVerificationService` to use plugin system
- Registered plugins in `NoticesServiceProvider`
- Added comprehensive PHPUnit tests:
  - `PluginRegistryTest.php` (13 tests)
  - `ShoutbombPluginTest.php` (15 tests)

#### Verification System - Phase 5: Enhanced UI
- Created `NoticeExportService` for CSV exports
  - `exportVerificationToCSV()` - Export search results
  - `exportPatronHistoryToCSV()` - Export patron history
  - `exportFailuresToCSV()` - Export troubleshooting data
- Added export controller methods to `DashboardController`
- Added export routes with proper naming
- Added CSV export buttons to all verification views
- CSV features:
  - UTF-8 BOM for Excel compatibility
  - Proper escaping for quotes, commas, special characters
  - Timestamped filenames
  - Query filter support
  - Result limits (1000 records) to prevent timeouts
- Added comprehensive PHPUnit tests:
  - `NoticeExportServiceTest.php` (13 tests)
  - `ExportControllerTest.php` (13 tests)

#### Testing & Quality Assurance
- Created comprehensive route tests:
  - `WebRoutesTest.php` (19 tests) - Validates all web dashboard routes
  - `ApiRoutesTest.php` (17 tests) - Validates all API endpoints
- Updated existing tests to use new `notices.*` route names
- All tests verify correct namespace usage and route accessibility
- Total tests added: **77 tests** across verification system and routes

#### Bug Fixes
- Fixed MySQL index name length errors in migration
  - Changed auto-generated index names to explicit short names
  - `dns_date_type_idx` and `dns_date_delivery_idx` for `daily_notification_summary`
- Fixed missing view namespace references in:
  - `DashboardController` (all 8 dashboard views)
  - `SettingsController` (all 4 settings views)
  - Blade templates (verification, troubleshooting, index, analytics, notifications, shoutbomb)
- Fixed missing route references in navigation (`notices.list` instead of `notices.notifications`)
- Fixed blade syntax errors (missing opening quotes in `@extends()` directives)
- Added missing variables to dashboard views:
  - `$latestRegistration` in `index.blade.php` and `shoutbomb.blade.php`
  - `$registrationHistory` in `shoutbomb.blade.php`
- Updated all fully-qualified class names from `\Dcplibrary\Notifications\` to `\Dcplibrary\Notices\`

#### Documentation
- Updated README.md with repository rename information
- Updated VERIFICATION_SYSTEM_DESIGN.md with completed phases
- Created CHANGELOG.md for tracking changes
- Created DEVELOPMENT_TIMELINE.md with detailed development history

### Migration Guide

#### For Developers
If you're upgrading from the old `notifications` namespace:

1. **Update composer.json**:
   ```bash
   composer require dcplibrary/notices
   ```

2. **Update use statements** in your code:
   ```php
   // Old
   use Dcplibrary\Notifications\Models\NotificationLog;

   // New
   use Dcplibrary\Notices\Models\NotificationLog;
   ```

3. **Update route names** in your views/controllers:
   ```php
   // Old
   route('notifications.dashboard')
   route('notifications.api.notifications.index')

   // New
   route('notices.dashboard')
   route('notices.api.logs.index')
   ```

4. **Update view namespaces**:
   ```blade
   {{-- Old --}}
   @extends('notifications::layouts.app')

   {{-- New --}}
   @extends('notices::layouts.app')
   ```

5. **Update config references**:
   ```php
   // Old
   config('notifications.notification_types')

   // New
   config('notices.notification_types')
   ```

6. **Run migrations** if updating database:
   ```bash
   php artisan migrate
   ```

## [1.0.0] - 2025-01-XX

### Initial Release
- Basic notification tracking from Polaris ILS
- Shoutbomb integration for SMS/Voice
- Dashboard with overview, analytics, and Shoutbomb pages
- RESTful API for programmatic access
- Artisan commands for imports and aggregation
- Docker setup with SQL Server driver

---

## Version Naming

This project follows [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes
