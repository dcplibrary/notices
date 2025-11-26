# Upgrade Guide: dcplibrary/notices 2.0.0

**Previous stable:** 1.26.2  
**New major:** 2.0.0

This guide explains what changed in 2.0.0, what is breaking, and how to migrate.

---

## 1. High-Level Changes

### New

- **Master notifications model**:
  - New tables: `notifications` and `notification_events`.
  - New service: `NotificationProjectionService`.
  - New Artisan command: `notices:sync-from-logs`.
- **Nightly flow** (recommended):
  1. Import data (FTP, PhoneNotices, exports, Shoutbomb reports).
  2. Project `notification_logs` → `notifications` + `notification_events` via `notices:sync-from-logs`.
  3. Aggregate daily stats via `notices:aggregate --yesterday`.

### Deprecated/Removed

- **Old NotificationLog-only import command**:
  - `ImportNotifications` (old `notices:import` that talked directly to Polaris NotificationLog) is no longer registered as a console command.
- **Legacy Shoutbomb → NotificationLog sync**:
  - `SyncShoutbombToLogs` (`notices:sync-shoutbomb-to-logs`) is no longer registered.

The new flow expects you to:

- Use the unified `notices:import` command (FTP + PhoneNotices + exports + failures).
- Use `notices:sync-from-logs` to project `notification_logs` into the new master tables.

---

## 2. Breaking Changes

### 2.1 Removed / Replaced Commands

**Removed from the command registry:**

- `notices:import-notifications` (class: `ImportNotifications`) – superseded by the unified `notices:import`.
- `notices:sync-shoutbomb-to-logs` (class: `SyncShoutbombToLogs`) – superseded by the new `notices:sync-from-logs`.

**What to do:**

- If you have **cron jobs**, **Docker schedulers**, or scripts that reference:
  - `php artisan notices:import-notifications ...`
  - `php artisan notices:sync-shoutbomb-to-logs ...`

  → Update them to use:

  ```bash
  # Unified import of patron lists, PhoneNotices, exports, failures
  php artisan notices:import --days=1

  # Project NotificationLog rows into master notifications + events
  php artisan notices:sync-from-logs --days=1

  # Aggregate stats (unchanged)
  php artisan notices:aggregate --yesterday
  ```

### 2.2 New Tables (Schema Additions)

The 2.0.0 migrations add:

- `notifications`
- `notification_events`

**This is additive** – existing tables (`notification_logs`, Shoutbomb tables, PhoneNotices, etc.) are not dropped or modified in a breaking way. However, any custom code that assumed `notification_logs` is the *only* canonical table should consider moving up to `notifications` + `notification_events` over time.

---

## 3. How to Upgrade an Existing App

### Step 1: Update Composer

In your Laravel app that depends on this package:

```bash
composer update dcplibrary/notices
```

Make sure it pulls in `^2.0` (or `2.x`) after the release has been tagged.

### Step 2: Run Migrations

```bash
php artisan migrate
```

This will create the new `notifications` and `notification_events` tables (and any other schema additions), without touching your existing data.

> **Do not** run `migrate:fresh` in production.

### Step 3: Backfill Master Notifications

Run the new projection command once to seed `notifications` + `notification_events` from your existing `notification_logs` data:

```bash
# Start conservatively to validate behavior
php artisan notices:sync-from-logs --days=7

# OR, for an explicit range
php artisan notices:sync-from-logs --from=2025-10-01 --to=2025-11-25
```

This will:

- Create one `notifications` row per logical notification (anchored by `notification_log_id` = Polaris `NotificationLogID`).
- Create one `notification_events` row per NotificationLog record (event_type: queued/delivered/failed, with channel-aware `status_text`).

You can re-run this command safely; it is idempotent per `notification_log_id`.

### Step 4: Update Your Schedulers/Cron Jobs

Anywhere you previously called:

- `php artisan notices:import-notifications ...`
- `php artisan notices:sync-shoutbomb-to-logs ...`

Update to the new flow, for example:

```bash
# 1) Import recent data from FTP, PhoneNotices, exports, failures
php artisan notices:import --days=1

# 2) Project NotificationLog into master notifications/events
php artisan notices:sync-from-logs --days=1

# 3) Aggregate summaries for yesterday
php artisan notices:aggregate --yesterday
```

If you use the package’s built-in scheduler (e.g. via Docker `schedule:work`), most of this is already wired for you. See `docs/SCHEDULER_DOCKER.md` for details.

### Step 5: Start Using the Master Model in Your Code (Optional but Recommended)

Where you previously relied directly on `NotificationLog` for queries, consider moving to the new model:

**Per-patron history:**

```php
use Dcplibrary\Notices\Models\Notification;

$notifications = Notification::with(['events', 'notificationLog'])
    ->where('patron_barcode', $barcode)
    ->orderByDesc('notice_date')
    ->get();
```

**Failed SMS/Voice notifications in a date range:**

```php
use Dcplibrary\Notices\Models\NotificationEvent;

$failed = NotificationEvent::where('event_type', NotificationEvent::TYPE_FAILED)
    ->whereIn('delivery_option_id', [3, 8]) // Voice (3) + SMS (8)
    ->whereBetween('event_at', [$start, $end])
    ->get();
```

You can migrate UI pieces gradually; existing `NotificationLog`-based code will continue to work.

---

## 4. New / Updated Documentation

2.0.0 adds package-level docs for the new model and scheduler:

- `docs/MASTER_NOTIFICATIONS.md`
  - Detailed description of `notifications` + `notification_events`.
  - How `NotificationLog` is projected via `NotificationProjectionService` and `notices:sync-from-logs`.
  - How PhoneNotices and export tables enrich the master records.

- `docs/SCHEDULER_DOCKER.md`
  - How to run `php artisan schedule:work` in a dedicated Docker container.
  - How to verify scheduled jobs (`schedule:list`), watch logs, and restart after schedule changes.

You do **not** need to change anything in the original Polaris notification verification docs; they still describe the upstream SQL sources. This upgrade adds a Laravel implementation layer that aligns with those docs.

---

## 5. FAQs

### Do I have to wipe any tables?

No. The upgrade is designed to be **additive** on the database:

- Existing tables (`notification_logs`, Shoutbomb tables, PhoneNotices, etc.) remain intact.
- New tables (`notifications`, `notification_events`) are populated from your existing `notification_logs`.

If you are in a development environment and want a totally clean slate, you can manually truncate `notifications` and `notification_events` and re-run `notices:sync-from-logs` – but this is **not** required for normal upgrades.

### What if I never used `notices:import-notifications` or `notices:sync-shoutbomb-to-logs`?

Then this major change is effectively **non-breaking** for your usage. You can simply:

- Update the package.
- Run `php artisan migrate`.
- Start using the new master model and command if/when you’re ready.

### Can I still query NotificationLog directly?

Yes. The `NotificationLog` model and `notification_logs` table are still present and used internally. The new master model simply layers a richer, vendor-agnostic view on top.

Over time, you’re encouraged to:

- Use `Notification` / `NotificationEvent` for most application-facing queries.
- Reserve `NotificationLog` for low-level debugging or alignment with Polaris docs.

---

## 6. Summary

- 2.0.0 introduces a **master notifications lifecycle** (`notifications` + `notification_events`) and a new projection command `notices:sync-from-logs`.
- It removes two legacy commands from the public surface: `notices:import-notifications` and `notices:sync-shoutbomb-to-logs`.
- Upgrading is straightforward:
  1. `composer update dcplibrary/notices`
  2. `php artisan migrate`
  3. `php artisan notices:sync-from-logs --days=7` (or date range)
  4. Update any schedulers/crons to use `notices:sync-from-logs`.

If you encounter issues during upgrade, check:

- `storage/logs/laravel.log`
- That migrations ran successfully (`php artisan migrate`)
- That your scheduler (cron/Docker) points to the new commands

> Note: v2.0.0 is the first release to introduce the master `notifications` + `notification_events` tables.
> This upgrade guide applies to all 2.x versions.
