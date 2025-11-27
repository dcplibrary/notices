# DEVELOPMENT ROADMAP

This document tracks the current feature set, recent changes made during this development cycle, and recommended next steps.

It is intentionally high-level and should stay in sync with:
- `docs/ARCHITECTURE.md`
- `docs/IMPORT_SCHEDULE.md`
- `docs/shoutbomb/SHOUTBOMB_DOCUMENTATION_INDEX.md`

## Recent Changes (This Iteration)

### Settings & Integrations UI

- **Settings Index Page** (`resources/views/settings/index.blade.php`)
  - Added a dedicated **Shoutbomb Reports Integration** card.
  - The card now exposes a clear **Enable/Disable toggle** backed by the database setting `integrations.shoutbomb_reports.enabled`.
  - The UI shows the current state using an "Enabled/Disabled" pill badge.
  - Added messaging to indicate whether a **DB override** exists or the package is using `.env`/config defaults.
  - Included a "How to install" modal that documents the basic steps for enabling `dcplibrary/shoutbomb-reports` in a host Laravel app (env variables, scheduler, and Artisan command).

- **Settings Backing Logic**
  - The toggle persists state via `NotificationSetting` and `SettingsManager`:
    - Reads from `integrations.shoutbomb_reports.enabled` via `SettingsManager::get()` with fallback to `config('notices.integrations.shoutbomb_reports.enabled')`.
    - Uses `SettingsController::toggleShoutbombReports()` to create/update the DB setting and keep `updated_by` attribution.
  - This makes the integration **configurable at runtime** without code changes or redeploys.

### Sync & Import Integration Awareness

- The **Sync & Import** page (Livewire-based) now respects the same `integrations.shoutbomb_reports.enabled` setting when computing whether the Shoutbomb Reports integration is considered available.
- This keeps the dashboard, sync history, and configuration UI aligned around a single source of truth.

> If you add new integrations or settings, prefer the same pattern: DB-backed setting via `NotificationSetting` + `SettingsManager`, surfaced in the Settings UI with clear state and help text.

## Current Feature Snapshot

This section is a quick status view of the Settings cards visible in the dashboard. Keep it updated as cards move from "Coming Soon" to live features.

### Live / Implemented

- **Shoutbomb Reports Integration**
  - Toggle for `integrations.shoutbomb_reports.enabled`.
  - Install instructions modal for `dcplibrary/shoutbomb-reports`.
  - Used by Sync & Import to decide whether to surface Shoutbomb-related operations as available.

- **Reference Data**
  - Management UI for:
    - `notification_types`
    - `delivery_methods`
    - `notification_statuses`
  - Supports enabling/disabling entries, reordering, and adjusting display labels.

- **Sync & Import**
  - Livewire UI that exposes import/sync operations and their status using `SyncLog`.
  - Tracks operations such as:
    - `sync_all`
    - `import_polaris`
    - `import_ftp_files`
    - `import_shoutbomb_submissions`
    - `import_shoutbomb_reports`
    - `aggregate`

- **Export & Backup**
  - Entry point for exporting configuration and creating backups.
  - Intended for operational/DevOps users who need to safeguard configuration and data.

### Planned / Coming Soon (UI Present, Not Fully Implemented)

These cards are visible in the Settings UI but currently display **"Coming Soon"**. Treat them as the highest-priority candidates for future work.

- **System Settings**
  - Placeholder for global, non-integration-specific knobs (e.g., dashboard defaults, feature flags, date ranges, etc.).

- **Email Settings**
  - Placeholder for configuration related to email-based imports and report processing.

- **FTP Settings**
  - Placeholder for Shoutbomb FTP configuration (hosts, credentials, paths).

- **Dashboard Settings**
  - Placeholder for per-dashboard layout and default filters (date range, branches, channels).

## Recommended Next Steps

### 1. Finish the Settings Story

1. **Promote "Coming Soon" cards to real settings groups**
   - Define concrete `NotificationSetting` keys for:
     - `system.*` (global package behavior)
     - `email.*` (email ingestion/report parsing)
     - `ftp.*` (Shoutbomb FTP connection and paths)
     - `dashboard.*` (default filters and preferences)
   - Implement backing logic through `SettingsManager` so all of these are DB-backed and cache-aware.
   - Wire each group into the Settings UI with:
     - Clear descriptions and help text.
     - Sensible defaults from `config/notices.php`.

2. **Document the Settings model explicitly**
   - Expand or cross-link from `docs/ARCHITECTURE.md` to describe:
     - `NotificationSetting` data model and scopes.
     - `SettingsManager::get()` / `set()` / `getGroup()` patterns.
     - How UI elements (like the Shoutbomb toggle) map to these settings.

3. **Add an Admin-Facing Settings Guide**
   - Create `docs/help/SETTINGS_USER_GUIDE.md` explaining:
     - What each Settings card does.
     - When to toggle Shoutbomb Reports on/off.
     - How DB overrides interact with `.env`/config.
   - Link it from `docs/INDEX.md` under **User Documentation** and from the main `help/USER_GUIDE.md` as a subpage.

### 2. Tighten Shoutbomb Integration Documentation

1. **Align docs with the current integration behavior**
   - Review `docs/shoutbomb/SHOUTBOMB_DOCUMENTATION_INDEX.md` and related files to ensure they:
     - Reference the new `integrations.shoutbomb_reports.enabled` setting.
     - Explicitly call out that, when the integration is enabled, Shoutbomb failure reports are used to infer delivery failures (and absence of failure implies success on submitted notices).

2. **Add a short "Operations Playbook" section**
   - In the Shoutbomb docs index (or a new `SHOUTBOMB_OPERATIONS.md`), document:
     - How staff should interpret the dashboard when Shoutbomb Reports are enabled vs disabled.
     - How to temporarily disable the integration (via Settings) during outages or testing.

### 3. Roadmap & Housekeeping

1. **Keep this roadmap small but current**
   - Whenever you ship a feature touching:
     - Settings,
     - Sync & Import,
     - Shoutbomb integration,
     - or dashboard configuration,
   - Add a short bullet under **Recent Changes** and, if applicable, move items from **Planned** to **Live**.

2. **Prune references to removed functionality**
   - When you deprecate or remove features, immediately:
     - Update `docs/INDEX.md` to remove links to obsolete docs.
     - Remove or archive any documents that describe features no longer present in the codebase.
     - Adjust examples in `ARCHITECTURE.md`, `MASTER_NOTIFICATIONS.md`, and Shoutbomb docs so they do not reference commands, tables, or scheduled jobs that no longer exist.

3. **Tie roadmap items to concrete artifacts**
   - For each upcoming feature you add to this roadmap, specify:
     - **Where it will live in the UI** (e.g., Settings → System Settings card).
     - **Which settings keys** it will use.
     - **Which docs** you expect to update (user help vs developer/architecture).

By following these steps and keeping this document and `docs/INDEX.md` aligned, the documentation will remain accurate as the package evolves, and new contributors will have a clear picture of what exists today and what is planned next.