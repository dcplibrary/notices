# AGENTS.md

Rules and reasoning for anyone, human or AI, who changes the `dcplibrary/notices` package.

Read this before touching notification import, reconciliation, or status logic. It explains what the package decides, how it decides it, and why each rule exists. The rules marked **Never** exist because breaking them has corrupted data before.

All examples here use made-up values. Do not put real patron names, phone numbers, email addresses, barcodes or Polaris IDs in code, comments, tests, docs or commit messages.

## What the package does

A library patron is told about holds, renewals and overdue items by one of four channels. The library system (Polaris) generates the notices, but two outside services do the delivering and neither reports back to Polaris reliably. This package rebuilds the full picture from several partial sources and answers one question per notification: **was it delivered, did it fail, or is it still too early to say?**

| Channel | `delivery_option_id` | Delivered by | How we learn the outcome |
| --- | --- | --- | --- |
| Mail | 1 | Staff, by printing | NotificationLog only |
| Email | 2 | LX Starter | NotificationLog and the daily Summary Report must agree |
| Voice | 3 | Shoutbomb | Shoutbomb failure emails; no failure means delivered |
| SMS | 8 | Shoutbomb | Shoutbomb failure emails; no failure means delivered |

Every notification, whatever the channel, is one row in `notifications` with a `status` of `pending`, `delivered` or `failed`.

## The data sources

| Source | Arrives as | Used for |
| --- | --- | --- |
| `holds_*.txt`, `renew_*.txt`, `overdue_*.txt` | Files exported for Shoutbomb, several times a day | Creating Voice and SMS notifications |
| NotificationLog | `notification_log_YYYY-MM-DD_HH-MM-SS.txt`, daily | Creating Email and Mail notifications, and their Polaris status |
| NotificationHistory | Daily export | Enrichment only |
| PhoneNotices | `PhoneNotices*.csv`, daily | Verifying and enriching Voice and SMS notifications |
| Summary Report | Email from Polaris, read through Microsoft Graph | Confirming Email delivery |
| Shoutbomb failure reports | Emails from Shoutbomb, read through Microsoft Graph | Detecting Voice and SMS failures |

Email reports are stored as `ingested_emails`; each parsed row becomes a `delivery_confirmations` record with a `confirmation_source`.

## Rule 1: each channel has exactly one creating source

**Never** create a notification from a source that does not own its channel.

| Source | May create | May enrich | Never |
| --- | --- | --- | --- |
| `holds_` / `renew_` / `overdue_` files | Voice (3), SMS (8) | Existing Email and Mail records | Create Email or Mail |
| NotificationLog | Email (2), Mail (1) | | Create Voice or SMS |
| PhoneNotices | nothing | Existing Voice and SMS records | Create anything |
| NotificationHistory | nothing | Existing Email and Mail records | Create anything |
| Summary Report | nothing | | Affect Voice, SMS or Mail |
| Shoutbomb failure reports | nothing | | Affect Email or Mail |

**Why.** The export files contain rows for all four delivery options, because Polaris writes every pending notice into them, but Shoutbomb only acts on Voice and SMS. If an Email row from a `holds_` file becomes a notification, the same notice then exists twice: once from the export file and once from NotificationLog, each tracked by different status rules that will disagree. This has happened; the result was duplicate notifications with wrong statuses.

**How it is enforced.** `NotificationReconciler::deduplicate()` filters every export-file query with `whereIn('delivery_option_id', [3, 8])`. Keep that filter on all three queries (holds, renewals, overdues).

**Enrichment is allowed across channels**, but enrichment must never change a status. Adding a patron name or item title to `raw_payload` is enrichment. Changing `status` is not.

Polaris sometimes writes Voice or SMS rows into NotificationLog. Treat those as noise and ignore them.

## Rule 2: nothing is decided early

Every channel starts as `pending`, and `pending` is a real answer meaning "the evidence is not in yet". The package would sooner leave a notification pending for a month than guess.

**Why.** The evidence arrives late and out of order. Shoutbomb's failure emails come the next morning. A Summary Report covers the previous day. If a notification is marked delivered before its failure report could have arrived, staff are told a patron was notified when they were not, and no one follows up.

**Time alone never changes a status.** A status changes only when the required evidence has been imported. If a report is never imported, the notifications that depend on it stay pending.

## Email (2): how success and failure are decided

### What must be true first

- The notification has a NotificationLog entry. If it does not, it does not exist for this package.
- Let `sent_date` be the calendar date of NotificationLog `notice_date`, in the notices timezone.
- A **valid** Summary Report for a date means one that parsed successfully and has at least one row. A report that parsed to zero rows is a failed import, not coverage.

### The gate

Email stays `pending` until a valid Summary Report for `sent_date` has been imported. This holds no matter how much time has passed.

**Why.** NotificationLog only says Polaris handed the email to LX Starter. The Summary Report is the only evidence of what LX Starter did with it. Without the report for that date there is nothing to compare against, so any verdict would be a guess.

### Polaris status codes

| `notification_status_id` | Meaning | Treated as |
| --- | --- | --- |
| 12 | Email completed | Success |
| 16 | Sent | Success |
| 13 | Email failed, invalid address | Failure |
| 14 | Email failed | Failure |
| NULL | Polaris has not set a status | See "NULL status" below |
| 7 | "Delivered (Email)" | Not treated as success; its meaning is a call status |

Status codes are channel-specific. A code that means success for Email means nothing for Mail.

### Decision order

Once the gate is open, `NotificationReconciler::reconcile()` applies the first rule that matches:

| # | NotificationLog says | Summary Report says | Result | Reason recorded |
| --- | --- | --- | --- | --- |
| 1 | Success | Success on `sent_date` | **Delivered** | |
| 2 | Success | Success on a nearby date, and no failure anywhere for this patron | **Delivered** | Date skew; see below |
| 3 | Failure | Failure | **Failed** | From the Summary Report |
| 4 | Success | Valid reports exist for `sent_date` and the next day, and this email is in neither as success or failure | **Failed** | Missing from Summary Reports |
| 5 | Success | No Summary Report row at all for this patron, on any date | **Failed** | LX Starter communication error |
| 6 | Failure | Success | **Delivered** | The Summary Report wins |
| 7 | Anything | Failure | **Failed** | From the Summary Report |
| 8 | Empty `delivery_string` | Nothing | **Failed** | Polaris/LX Starter error: no email address |
| 9 | Anything else | | Stays **pending** | |

**Why both sources must agree for "delivered" (rules 1 and 2).** Each source alone has been wrong. Polaris can record success for an email LX Starter never received. Requiring agreement means "delivered" is only claimed when the sender and the delivery service both say so.

**Why rule 2 exists.** An email exported late in the day can miss that morning's batch and go out one or two days later, so its confirmation lands in a later report. If a success exists near the date and there is no failure anywhere, calling it an LX Starter error would be a false alarm.

**Why failure needs the next day's report as well (rule 4).** Because of that same lag, an email missing from the `sent_date` report might still appear in tomorrow's. Only when the next day's report is also in, and the email is in neither, is absence meaningful. If the next day's report is missing or has zero rows, the email stays pending.

**Why the Summary Report wins a disagreement (rules 6 and 7).** It is the record of actual delivery. NotificationLog is the record of what Polaris intended.

**Why summaries are matched to this notification's own cycle.** Summary rows are looked up by patron and channel, then narrowed to rows on or after this notification's `sent_date`, taking the earliest. Without that, one old failure for a patron would be matched to every later email they receive.

### NULL status

If `notification_status_id` is NULL, Polaris has not finished with the notice. The reconciler waits until 48 hours after the datetime in the NotificationLog filename **and** an import has run after that point. If the status is still NULL, the email is marked failed with a Polaris error reason.

**Why.** NULL is usually temporary. Two import cycles give Polaris the chance to fill it in before the package calls it a Polaris fault.

### What kind of failure it is

A failed email is also classified, because the fix differs:

| Classification | Trigger | Who fixes it |
| --- | --- | --- |
| **Failed contact** | Status 13 (invalid address). Immediate. | Staff update the patron's email |
| **Failed delivery** | Status 14. Transient. | No one, unless it repeats |
| Failed delivery escalated to **failed contact** | Three or more status-14 failures for the same patron and the same email address | Staff update the patron's contact or delivery method |
| **Polaris / LX Starter error** | Empty `delivery_string`, a NULL status that never resolves, or success in NotificationLog with nothing in any Summary Report | IT; the patron's details are not the problem |

**Why the escalation.** One failed email may be a full inbox. Three to the same address means the address does not work, even though Polaris never said "invalid".

**Why system errors are kept apart.** Staff should not be asked to fix a patron record when the fault is between Polaris and LX Starter. Empty `delivery_string` rows often arrive in clusters at the same minute, which is a sign of a system fault and not of many bad patron records.

Implemented in `NotificationReconciler::flagEmailFailuresFromNotificationLog()` and the empty-`delivery_string` branch of `reconcile()`.

### Report email duplicates

`GraphEmailIngestionService` keeps one stored email per report type and report date.

- Structured reports (Summary Reports and the Shoutbomb reports) are identified by **report type and the date the report covers**, not by Microsoft Graph's message ID. A second email for a type and date already stored is skipped, even if its body differs.
- One exception: a Shoutbomb failure report already stored with zero parsed records is replaced when a later copy for the same date parses to at least one record.
- Emails that are not structured reports are deduplicated by message ID.
- A Summary Report that parses to zero records is still stored, for audit, and a warning is logged. It does not count as coverage: the email gate looks for Summary Report rows on `sent_date`, and a zero-record report contributes none.

**Why the covered date and not the message ID.** Graph has reused a message ID for different days' reports. Deduplicating on ID dropped a day's coverage, which left that day's email pending with nothing to compare against.

## Mail (1): how success is decided

- Mail exists only through NotificationLog.
- **Delivered** only when `notification_status_id` is 15 ("Mail Printed").
- Any other code, including the Email success codes 12 and 16, leaves Mail `pending`.
- There are no Mail failure codes. Mail does not fail in this package; it waits.
- Summary Reports never apply to Mail.

**Why.** Staff print and post mail by hand, and LX Starter is not involved. The only evidence that it happened is Polaris recording that it was printed. A lot of Mail legitimately stays pending.

## Voice (3) and SMS (8): how success and failure are decided

These two channels use the same logic.

### Failure is checked first, with no waiting

A Voice or SMS notification is **failed** as soon as a Shoutbomb failure is matched to it. The failure's own text is kept as the reason.

| `confirmation_source` | Shoutbomb report | What it means |
| --- | --- | --- |
| `shoutbomb_invalid_phone` | "Invalid patron phone number" | Shoutbomb considers the number invalid or opted out |
| `shoutbomb_voice_system_error` | "Calls that generated errors" | The call hit a carrier or system error |
| `shoutbomb_voice_failure` | "Voice notices that were not delivered" | A summary of undelivered calls for a date; may be transient |

**Matching a failure to a notification:**

1. By patron barcode.
2. If there is no barcode match, by phone number, but only for failures dated the notification's export date or the day after, and only for the same channel when the report states one.

**Why the date limit on phone matches.** Some reports carry only a phone number. A phone number is shared by every notice that patron ever gets, so an undated match would fail all of their later notifications as well.

### Delivered means "no failure was reported"

Shoutbomb never confirms success. A notification is **delivered** only when all three of these are true:

1. **24 hours have passed** since the datetime in the export filename, for example `holds_2026-01-06_09-05-01.txt`. If the filename cannot be parsed, `first_exported_at` is used.
2. **An import batch has run after** that 24-hour mark.
3. **A Shoutbomb report for that export date has been ingested**: at least one `ingested_emails` row of a Shoutbomb report type whose `report_date` equals the export date.

If any is missing, the notification stays `pending`. If it had been marked delivered and a condition no longer holds, it goes back to `pending` and `delivered_at` is cleared.

**Why each condition exists:**

- **24 hours:** Shoutbomb sends failure reports the next morning. Before then, "no failure" only means "no report yet".
- **An import after the mark:** time passing is not enough; the reports have to have been fetched. This matters most where imports are infrequent.
- **A report for that date:** if the mailbox was not read for that day, absence of a failure says nothing. This guards against marking a whole day delivered because of a narrow import window.

`delivered_at` is set to the export filename datetime, the moment the notice was handed to Shoutbomb.

### Which failures mean the patron's contact details are wrong

All three failure sources set `status` to `failed`, and all three are recorded against the phone number in `failed_delivery_strings` with their `failure_source`. Only two of them are shown to staff as a contact problem for Voice and SMS:

| Source | Listed as a failed contact |
| --- | --- |
| `shoutbomb_invalid_phone` | Yes |
| `shoutbomb_voice_system_error` | Yes |
| `shoutbomb_voice_failure` | No |

The filter is in `FailedContactQueryService`. If you add another place that lists failed contacts for Voice or SMS, apply the same filter.

**Why.** "Voice notices that were not delivered" includes calls that simply were not answered. Sending staff to correct a working phone number wastes their time and teaches them to ignore the list.

### Overdue escalation

Polaris sends `notification_type_id = 1` for every overdue notice, whatever stage it is at. Shoutbomb escalates on its own schedule, so the package works out the stage from days overdue (export date minus due date):

| Days overdue | Stage | `notification_type_id` | `schedule_step` |
| --- | --- | --- | --- |
| up to 6 | 1st overdue | 1 | 1 |
| 7 to 13 | 2nd overdue | 12 | 2 |
| 14 to 20 | 3rd overdue | 13 | 3 |
| 21 to 41 | Bill | 11 | 4 |
| 42 or more | Collection | 11 | 5 |

This applies to Voice and SMS only. Email and Mail use the type NotificationLog gives them. See `NotificationReconciler::calculateShoutbombOverdueSchedule()`.

### Mismatched patron preference

Shoutbomb sends a "Mismatched patron preference" email when several barcodes share one phone number but are set to different delivery methods, for example one to Voice and one to Text. Shoutbomb cannot deliver while they conflict.

Each row gives the home library, the phone, the barcodes with the newer preference, and the barcodes with the older one. With made-up values:

```
3 | 5555550100 | 20000000000001 | 20000000000002
```

- A `PatronDeliveryIssue` of type `mismatched_preference` is created for each barcode with the **older** preference, since those are the ones to change.
- It is a patron configuration problem. It is not a system failure and not a bad phone number.
- Staff fix it in Polaris by setting the older barcode's preference to match the newer one, then resolve the issue. Nothing in the code resolves it automatically.
- These issues are not tied to a notification (`notification_id` is NULL), and only one open issue is kept per barcode.

## Deduplication and combined messages

- The same notice appears in many export files, because Polaris re-exports it until it is resolved. Raw rows are grouped into one notification by patron, delivery option and notification type, per day.
- The first export in which a notice appears is its official time (`first_exported_at`).
- A patron receives one combined email, call or text covering several notices. Status belongs to the combined message: everything in it succeeds or fails together. Counts in the UI are of combined messages, not raw rows.

## Delivery issues

Issues are rows in `patron_delivery_issues`. They are what staff act on.

| Issue type | Meaning | Resolves by itself |
| --- | --- | --- |
| `failed_delivery_string` | The patron's contact detail is invalid or unreachable | Yes, when Polaris reports a different email or phone for that patron |
| `mismatched_preference` | Barcodes sharing a phone have conflicting preferences | No |
| `lx_starter_failure` | An Email, Voice or SMS notification has been pending for more than 48 hours | Yes, but only when the notification becomes delivered |
| `polaris_export_failure` | Exported from Polaris but never sent | No. The empty-address case is recorded already resolved, as a diagnostic |
| `null_preference` | The patron has no delivery method set | No |
| `manual_review` | Needs a person to look | No |
| `system_error` | A processing fault, including NotificationLog email rows with an empty `delivery_string` | No |

Notes on `lx_starter_failure`:

- It is raised only for notifications pending between 48 hours and 7 days. Mail is excluded, because Mail is expected to wait.
- One open issue is kept per patron, not per notification.
- If the notification later fails, the issue stays open: the failure confirms it.

**Why 48 hours.** A notification can be pending for a day or two in normal running, while NotificationLog or the confirming report has not been imported yet. Flagging sooner produced false alarms.

## What reconciliation looks at

- `reconcile($date)` processes notifications first exported on that date, plus any still `pending` or `failed` from the previous 30 days.
- It loads confirmations from 31 days before the date to 3 days after.

**Why.** Confirmations arrive late. Re-checking a month of open notifications on every run lets a late report settle an old notification without a separate back-fill job. The date passed in is the anchor, so replaying a past date gives the same answer whenever it is run.

## Rules for the data itself

- **Lookup IDs are Polaris IDs.** The `notification_types`, `delivery_options` and similar tables copy Polaris tables with the same IDs. Never renumber them or add rows with auto-increment IDs. Gaps, such as delivery option 8 following 3, are Polaris's. They are seeded by `LookupTablesSeeder`.
- **Verify column order against the Polaris export query that produced the file**, not against prose documentation. Those queries are kept outside this repository; `FtpNotificationImporter` records the order it expects. Import one file and check every field before importing in bulk.
- **Timezone.** Every datetime from a filename, export or email header is read in `config('notices.timezone')`. Display times in that timezone, in 12-hour format.

## Rules for the code

- **This is a package.** All code lives in the package. Do not copy migrations, views, routes or classes into the host application's `app/` directory; the service provider loads them from the package. Publishing config or views is optional.
- **Config keys mirror `.env` names.** A key in `config/notices.php` is its `.env` variable lower-cased, with the prefix as the section: `MICROSOFT_MAILBOX` is `notices.microsoft.mailbox`, `POLARIS_FTP_PATH` is `notices.polaris.ftp.path`. A new setting follows the same rule, is added to `notices:install` if a host must supply it, and is named in the docs exactly as the code reads it; tests enforce the last two.
- **Put logic in services.** Queries and decisions belong in classes such as `NotificationReconciler` and `NotificationService`, not in controllers.
- **Date options keep the same names.** The import commands (`ImportCommand`, `ImportEmailCommand`, `ImportFtpExportsCommand`) take `--date`, `--from`, `--to` and `--days`. Other commands take a subset. A new command that works on dates should use these names with the same meaning, not invent its own.
- **Keep PHPDoc current** on public services and controllers, including which statuses a method may change.

## When you change status logic

1. Re-read this file.
2. Change the rule here first, with the reason, so the document and the code never disagree.
3. Add or update tests for the behaviour, especially timing gates, success-by-absence, and NotificationLog versus Summary Report disagreements.
4. Run the full test suite.
5. If you are unsure whether a change breaks a rule above, ask before making it.

## Where the logic lives

| Concern | Location |
| --- | --- |
| Creating notifications, grouping raw rows | `NotificationReconciler::deduplicate()` |
| Deciding status for all channels | `NotificationReconciler::reconcile()` |
| Phone-number matching of Shoutbomb failures | `NotificationReconciler::phoneFailuresForNotification()` |
| Email failure classification and escalation | `NotificationReconciler::flagEmailFailuresFromNotificationLog()` |
| Recording failed phone numbers | `NotificationReconciler::flagFailedDeliveryStrings()` |
| Pending-too-long issues | `NotificationReconciler::flagLxStarterFailures()` |
| Which failures are listed as failed contacts | `FailedContactQueryService` |
| Report email storage and duplicates | `GraphEmailIngestionService` |
| Mismatched preference issues | `NotificationReconciler::flagMismatchedPreferences()` |
| Overdue stage calculation | `NotificationReconciler::calculateShoutbombOverdueSchedule()` |
| Parsing report emails | `src/Services/EmailReportParsers/` |

## Related documentation

This file is the authority on status rules. These explain the surrounding system:

| For | Read |
| --- | --- |
| How the code is laid out, and request flow | [docs/development/architecture.md](docs/development/architecture.md) |
| Commit format, releases and the stylesheet build | [docs/development/contributing.md](docs/development/contributing.md) |
| Running the tests | [docs/development/testing.md](docs/development/testing.md) |
| Interface conventions | [docs/development/ui.md](docs/development/ui.md) |
| The notification lifecycle, delivery options and issue types as staff see them | [docs/concepts.md](docs/concepts.md) |
| Every Artisan command and its options | [docs/commands.md](docs/commands.md) |
| HTTP endpoints | [docs/openapi.yaml](docs/openapi.yaml). A test fails if it and the registered routes disagree, so update both together. |
| Classes, methods and model attributes | The docblocks in `src/`. phpDocumentor builds the published class reference from them (`phpdoc.xml`), so a docblock is documentation; keep it true. |

If one of these disagrees with this file about how status is decided, this file and the code win. Fix the other document.
