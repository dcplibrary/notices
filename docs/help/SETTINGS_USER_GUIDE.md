# Notices Settings User Guide

This guide is for administrators who manage the Notices package configuration from the **Settings** area of the dashboard.

It explains what each Settings card does, how database-backed settings interact with `.env`/config, and how the **Shoutbomb Reports Integration** affects data ingestion and failure displays.

---

## 1. Settings Overview

Go to:

- **Menu → Settings** (`/notices/settings`)

You’ll see several cards:

- **Shoutbomb Reports Integration** (live)
- **Reference Data** (live)
- **Sync & Import** (live)
- **Export & Backup** (live)
- **System Settings** (coming soon)
- **Email Settings** (coming soon)
- **FTP Settings** (coming soon)
- **Dashboard Settings** (coming soon)

Each card either links to a sub‑page or exposes controls directly in place.

Behind the scenes, most configuration is stored in the `notification_settings` table via the `NotificationSetting` model and the `SettingsManager` service.

> If a setting exists in the database, it overrides the value from `.env` / `config/notices.php`. The UI will usually tell you when a database override is active.

---

## 2. Shoutbomb Reports Integration

**Card:** “Shoutbomb Reports Integration” on the main Settings page.

### 2.1 What it controls

This toggle controls the **Shoutbomb failure-report pipeline**:

- **Key:** `integrations.shoutbomb_reports.enabled`
- **Storage:** `notification_settings` (global scope)
- **Used by:**
  - `NotificationProjectionService` when deciding whether to link email-based failures into `notification_events`.
  - The “Sync & Import” and troubleshooting flows when showing Shoutbomb-related status.

When **enabled**:

- Shoutbomb failure **emails** (invalid phones, opt‑outs, voice failures, etc.) are ingested into the `notice_failure_reports` table.
- `NotificationProjectionService` looks for matching failure records for each `Notification` and adds **FAILED** `NotificationEvent` rows with:
  - `source_table = notice_failure_reports` (or configured name)
  - `status_text` such as “Shoutbomb failure: Invalid phone” or “Shoutbomb failure: Opted-out”.
- The dashboard “Troubleshooting” and timelines will show these reasons as part of the normal lifecycle.

When **disabled**:

- Existing data in `notice_failure_reports` remains, but:
  - New email failures do not have to be ingested/processed.
  - You can treat Shoutbomb failures as “unavailable” and rely mainly on Polaris + FTP delivery tables for status.

This is useful when:

- You are testing the system without Shoutbomb email integration.
- The Shoutbomb email format changes and you need time to adjust the parser.

### 2.2 UI behavior

On the card you’ll see:

- A pill badge:
  - **Enabled** – green badge when `integrations.shoutbomb_reports.enabled = true`.
  - **Disabled** – gray badge otherwise.
- A toggle switch:
  - Turning it on/off sends a POST request to `/notices/settings/integrations/shoutbomb-reports/toggle`.
  - The controller either creates the database setting or updates it via `SettingsManager`.
- A note under the toggle:
  - **“DB override active for this integration.”** – the value is coming from `notification_settings`.
  - **“No DB override set. Using .env/config.”** – the value is coming from `config/notices.php` / environment.
- A **“How to install”** link that opens a modal describing:
  - Required `.env` values for Shoutbomb.
  - Running `composer require dcplibrary/shoutbomb-reports` (if used separately).
  - Scheduling of the Shoutbomb check/ingest command.

### 2.3 Recommended admin actions

- **Initial setup:**
  1. Configure Shoutbomb FTP and email credentials in `.env` / `config/notices.php`.
  2. Enable the Shoutbomb Reports Integration toggle.
  3. Confirm that:
     - `notice_failure_reports` is being populated.
     - Timelines show specific Shoutbomb failure reasons for SMS/Voice.

- **During outages / parser issues:**
  - Temporarily disable the toggle.
  - This prevents misleading failure events while you adjust import logic.

---

## 3. Reference Data

**Card:** “Reference Data” on the main Settings page.

This links to a page where you manage **lookup tables** that drive the UI:

- Notification Types (`notification_types`)
- Delivery Methods (`delivery_methods`)
- Notification Statuses (`notification_statuses`)

### 3.1 What you can change

For each row you typically can:

- Enable/disable a type/method/status.
- Change **display order** (which affects sorting in dropdowns and dashboards).
- Override the **label** shown to staff (without changing raw Polaris values).

These changes do not change historical data; they affect how records are displayed and filtered.

### 3.2 When to use it

- Hiding rarely used notification types.
- Reordering delivery methods (for example, putting Email first).
- Renaming statuses to something staff will understand more easily.

---

## 4. Sync & Import

**Card:** “Sync & Import” on the main Settings page.

This opens the **Sync & Import** management view, which has two main parts:

1. **Summary cards + Recent Syncs table** (from `SyncLog`).
2. The **SyncAndImport Livewire component**, used for importing FTP files and patron lists with real-time feedback.

### 4.1 High-level summary cards

At the top, you’ll see cards such as:

- **Complete Sync** – last `sync_all` run.
- **FTP Files Import** – last `import_ftp_files` run.
- **Polaris Import** – last `import_polaris` run.
- **Shoutbomb Submissions** – last `import_shoutbomb_submissions` run.
- **Shoutbomb Reports** – last `import_shoutbomb_reports` run.
- **Aggregation** – last `aggregate` run.

These draw from the `sync_logs` table, using the latest entry for each operation type.

### 4.2 Recent Syncs table

The “Recent Syncs” section shows a table of the last N `SyncLog` entries, with:

- Operation label (e.g. “Import Polaris”, “Shoutbomb Reports”).
- Status (Completed / Failed / Completed with errors).
- Start time.
- Records processed.

Click **“View”** to open a modal with:

- Status, timestamps, duration.
- Error message (if any).
- Raw `results` payload, if the command recorded extra details.

### 4.3 Livewire Sync & Import panel

Below the history, the `<livewire:sync-and-import />` component lets you:

- Choose a **date range**:
  - Today, Yesterday, Last 7 days, or Custom.
- Toggle **“Import patron delivery preferences”** (`importPatrons` flag).
- Start an import of FTP-based submissions (e.g., holds/overdues/renewals, patron lists).

When you click **Start Import**:

- A `notices:import-ftp-files` command is built with the chosen date parameters, and optionally `--import-patrons`.
- Livewire listens for streamed progress events and updates:
  - Current file being processed.
  - Progress lines.
  - Import statistics.
- You can cancel the import, which dispatches a cancel event and shows a toast.

### 4.4 How this relates to Shoutbomb Reports

- FTP imports handle **outgoing** submissions and related reference data.
- Shoutbomb failure **emails** (for `notice_failure_reports`) are imported by separate commands/scheduler entries.
- Together, these pipelines feed the projection stage:
  - Exports + submissions + PhoneNotices + deliveries + failure reports → `notifications` + `notification_events`.

As an admin, you typically:

1. Use **Sync & Import** when you need to backfill or re-import recent days.
2. Use the **history and modal details** to confirm commands are running on schedule and diagnose issues.

---

## 5. Export & Backup

**Card:** “Export & Backup” on the main Settings page.

This page provides entry points for:

- Exporting reference data (types/methods/statuses) as JSON or SQL.
- Exporting notification data or snapshots.
- Initiating or downloading database backups (depending on how you wire it in your host app).

Use this when you:

- Need to move configuration between environments.
- Want a snapshot of notification logs for long-term archival outside the app.

---

## 6. Coming Soon Cards (System, Email, FTP, Dashboard)

These cards are currently placeholders but map to future `NotificationSetting` groups:

- **System Settings** – global, non-integration-specific behavior:
  - Default dashboard date range.
  - Feature flags.
- **Email Settings** – email ingestion and report parsing:
  - IMAP mailbox, filters, run frequency.
  - Safety knobs for parsing failures.
- **FTP Settings** – Shoutbomb FTP and local archive configuration:
  - FTP hosts, credentials, paths.
  - Which export types are enabled.
- **Dashboard Settings** – defaults for UI behavior:
  - Default filters (branches, date ranges).
  - Which widgets are visible.

As you implement each of these groups, you’ll:

1. Define `notification_settings` keys (e.g. `system.default_date_range`, `ftp.host`, `email.import_enabled`).
2. Wire them through `SettingsManager`.
3. Add UI controls under the appropriate card.
4. Update this guide and `docs/INDEX.md`.

---

## 7. DB Override vs .env / Config

Many settings can be set in **three places**:

1. `.env` → used by `config/notices.php`.
2. `config/notices.php` → default values for the package.
3. `notification_settings` (via Settings UI or seeding) → runtime overrides.

The **effective value** is resolved by `SettingsManager` roughly as:

1. Check `notification_settings` for the specific key and scope.
2. If not present, fall back to `config('notices.*')`.
3. If not present there, use a hard-coded default.

The Settings pages try to make this clear by:

- Showing when a DB override exists.
- Falling back to config when no DB record is present.

**Best practice:**

- Use `.env`/config for **environment-specific secrets** and low-level connection details.
- Use **Settings UI** for feature flags and integration toggles that staff may need to change without a deploy.

---

## 8. Admin Checklist

When deploying or upgrading the Notices package:

1. **Confirm migrations** have run (including `notification_settings`, `sync_logs`, `notice_failure_reports`, and the master `notifications` + `notification_events` tables).
2. **Verify Settings → Shoutbomb Reports Integration** shows the correct state and can be toggled.
3. **Verify Settings → Sync & Import**:
   - Summary cards show recent activity once imports run.
   - Manual FTP import works for a small date range.
4. **Check Troubleshooting and Verification timelines**:
   - Ensure SMS/Voice failures surface reasons when failure reports are enabled.
5. **Document any environment-specific details** (e.g., which scheduler flags are enabled) for your operations team.

Keeping this guide up to date alongside `docs/DEVELOPMENT_ROADMAP.md` will make ongoing maintenance and onboarding much easier for future admins.