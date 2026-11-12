# Notices Package

[![Version](https://img.shields.io/github/v/release/dcplibrary/notices?label=version&sort=semver)](https://github.com/dcplibrary/notices/releases/latest)
[![Release](https://github.com/dcplibrary/notices/actions/workflows/release.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/release.yml)
[![Nightly Percona Full Suite](https://github.com/dcplibrary/notices/actions/workflows/nightly-percona.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/nightly-percona.yml)
[![CodeQL - Code Quality](https://github.com/dcplibrary/notices/actions/workflows/github-code-quality/codeql/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/github-code-quality/codeql)
[![Security Risk Assessment](https://github.com/dcplibrary/notices/actions/workflows/github-code-scanning/code-security-risk-assessment/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/github-code-scanning/code-security-risk-assessment)

Laravel package for monitoring, logging, and validating the entire lifecycle of library notifications (Mail, Email, Voice, SMS) from generation to delivery.

This package integrates with Polaris ILS exports, Shoutbomb FTP exports, and failure reports to provide complete visibility into notification delivery and detect issues that prevent patrons from receiving important library communications.

## Features

- **Import notification data** from multiple sources (FTP, email, local exports)
- **Unified notification tracking** - deduplicate and normalize notifications across all delivery channels
- **Delivery issue detection** - automatically identify failed deliveries, invalid contact information, and at-risk patrons
- **Web interface** - search, filter, and investigate notifications and delivery issues
- **Patron timeline view** - see complete notification history for individual patrons
- **Bulk issue resolution** - resolve multiple delivery issues at once
- **Automated pipeline** - scheduled daily imports and reconciliation

---

## Authentication

This package requires authentication via the **dcplibrary/entra-sso** package. All routes are protected by Laravel's `auth` middleware.

### Configuration

1. **Install and configure entra-sso** in your Laravel application:
```bash
composer require dcplibrary/entra-sso
php artisan entra:install
```

2. **Set redirect to /notices after login** in your `.env` file:
```env
ENTRA_REDIRECT_AFTER_LOGIN=/notices
```

3. **User Interface Features:**
   - Avatar displays user initials from authenticated user's name
   - Dropdown menu shows user info (name, email, role)
   - **Settings** menu (admin users only) - accessible via avatar dropdown
   - **Logout** button - destroys session and returns to login page

4. **Role-Based Access:**
   - Settings page is only visible to users with `role = 'admin'`
   - Set user roles in the Entra SSO group mapping configuration

For complete entra-sso setup instructions, see the [dcplibrary/entra-sso documentation](https://github.com/dcplibrary/entra-sso).

---

## Quick Start

### Web Interface

Access the package at `/notices` in your Laravel application:

| Page | URL | Purpose |
|------|-----|---------|
| **Dashboard** | `/notices/dashboard` | Overview and recent activity |
| **Notifications** | `/notices/list` | Search and filter all notifications |
| **Delivery Issues** | `/notices/issues` | Active delivery problems requiring attention |
| **Patron History** | `/notices/patron/{barcode}` | Complete timeline for a specific patron |
| **Data** | `/notices/data/report-emails` | View email reports and import data |
| **Settings** | `/notices/settings` | Configure display labels and preferences (admin only - access via avatar dropdown) |

---

## For AI coding agents

Read [AGENTS.md](AGENTS.md) first. It holds the rules for how notification status is decided and the reasons behind them.

For orientation in the code, start with:

- `routes/web.php` and `routes/api.php` route manifests (they fan out to feature route modules)
- `routes/web/*.php` and `routes/api/*.php` to find feature-level route wiring
- `src/Http/Controllers/{Dashboard,Notifications,Issues,FailedContacts,Search,Settings*}Controller.php` for feature endpoints
- `src/Services/NotificationService.php` as orchestration facade and entrypoint to focused query/action services
- `resources/views/notifications/issues.blade.php` for charts + failed contact UI
- `resources/views/components/*-modal.blade.php` for detail modal UX

Current textual architecture map:
- `docs/architecture.md`

Runtime shape (simplified):
```text
Browser/API Client
  -> routes/{web,api}.php manifests
      -> routes/<feature>.php
          -> Feature Controller
              -> NotificationService + focused services
                  -> Models/DB
              -> Blade/JSON response
```

**Critical context** lives in `AGENTS.md` (data source separation rules).

**Local dev**: this is a package; you’ll usually run it inside a host app. If UI changes don’t appear, clear views in the host app with `php artisan view:clear` and confirm the host app uses a composer `path` repo (symlink) to this checkout.

### Daily Operations

Run the complete daily pipeline:

```bash
# Import and process today's notifications
php artisan notices:import

# Or process specific date
php artisan notices:import --date=2025-12-27
```

This single command handles:
1. Importing FTP exports (holds, renewals, overdue)
2. Importing email failure reports
3. Deduplicating notifications
4. Reconciling statuses
5. Detecting delivery issues
6. Processing orphaned notifications (Polaris → LX Starter failures)

### Automated Scheduling

**✨ NEW:** The package now handles scheduling automatically! No configuration needed.

**Automatic Schedule** (runs 4 times daily):
- **8:30 AM** - Morning import (Notification Log + first FTP batch)
- **9:30 AM** - Second holds batch
- **1:30 PM** - Afternoon holds batch
- **5:30 PM** - Evening holds batch

The package registers these schedules internally and logs success/failure to Laravel logs:
```bash
tail -f storage/logs/laravel.log | grep '\[Notices\]'
```

**Import Monitoring:**
Visit the dashboard at `/notices/dashboard` to see:
- Last import time for each source (FTP Holds, Renewal, Overdue, Email Reports)
- Status indicators (green = success, yellow = warning, red = error)
- "Up to date" or "Behind schedule" badge
- Failed files list (if any)

**Manual Override** (optional):
If you need custom scheduling in `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Package already schedules 8:30 AM, 9:30 AM, 1:30 PM, 5:30 PM
    // Add custom schedules only if needed
    $schedule->command('notices:import --date=yesterday')
        ->dailyAt('02:00')
        ->emailOutputOnFailure('admin@yourlibrary.org');
}
```

---

## Documentation

Everything is indexed in [docs/README.md](docs/README.md).

- **Staff:** [User guide](docs/user-guide.md), [Workflows](docs/workflows.md), [Concepts](docs/concepts.md)
- **Administrators:** [Commands](docs/commands.md), [Deployment](docs/deployment/README.md), [API](docs/api.md)
- **Developers and coding agents:** [AGENTS.md](AGENTS.md), then [docs/development/](docs/development/)
- **API:** endpoints are described in [docs/openapi.yaml](docs/openapi.yaml) and browsable at `/notices/api-docs` in a running install. The PHP class reference is generated from the code by phpDocumentor.

---

## Common Commands

```bash
# Run complete daily pipeline
php artisan notices:import

# Import FTP data only (e.g., when on VPN)
php artisan notices:import-ftp --date=2025-12-27

# Import email reports only
php artisan notices:import-email --date=2025-12-27

# Process date range
php artisan notices:import --from=2025-12-20 --to=2025-12-27

# Process last 7 days
php artisan notices:import --days=7

# Process orphaned notifications (runs automatically in notices:import)
php artisan notices:process-orphaned --days=7

# Run integrity checks on data (runs automatically after import with --fix)
php artisan notices:integrity-check --days=30
php artisan notices:integrity-check --days=30 --fix

# List available FTP files
php artisan notices:import-ftp --list

# Sync lookup tables
php artisan notices:sync-lookup-tables

# Seed lookup tables with Polaris data
php artisan notices:seed
```

See [Commands Reference](docs/commands.md) for complete documentation.

---

## VPN Workflow

When you need to access FTP via VPN but lose internet access:

```bash
# 1. Connect to VPN
# 2. Import FTP data only
php artisan notices:import-ftp --date=2025-12-27

# 3. Disconnect VPN, reconnect to internet
# 4. Complete pipeline (uses cached FTP files)
php artisan notices:import --date=2025-12-27
```

See [Common Workflows](docs/workflows.md#vpnftp-workflows) for detailed instructions.

---

## Delivery Methods

The package tracks four delivery methods:

| ID | Method | Handling | Notes |
|----|--------|----------|-------|
| 1 | Mail | Manual - printed by staff | Stays "Pending" until processed |
| 2 | Email | Automated via LX Starter | Delivered electronically |
| 3 | Voice | Automated via LX Starter | Phone calls |
| 8 | SMS | Automated via LX Starter | Text messages |

**Important:** Mail notifications remain "Pending" - this is normal. Only electronic delivery (Email, Voice, SMS) is flagged for LX Starter failures.

See [Delivery Options](docs/concepts.md) for complete details.

---

## Configuration

Package configuration is in `config/notices.php`. Key environment variables:

```env
# FTP Configuration
POLARIS_USE_FTP=true
POLARIS_FTP_HOST=your-ftp-host
POLARIS_FTP_USERNAME=your-username
POLARIS_FTP_PASSWORD=your-ftp-password

# Microsoft Graph API (for email ingestion)
MICROSOFT_TENANT_ID=your-tenant-id
MICROSOFT_CLIENT_ID=your-client-id
MICROSOFT_CLIENT_SECRET=your-client-secret
MICROSOFT_MAILBOX=reports@yourlibrary.org
# Optional: the mail folder holding Shoutbomb reports (default: Shoutbomb_Reports)
MICROSOFT_FOLDER_SHOUTBOMB=Shoutbomb_Reports
```

---

## Testing

The package includes a comprehensive test suite:

```bash
# From package directory
composer install
vendor/bin/pest

# Run specific test suite
vendor/bin/pest tests/Feature/NotificationAccountingTest.php
vendor/bin/pest tests/Feature/IssueDetectionTest.php

# Run with coverage
vendor/bin/pest --coverage --coverage-html coverage
```

See [Testing Guide](docs/development/testing.md) for detailed documentation.

---

## Installation

**Via Composer:**

```bash
composer require dcplibrary/notices
```

**Laravel 12 with Alpine.js Note:**

If you're using Laravel 12 and encounter "Class 'Livewire\Mechanisms\ExtendBlade\ExtendBlade' not found" errors, you have two options:

1. **Install Livewire** (recommended):
   ```bash
   composer require livewire/livewire
   ```

2. **Disable Livewire auto-discovery** in your Laravel application's `bootstrap/providers.php`:
   ```php
   // Add this to prevent Livewire auto-discovery
   if (!class_exists(\Livewire\Livewire::class)) {
       config(['livewire.disable_auto_discovery' => true]);
   }
   ```

**Set it up:**

```bash
php artisan notices:install
```

This asks for the Microsoft Graph and Polaris FTP settings, writes them to your `.env`, runs the migrations and seeds the lookup tables. It can be run again to change a setting. See [Commands](docs/commands.md) for its options.

To do the same by hand: add the settings under [Configuration](#configuration) to `.env`, then run `php artisan migrate` and `php artisan notices:seed`.

**Publishing the config file is optional.** The package works without it. If you want to change defaults that have no `.env` setting:

```bash
php artisan vendor:publish --tag=notices-config
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Support and Contributing

- **Package:** `dcplibrary/notices`
- **Repository:** [github.com/dcplibrary/notices](https://github.com/dcplibrary/notices)
- **Issues:** [Report issues on GitHub](https://github.com/dcplibrary/notices/issues)
- **Documentation:** See `docs/` directory
- **Changelog:** See [CHANGELOG.md](CHANGELOG.md)

---

## License

This package is open-sourced software licensed under the MIT license.
