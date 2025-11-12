# Shoutbomb Reports Integration (dcplibrary/shoutbomb-reports)

Optional integration that lets Notices read failure reports captured from a mailbox via Microsoft Graph/Entra, provided by the dcplibrary/shoutbomb-reports package. When enabled, Notices will:
- Mark a Voice/SMS notice as Failed if a matching failure row exists in the shoutbomb-reports table around the notice date.
- If a notice is known Submitted and no matching failure is found in that window, infer Delivered.

This does NOT replace importing Shoutbomb submissions via FTP. Continue running your existing submissions import.

## Prerequisites
- A Laravel application hosting both the Notices package and the shoutbomb-reports package (typically your main app).
- An Entra application with API permissions to read the mailbox folder containing Shoutbomb report emails.

## Install (in your Laravel app)
1) Require the package:
   ```bash
   composer require dcplibrary/shoutbomb-reports
   ```

2) Publish and run migrations:
   ```bash
   php artisan vendor:publish --provider="Dcplibrary\ShoutbombReports\ShoutbombReportsServiceProvider" --tag=migrations
   php artisan migrate
   ```

3) Configure .env (examples):
   ```env
   # Azure/Entra
   SHOUTBOMB_TENANT_ID=...
   SHOUTBOMB_CLIENT_ID=...
   SHOUTBOMB_CLIENT_SECRET=...

   # Mailbox to monitor
   SHOUTBOMB_USER_EMAIL=someone@example.org
   SHOUTBOMB_FOLDER=Shoutbomb

   # Storage
   SHOUTBOMB_FAILURE_TABLE=notice_failure_reports
   ```

4) Schedule the package’s sync/ingest commands as described in the package README.

## Enable in Notices
You can enable the integration either via .env or the Settings UI.

- .env (explicit):
  ```env
  NOTICES_SHOUTBOMB_REPORTS_ENABLED=true
  ```
- .env (reuse existing flag):
  ```env
  SHOUTBOMB_LOG_PROCESSING=true
  ```
- Settings UI (global DB setting):
  - integrations.shoutbomb_reports.enabled = true

Configuration keys resolved by Notices:
- Table name: `SHOUTBOMB_FAILURE_TABLE` (default `notice_failure_reports`)
- Date window: `SHOUTBOMB_REPORTS_DATE_WINDOW_HOURS` (default `24`)

## How matching works
- Channel: derived from `delivery_option_id` (Voice=3, SMS=8) → maps to `notice_type` of `Voice` or `SMS` in the failure table
- Phone: last 10 digits match (digits-only comparison)
- Time window: ±N hours around the notice’s `notification_date` (configurable, default 24)

If a failure row is found, the verification marks:
- delivery_status = `Failed`
- failure_reason from the row (or failure_type)

If no failure is found and the notice is already `submitted`, verification marks:
- delivery_status = `Delivered` (inferred)

## Disabling
- Set `NOTICES_SHOUTBOMB_REPORTS_ENABLED=false` (or `SHOUTBOMB_LOG_PROCESSING=false`) or toggle the setting off in the UI.
- When disabled or absent, Notices falls back to the existing FTP-based `shoutbomb_deliveries` data (if present).

## Notes
- This integration only reads data written by dcplibrary/shoutbomb-reports. It does not run any Graph API calls itself.
- Submissions import via FTP is unchanged and remains required to identify “submitted” notices.
- Ensure your app scheduler is running the shoutbomb-reports ingestion so the failure table stays updated.
