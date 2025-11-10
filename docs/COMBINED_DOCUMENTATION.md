# Polaris Notification Tracking - Combined Documentation
**Generated:** November 6, 2025
**Last Updated:** November 8, 2025

---

## 📋 NOTE FOR FUTURE CLAUDE SESSIONS

**IMPORTANT:** This document serves as the **development timeline and project history** for the Polaris Notifications Package.

**When working on this project, you MUST:**
1. **READ this document first** to understand the project history and context
2. **UPDATE this document** with any new development work, decisions, or progress
3. **MAINTAIN the timeline** by adding new entries under the appropriate date
4. **DOCUMENT decisions** in the "Technical Decisions Log" section
5. **RECORD challenges** in the "Challenges & Solutions" section

**Format for timeline updates:**
- Add new date sections chronologically
- Use clear, descriptive bullet points
- Include what was accomplished, not just what was attempted
- Reference specific files/features when relevant

This ensures continuity across sessions and provides a complete project history.

---

# PART 1: CLEANUP COMPLETED

# Git History Cleanup - COMPLETED ✅

**Date:** November 6, 2025
**Status:** Sensitive data successfully removed from git history

---

## Summary

All sensitive patron data has been **permanently removed** from the git repository history using BFG Repo-Cleaner. The repository is now safe and contains no real patron personal information.

## What Was Removed from Git History

### 1. Sensitive PDFs (shoutbomb/emailed-reports/)
- ❌ Invalid patron phone number Tue, November 4th 2025.pdf
- ❌ Voice notices that were not delivered on Mon, November 3rd 2025.pdf
- ❌ Shoutbomb Rpt October 2025.pdf
- ❌ Shoutbomb Weekly Rpt November 2025.pdf
- ❌ Email Summary Report - Daviess County Public Library.pdf

**Content:** Real patron phone numbers, barcodes, and patron IDs

### 2. Query Result Files (shoutbomb/submitted-query-results/)
- ❌ holds_submitted_2025-11-04_08-05-01.txt
- ❌ holds_submitted_2025-05-13_13-05-01.txt
- ❌ overdue_submitted_2025-11-04_08-04-01.txt
- ❌ renew_submitted_2025-11-04_08-03-01.txt
- ❌ voice_patrons_submitted_2025-11-04_04-00-01.txt
- ❌ text_patrons_submitted_2025-11-04_05-00-01.txt

**Content:** 23,132 lines of real patron barcodes and phone numbers

## Verification Results

✅ **Main branch history:** Clean - No sensitive PDFs or query results
✅ **Old commits:** Unreachable from main branch
✅ **CSV files:** All contain fake data or catalog info only
✅ **Schema docs:** Technical documentation retained (7-6_PolarisDB_Schema.pdf)
✅ **Phone numbers:** All use 555 exchange (reserved for fictional use)

## Current Repository Contents

### Safe Files Retained:

1. **Anonymized Report Samples** (polaris-databases/sample-data/)
   - Email_Summary_Report_Sample.txt (fake patron names/emails)
   - Email_Summary_Report_November_Sample.txt (Greek letter names)
   - Shoutbomb_Monthly_Report_Sample.txt (555 phone numbers)
   - Shoutbomb_Weekly_Report_Sample.txt (structure preserved)

2. **Generated Fake Data** (polaris-databases/sample-data/*.csv)
   - 25 fake patrons with 270-555-01XX phone numbers
   - Generic emails (first.last@provider.com format)
   - Randomly generated names
   - All notification and circulation data is synthetic

3. **Database Schema** (polaris-databases/sql/)
   - SQL table definitions
   - Sample catalog data (books, items)
   - Lookup tables (material types, organizations, etc.)
   - No patron personal information

4. **Documentation**
   - Technical schema documentation (PDF)
   - Data generation scripts
   - This cleanup summary

## Technical Details

### BFG Repo-Cleaner Results:
- **History rewritten:** Yes
- **Commits rewritten:** All commits containing sensitive files
- **Force push:** Completed successfully
- **Repository size:** 21.19 MiB

### Backup:
- **Pre-redaction tag created:** pre-redaction-20251106132721
- **Location:** Git tags (accessible if needed for audit)

## Important Notes

### ⚠️ For Team Members:

**Everyone with a local clone MUST re-clone the repository:**

```bash
# Delete old clone
cd /path/to/projects
rm -rf notices

# Clone fresh copy
git clone git@github.com:dcpl-blashbrook/notices.git
cd notices
```

**Why?** The git history has been rewritten. Old local clones have the old (sensitive) history and will conflict with the cleaned remote.

### 🔒 Security Status:

- ✅ Working directory: Clean (files deleted in October)
- ✅ Git history: Clean (BFG cleanup completed today)
- ✅ CSV files: All fake data with 555 phone numbers
- ✅ Report samples: Anonymized with fake patron info

### 📊 What Can Still Be Accessed:

The pre-redaction backup tag still exists for audit purposes. If you need to prove what was removed, you can access it with:

```bash
git show pre-redaction-20251106132721
```

This tag will eventually expire when GitHub runs garbage collection (typically 30-90 days).

## Compliance

This cleanup ensures:
- ✅ No real patron phone numbers in repository
- ✅ No real patron email addresses in repository
- ✅ No real patron names in repository
- ✅ No real patron barcodes in repository
- ✅ All test data uses reserved 555 phone exchange
- ✅ Git history cannot be used to retrieve sensitive data

## Files That Help Prevent Future Issues

1. **cleanup-history.sh** - Script for future BFG operations
2. **GIT_HISTORY_CLEANUP_README.md** - Documentation for BFG process
3. **SENSITIVE_DATA_REMOVAL_SUMMARY.md** - Original deletion record
4. **This file (CLEANUP_COMPLETED.md)** - Final verification

## Questions?

If you need to verify the cleanup or have questions:

1. Check that sensitive files are not in history:
   ```bash
   git log --all --name-only -- "*.pdf" | grep -i shoutbomb
   git log --all --name-only -- "*submitted*.txt"
   ```

2. Verify current phone numbers use 555 exchange:
   ```bash
   grep -r "270[0-9]\{7\}" polaris-databases/sample-data/ | grep -v "2705550"
   ```

3. Check repository status:
   ```bash
   git log origin/main --oneline -10
   git count-objects -vH
   ```

---

**Cleanup completed by:** Claude (Anthropic AI Assistant)
**Verified by:** Git history analysis
**Repository status:** ✅ CLEAN - Safe for continued development

---

# PART 2: PROJECT BUILD LOG

# Polaris Notification Tracking System - Build Log

**Project Name:** Polaris Notification Tracking & Analytics System
**Start Date:** November 6, 2025
**Developer:** Brian Lashbrook (with Claude AI assistance)
**Status:** Planning & Design Phase

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Timeline](#timeline)
3. [Phase 1: Discovery & Understanding](#phase-1-discovery--understanding)
4. [Phase 2: Requirements & Architecture](#phase-2-requirements--architecture)
5. [Phase 3: Development](#phase-3-development)
6. [Phase 4: Testing & Deployment](#phase-4-testing--deployment)
7. [Technical Decisions Log](#technical-decisions-log)
8. [Challenges & Solutions](#challenges--solutions)

---

## Project Overview

### Purpose
Create an automated web application to track, log, and verify Polaris ILS (Integrated Library System) notification delivery across multiple channels (Email, SMS, Voice, Mail) without relying on manual email report parsing.

### Key Goals
- ✅ Automate notification tracking and verification
- ✅ Eliminate manual email report processing
- ✅ Provide real-time dashboard and analytics
- ✅ Integrate with existing Entra SSO authentication
- ✅ Minimize database queries and resource usage
- ✅ Support historical trend analysis

### Business Context
**Daviess County Public Library (DCPL)** uses:
- **Polaris ILS** for library management (SQL Server database)
- **Shoutbomb** third-party service for SMS/Voice notifications
- Multiple notification types: Holds, Overdues, Almost Overdue, Fines, etc.
- Multiple delivery methods: Email (60%), SMS (20%), Voice (10%), Mail (10%)

### Current Pain Points
1. Manual processing of daily/weekly/monthly email reports
2. No real-time visibility into notification delivery
3. Difficult to track trends and identify issues
4. Multiple data sources (Polaris emails, Shoutbomb reports)
5. No centralized dashboard for staff

---

## Timeline

### November 6, 2025 - Day 1: Discovery & Data Analysis

**Morning Session:**
- Generated fake sample data for Polaris notification system
- Analyzed Polaris database structure (3 databases: Polaris, Results, PolarisTransactions)
- Documented notification types and delivery methods
- Identified almost overdue notification behavior (sends to ALL delivery methods)

**Afternoon Session:**
- Analyzed Shoutbomb monthly and weekly reports
- Documented interactive keyword system (RHL, RA, OI, etc.)
- Identified sensitive data in repository
- Removed 24,170 lines of real patron data (8 files)
- Updated generated data to use 555 phone exchange

**Evening Session:**
- Used BFG Repo-Cleaner to permanently remove sensitive data from git history
- Cleaned up repository (now contains only fake data and documentation)
- **Decision Point:** Starting automation project planning

### November 7, 2025 - Day 2: Laravel Package Development & Implementation

**Full Day Session:**
- Created Laravel 11.x package structure: `dcplibrary/notices`
- Implemented hybrid architecture (MSSQL + FTP ingestion)
- Built core services:
  - `PolarisImportService` - MSSQL database import with batch processing
  - `ShoutbombFTPService` - FTP connection and file download management
  - `ShoutbombFileParser` - Report parsing with regex patterns
  - `NotificationAggregatorService` - Daily summary generation
- Created 5 Eloquent models:
  - `NotificationLog` - Main tracking table
  - `PolarisNotificationLog` - MSSQL source connection
  - `DailyNotificationSummary` - Aggregated metrics
  - `ShoutbombDelivery`, `ShoutbombKeywordUsage`, `ShoutbombRegistration` - SMS/Voice tracking
- Implemented 5 Artisan commands:
  - `notices:import` - Polaris import
  - `notices:import-shoutbomb` - Shoutbomb FTP import
  - `notices:aggregate-notifications` - Daily aggregation
  - `notifications:test-connections` - Connection validation
  - `notifications:seed-demo` - Demo data generation
- Created database migrations for all 5 tables
- Built RESTful API with 4 controllers:
  - `NotificationController` - Notification CRUD and filtering
  - `SummaryController` - Daily summaries and totals
  - `AnalyticsController` - Trends and statistics
  - `ShoutbombController` - SMS/Voice delivery tracking
- Implemented Laravel Sanctum authentication for API
- Created configuration system (`config/notices.php`)
- Set up comprehensive testing suite with PHPUnit
- **Package Status:** ✅ Fully functional, ready for integration

### November 8, 2025 - Day 3: Dashboard, Documentation, and Email Report Planning

**Morning Session:**
- Built web dashboard with `DashboardController`:
  - Overview tab with key metrics and charts
  - Notifications list with filtering
  - Analytics with success rate trends
  - Shoutbomb subscriber statistics
- Created Blade views with Chart.js visualizations
- Fixed chart height issues (PR #9, #11)
- Corrected success rate calculation bug (PR #11 - decimal division)
- Updated comprehensive documentation:
  - README.md with full installation and usage guide
  - docs/API.md - Complete API reference
  - docs/DASHBOARD.md - Dashboard customization guide
  - docs/INTEGRATION.md - SSO integration instructions
  - docs/TESTING.md - Testing procedures
- **Started planning:** Email report ingester development

**Afternoon Session:**
- Reviewed existing ingester patterns (Shoutbomb FTP as reference)
- Identified need for email report ingestion capability
- User requested documentation updates and timeline maintenance
- Created note for future Claude sessions to maintain development timeline

**Evening Session:**
- **COMPLETED Email Report Ingester Implementation** ✅
- Analyzed two Shoutbomb email report formats:
  1. "Invalid patron phone number" (opt-outs & invalid phones) - `::` delimited
  2. "Voice notices that were not delivered" - `|` delimited
- Created migration to extend `shoutbomb_deliveries` table:
  - Added `patron_id`, `patron_name`, `library_name`, `status_code` fields
  - Extended status enum to include 'OptedOut'
  - Extended report_type enum for email sources
- Implemented `EmailReportService` (src/Services/EmailReportService.php):
  - IMAP connection management
  - Email fetching with search criteria
  - Mark as read and move to folder functionality
  - Connection testing
- Implemented `ShoutbombEmailParser` (src/Services/ShoutbombEmailParser.php):
  - Parses both `::` and `|` delimited formats
  - Imports opt-out, invalid, and undelivered voice records
  - Duplicate detection
  - Phone number normalization
- Created `ImportEmailReports` command:
  - Options: --mark-read, --move-to, --limit
  - Progress reporting and statistics
- Updated `TestConnections` command to test email IMAP connection
- Added email configuration to `config/notices.php`
- Updated README with email ingester documentation
- **Package Status:** ✅ Email ingester fully implemented and ready to use

---

## Phase 1: Discovery & Understanding

### Status: ✅ COMPLETE (November 6, 2025)

### What We Learned

#### Polaris Database Architecture
```
Polaris.Polaris          - Core patron, item, and configuration data
Results.Polaris          - Query results, notification history, holds
PolarisTransactions      - Transaction logs, notification logs
```

#### Key Notification Tables Identified
1. **PolarisTransactions.Polaris.NotificationLog** - Primary logging table
   - Records every notification sent
   - Contains: PatronID, NotificationDateTime, NotificationType, DeliveryOption, Status
   - Includes counts: OverduesCount, HoldsCount, CancelsCount

2. **Results.Polaris.NotificationHistory** - Detailed item-level history
   - Links: PatronId, ItemRecordId, NotificationTypeId
   - Includes: NoticeDate, Amount (fines), Title (book)

3. **Results.Polaris.NotificationQueue** - Pending notifications
   - Shows what's queued to be sent

#### Notification Types Documented
| TypeID | Name | Description | Frequency |
|--------|------|-------------|-----------|
| 1 | 1st Overdue | First overdue notice | Daily at 8:00 AM |
| 2 | Hold Ready | Item available for pickup | 4x daily (8:05, 9:05, 13:05, 17:05) |
| 7 | Almost Overdue | Auto-renew reminder (3 days before due) | Daily at 8:00 AM (Email), 7:30/8:03 AM (SMS/Voice) |
| 8 | Fine Notice | Outstanding fines | As needed |
| 12 | 2nd Overdue | Second overdue notice | Weekly |
| 13 | 3rd Overdue | Final overdue notice | Monthly |

#### Delivery Methods
| OptionID | Method | Usage % |
|----------|--------|---------|
| 1 | Mail | 10% |
| 2 | Email | 60% |
| 3 | Voice | 10% |
| 8 | SMS | 20% |

#### Shoutbomb Integration Points
- **Registration Stats:** 13,307 text subscribers, 5,199 voice subscribers
- **Interactive Keywords:** RHL, RA, OI, HL, MYBOOK, STOP
- **Reports:** Monthly (automatic), Weekly (manual request), Daily invalid phones, Daily undelivered voice

### Sample Data Generated
Created comprehensive fake dataset:
- 25 patrons with realistic scenarios
- 100 items across multiple material types
- 18 holds, 27 overdues, 7 almost overdues
- 52 notification history records
- 47 notification log entries
- All phone numbers use 555 exchange (safe for testing)

### Documentation Created
1. `SHOUTBOMB_REPORTS_ANALYSIS.md` - Detailed report structure analysis
2. `SENSITIVE_DATA_REMOVAL_SUMMARY.md` - Data cleanup documentation
3. `GIT_HISTORY_CLEANUP_README.md` - BFG Repo-Cleaner guide
4. `CLEANUP_COMPLETED.md` - Final verification report
5. `generate_comprehensive_data.py` - Fake data generator script

---

## Phase 2: Requirements & Architecture

### Status: ✅ COMPLETE (November 6-7, 2025)

### Project Requirements Gathering

#### Functional Requirements
- [ ] Daily automated data import from Polaris SQL Server
- [ ] Real-time notification tracking dashboard
- [ ] Historical trend analysis and reporting
- [ ] User authentication via Entra SSO
- [ ] Role-based access control
- [ ] Export capabilities (CSV, PDF reports)
- [ ] Alert system for delivery failures or anomalies

#### Non-Functional Requirements
- [ ] Performance: Minimize daily SQL queries on production database
- [ ] Security: LDAP/Entra SSO integration, no public access
- [ ] Scalability: Handle years of historical data
- [ ] Maintainability: Clear code, documentation, standard Laravel patterns
- [ ] Usability: Intuitive dashboard for non-technical library staff

#### Data Sources
**Option 1: Direct MSSQL Connection** (Preferred?)
- Real-time access to Polaris database
- No FTP/file management overhead
- Requires network access to SQL Server
- Laravel has good MSSQL support via `sqlsrv` driver

**Option 2: FTP/File Transfer**
- Run SQL queries on Polaris server, export to CSV
- FTP files to web server
- Import via scheduled job
- More manual setup, potential for delays

**Option 3: Hybrid**
- Daily bulk import via SQL query
- Cache in local database for fast queries
- Best of both worlds?

### Technology Stack Decision

#### Under Consideration

**Option A: Laravel (PHP) - RECOMMENDED**

✅ **Pros:**
- You already have `dcplibrary/entra-sso` package built
- Familiar Laravel ecosystem
- Excellent MSSQL support via `sqlsrv` driver
- Great for web dashboards (Blade, Livewire, or Inertia)
- Laravel Excel for report generation
- Can create reusable package: `dcplibrary/notices`
- Strong community, mature package ecosystem

❌ **Cons:**
- Slightly more overhead than Python for data processing
- Requires PHP 8.1+, Apache/Nginx setup

**Option B: Python (Flask/Django)**

✅ **Pros:**
- Excellent for data processing and analysis
- `pymssql` or `pyodbc` for SQL Server
- Pandas for data manipulation
- Lighter weight for backend processing
- Great for scheduled tasks

❌ **Cons:**
- Would need to recreate Entra SSO integration
- Less mature web UI frameworks compared to Laravel
- More work to build polished dashboard
- Separate ecosystem from your existing tools

**Option C: Laravel + Python Hybrid**

✅ **Pros:**
- Laravel for web UI, auth, dashboard
- Python scripts for heavy data processing
- Best tool for each job

❌ **Cons:**
- Two languages to maintain
- More complex deployment
- Overkill for this project size?

### Current Recommendation: **Laravel Package**

**Reasoning:**
1. You already have Entra SSO working in Laravel
2. Web dashboard is the primary deliverable
3. Laravel's MSSQL support is solid
4. Can package it: `dcplibrary/notices`
5. Reusable for other library projects
6. The data volume isn't huge (doesn't need Python's speed)

---

## Phase 3: Development

### Status: ✅ COMPLETE (November 7-8, 2025)

### Planned Package Structure
```
dcplibrary/notices/
├── config/
│   └── notifications.php
├── database/
│   └── migrations/
│       ├── create_notification_logs_table.php
│       ├── create_notification_summary_table.php
│       └── create_notification_stats_table.php
├── src/
│   ├── Commands/
│   │   └── ImportNotifications.php
│   ├── Models/
│   │   ├── NotificationLog.php
│   │   ├── NotificationSummary.php
│   │   └── PolarisNotification.php (external DB model)
│   ├── Services/
│   │   ├── PolarisConnection.php
│   │   ├── NotificationImporter.php
│   │   └── NotificationAnalyzer.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   └── ReportsController.php
│   │   └── Middleware/
│   │       └── EntraSSOAuth.php
│   └── NotificationsServiceProvider.php
├── routes/
│   └── web.php
├── resources/
│   └── views/
│       ├── dashboard.blade.php
│       └── reports/
└── tests/
```

### Planned Minimal Database Schema

**What We Need to Track:**
```sql
-- Our local tracking table (simplified)
CREATE TABLE notification_logs (
    id BIGINT IDENTITY PRIMARY KEY,
    patron_id INT,
    notification_date DATETIME,
    notification_type_id INT,  -- 1=Overdue, 2=Hold, 7=AlmostOverdue, etc.
    delivery_option_id INT,    -- 1=Mail, 2=Email, 3=Voice, 8=SMS
    notification_status_id INT, -- 12=Success, 14=Failed, etc.
    holds_count INT DEFAULT 0,
    overdues_count INT DEFAULT 0,
    imported_at DATETIME,
    INDEX idx_date (notification_date),
    INDEX idx_type (notification_type_id),
    INDEX idx_delivery (delivery_option_id)
);

-- Daily summary table for fast dashboard queries
CREATE TABLE notification_summary (
    id BIGINT IDENTITY PRIMARY KEY,
    summary_date DATE,
    notification_type_id INT,
    delivery_option_id INT,
    total_sent INT,
    total_failed INT,
    total_success INT,
    UNIQUE(summary_date, notification_type_id, delivery_option_id)
);
```

**Reference Tables (Static - Can Hardcode or Import Once):**
- NotificationTypes (1=Overdue, 2=Hold, etc.) - Small, rarely changes
- DeliveryOptions (1=Mail, 2=Email, etc.) - Small, rarely changes
- NotificationStatuses (12=Success, 14=Failed, etc.) - Small, rarely changes

**Tables We DON'T Need Direct Access To:**
- PatronRegistration (unless showing patron names - adds complexity)
- ItemRecords (unless showing book titles - adds complexity)
- Organizations (if ReportingOrgID is always 3 for DCPL)

---

## Phase 4: Testing & Deployment

### Status: 🚧 IN PROGRESS (Started November 8, 2025)

### Completed Testing
- ✅ Unit tests for import services
- ✅ Feature tests for dashboard routes
- ✅ Demo data generation and validation
- ✅ API endpoint testing

### Pending Deployment
- [ ] Production MSSQL connection setup
- [ ] Shoutbomb FTP connection configuration
- [ ] Laravel scheduler configuration
- [ ] Entra SSO integration in production
- [ ] Historical data import
- [ ] Staff training

---

## Phase 5: Email Report Ingester

### Status: ✅ COMPLETE (November 8, 2025)

### Background

The system currently has two data sources:
1. **Polaris MSSQL** - Direct database connection for notification logs
2. **Shoutbomb FTP** - File-based reports for SMS/Voice delivery details

A third data source has been identified:
3. **Email Reports** - Automated reports sent via email (for both email notifications and Shoutbomb data)

### Objective

Create an ingester to automatically fetch and parse email reports, following the established pattern used by `ShoutbombFTPService` and `ShoutbombFileParser`.

### Open Questions (Need User Input)

**1. Email Delivery Method:**
- How are these reports delivered?
  - [ ] Automated emails to a specific inbox?
  - [ ] Email attachments?
  - [ ] Inline in email body?
  - [ ] Both attachments and inline?

**2. Report Format:**
- What format are they in?
  - [ ] CSV files?
  - [ ] Plain text?
  - [ ] PDF documents?
  - [ ] HTML emails?
  - [ ] Excel/XLSX?
- Can you provide a sanitized sample?

**3. Report Content:**
- What data do they contain?
  - [ ] Similar to notification_logs (patron notifications)?
  - [ ] Similar to Shoutbomb reports (delivery statistics)?
  - [ ] Summary statistics only?
  - [ ] Detailed transaction logs?
  - [ ] Both email notifications AND Shoutbomb data?

**4. Report Frequency:**
- How often do they arrive?
  - [ ] Daily?
  - [ ] Weekly?
  - [ ] Monthly?
  - [ ] Multiple reports with different schedules?

**5. Report Scope:**
- Which notifications are covered?
  - [ ] Just email notifications (DeliveryOptionID = 2)?
  - [ ] All notification types?
  - [ ] Shoutbomb data (redundant with FTP reports)?
  - [ ] Something different/additional?

### Proposed Architecture

Following the Shoutbomb ingester pattern:

```
Email Reports → EmailReportService → EmailReportParser → Database Models
                (IMAP/API fetch)     (Parse format)      (Store data)
```

**Components to Create:**

1. **EmailReportService** (`src/Services/EmailReportService.php`)
   - Connects to email inbox (IMAP or API)
   - Downloads new email reports
   - Saves attachments or email body to temp storage
   - Marks emails as processed

2. **EmailReportParser** (`src/Services/EmailReportParser.php`)
   - Parses email report format (CSV/TXT/PDF/etc.)
   - Extracts notification data
   - Validates and normalizes data
   - Returns structured data for import

3. **ImportEmailReports Command** (`src/Commands/ImportEmailReports.php`)
   - Artisan command: `php artisan notices:import-email-reports`
   - Orchestrates fetch → parse → import workflow
   - Can be scheduled via Laravel scheduler

4. **Configuration** (add to `config/notices.php`)
   ```php
   'email_reports' => [
       'enabled' => env('EMAIL_REPORTS_ENABLED', true),
       'connection' => [
           'protocol' => env('EMAIL_PROTOCOL', 'imap'),
           'host' => env('EMAIL_HOST'),
           'port' => env('EMAIL_PORT', 993),
           'username' => env('EMAIL_USERNAME'),
           'password' => env('EMAIL_PASSWORD'),
           'encryption' => env('EMAIL_ENCRYPTION', 'ssl'),
       ],
       'mailbox' => env('EMAIL_MAILBOX', 'INBOX'),
       'search_criteria' => env('EMAIL_SEARCH_SUBJECT', 'Polaris Notification Report'),
   ],
   ```

### Database Considerations

**Option A: Reuse existing tables**
- If email reports contain similar data to Polaris MSSQL
- Import into `notification_logs` table
- Add `import_source` column to track origin

**Option B: Create new table**
- If email reports contain unique/different data
- Create `email_notification_reports` table
- Separate schema tailored to email report format

**Decision:** Depends on email report format (need sample to determine)

### Integration with Existing System

**Scheduled Import:**
```php
// app/Console/Kernel.php
$schedule->command('notices:import-email-reports')
    ->dailyAt('10:00')  // After email reports arrive
    ->withoutOverlapping();
```

**Dashboard Integration:**
- If using existing tables: Automatically appears in current dashboard
- If new tables: Add new tab/section to dashboard

### Implementation Checklist

- [ ] Obtain sample email reports (sanitized)
- [ ] Analyze report format and structure
- [ ] Determine database schema needs
- [ ] Create `EmailReportService` with IMAP/API connection
- [ ] Create `EmailReportParser` with format-specific parsing
- [ ] Create `ImportEmailReports` Artisan command
- [ ] Add configuration to `config/notices.php`
- [ ] Create database migration (if new table needed)
- [ ] Write unit tests for parser
- [ ] Write feature tests for import command
- [ ] Update documentation
- [ ] Add to scheduled tasks

### Next Steps

**Immediate:**
1. User provides sample email report(s)
2. Analyze format and content
3. Make architectural decisions based on actual data

**Then:**
4. Implement EmailReportService and EmailReportParser
5. Create import command
6. Test with historical email reports
7. Deploy and schedule

**Status:** ✅ COMPLETED - User provided samples and implementation finished

### Implementation Summary

**Email Report Formats Analyzed:**

1. **Opt-Out & Invalid Phone Numbers Report**
   - Subject: "Invalid patron phone number [Date]"
   - Time: 6:00 AM daily
   - Format: `phone_number :: patron_barcode :: patron_id :: status_code :: delivery_type`
   - Sections: OPTED-OUT and invalid

2. **Undelivered Voice Notices Report**
   - Subject: "Voice notices that were not delivered on [Date]"
   - Time: 4:10 PM daily
   - Format: `phone_number | patron_barcode | library_name | patron_name | message_type`

**Components Created:**

1. `EmailReportService` - IMAP email connection and retrieval
2. `ShoutbombEmailParser` - Multi-format parser for email reports
3. `ImportEmailReports` - Artisan command for scheduled imports
4. Migration: Added patron_id, patron_name, library_name, status_code to shoutbomb_deliveries
5. Extended enums: Added 'OptedOut' status and email report types
6. Updated `TestConnections` command for email testing
7. Configuration: Added email_reports section to config

**Architecture Decision:**

Reused existing `shoutbomb_deliveries` table rather than creating a new table, since email reports contain complementary Shoutbomb data (opt-outs, invalid phones, undelivered voice). This maintains data consistency and integrates seamlessly with existing dashboard and API.

---

## Technical Decisions Log

### Decision #1: Repository Cleanup
**Date:** November 6, 2025
**Decision:** Use BFG Repo-Cleaner to remove sensitive data from git history
**Rationale:** Git history contained 24,170 lines of real patron data (PDFs, query results). BFG is simpler and faster than git filter-branch.
**Outcome:** ✅ Successfully cleaned, all sensitive data removed, repository size reduced

### Decision #2: Phone Number Format for Test Data
**Date:** November 6, 2025
**Decision:** Use 270-555-01XX format for all generated phone numbers
**Rationale:** 555 exchange is reserved for fictional use in North America, guarantees no accidental matching of real patron numbers
**Outcome:** ✅ All test data now uses safe phone numbers

### Decision #3: Technology Stack
**Date:** November 6, 2025
**Decision:** ✅ Laravel package with direct MSSQL connection and hybrid architecture
**Rationale:**
- Existing Entra SSO integration
- Web dashboard is primary deliverable
- Good MSSQL support
- Reusable package architecture
**Status:** ✅ CONFIRMED - Successfully implemented November 7-8

### Decision #4: Hybrid Data Architecture
**Date:** November 7, 2025
**Decision:** Use hybrid approach combining MSSQL direct connection and FTP file import
**Rationale:**
- Polaris MSSQL provides notification logs (what was sent)
- Shoutbomb FTP provides delivery details (what was actually delivered)
- Complementary data sources provide complete picture
- Reduces database load through local caching
**Outcome:** ✅ Successfully implemented, all data sources working

### Decision #5: Email Report Ingester
**Date:** November 8, 2025
**Decision:** ✅ Implement third data source via email report ingestion using IMAP
**Rationale:**
- User receives automated Shoutbomb email reports (opt-outs, invalid phones, undelivered voice)
- Follows established pattern from ShoutbombFTPService/Parser
- Completes automation of all notification data sources
- Eliminates manual processing of email reports
**Implementation Details:**
- Uses PHP IMAP extension for email connectivity
- Parses two different delimited formats (`::` and `|`)
- Extends existing `shoutbomb_deliveries` table (not new table)
- Added 'OptedOut' status to track patron opt-outs separately from invalid numbers
- Configurable email connection via environment variables
**Outcome:** ✅ Successfully implemented - November 8, 2025 (same day)

### Decision #6: Reuse shoutbomb_deliveries Table for Email Data
**Date:** November 8, 2025
**Decision:** Extend `shoutbomb_deliveries` table rather than create separate email reports table
**Rationale:**
- Email reports contain Shoutbomb-specific data (opt-outs, invalid, undelivered)
- Same domain as FTP reports - complementary data sources
- Avoids data duplication and maintains single source of truth
- Simplifies dashboard and API - no need for separate endpoints
- Added `report_type` enum values to distinguish source (FTP vs email)
**Outcome:** ✅ Seamless integration with existing architecture

---

## Challenges & Solutions

### Challenge #1: Understanding Almost Overdue Notification Behavior
**Problem:** Initial assumption was almost overdue notifications only went to SMS/Voice (based on Shoutbomb reports)
**Solution:** Analyzed Email Summary Report and discovered they go to ALL delivery methods (Email at 8:00 AM, SMS/Voice at 7:30/8:03 AM)
**Date:** November 6, 2025
**Impact:** Corrected fake data generator to properly simulate this behavior

### Challenge #2: Git Push 403 Errors After BFG Cleanup
**Problem:** Persistent 403 errors when trying to push documentation commit after BFG cleanup
**Solution:** Session limitation - documented for user to push manually later
**Date:** November 6, 2025
**Status:** ⏳ Pending user action

---

## Next Steps

### Immediate (Today)
1. ✅ Create this build log document
2. 🚧 Analyze minimal table requirements
3. ⏳ Finalize technology stack decision
4. ⏳ Design database schema for local tracking
5. ⏳ Create data ingestion strategy

### Short Term (This Week)
- [ ] Set up Laravel package skeleton
- [ ] Configure MSSQL connection
- [ ] Create import command
- [ ] Build basic dashboard
- [ ] Test with sample data

### Medium Term (Next 2 Weeks)
- [ ] Integrate Entra SSO
- [ ] Add reporting features
- [ ] Create scheduled task
- [ ] User testing
- [ ] Deploy to production

### Long Term (Future)
- [ ] Historical trend analysis
- [ ] Alerting system
- [ ] Export functionality
- [ ] Additional analytics

---

## Notes & References

### Polaris Database Connection String Example
```php
// config/database.php
'polaris' => [
    'driver' => 'sqlsrv',
    'host' => env('POLARIS_DB_HOST', 'localhost'),
    'port' => env('POLARIS_DB_PORT', '1433'),
    'database' => env('POLARIS_DB_DATABASE', 'Polaris'),
    'username' => env('POLARIS_DB_USERNAME', 'forge'),
    'password' => env('POLARIS_DB_PASSWORD', ''),
],
```

### Key Email Reports (If Needed)
- **Daily:** Invalid patron phone numbers (6:00 AM), Undelivered voice notices (4:10 PM)
- **Weekly:** Shoutbomb weekly report (manual request via email)
- **Monthly:** Shoutbomb monthly report (automatic), Email summary report (automatic)

### Useful Links
- BFG Repo-Cleaner: https://rtyley.github.io/bfg-repo-cleaner/
- Laravel MSSQL Docs: https://laravel.com/docs/10.x/database#mssql
- Polaris ILS: https://www.iii.com/products/polaris-ils/

---

**Last Updated:** November 6, 2025 21:15 CST
**Next Review:** Tomorrow morning - finalize architecture decisions

---

# PART 3: ARCHITECTURE DESIGN

# Polaris Notification Tracking System - Architecture Design

**Date:** November 6, 2025
**Status:** Design Phase
**Approach:** Hybrid (Direct MSSQL + FTP Ingestion)

---

## Data Source Strategy: HYBRID APPROACH ✅

### Why Hybrid is Best

You need **TWO different data sources** because they contain complementary information:

| Data Source | Access Method | Contains | Update Frequency |
|-------------|---------------|----------|------------------|
| **Polaris Database** | Direct MSSQL | Notification logs, patron/item details, holds, overdues | Real-time |
| **Shoutbomb Reports** | FTP (text/email files) | SMS/Voice delivery details, keyword usage, opt-outs, invalid phones | Daily/Weekly/Monthly |

### What Each Source Provides

#### Polaris MSSQL Database (Direct Connection)
**Tables:**
- `PolarisTransactions.Polaris.NotificationLog` - Every notification sent from Polaris
- `Results.Polaris.NotificationHistory` - Item-level notification details
- `Results.Polaris.NotificationQueue` - Pending notifications
- `Polaris.Polaris.PatronRegistration` - Patron details (if needed)
- `Polaris.Polaris.SysHoldRequests` - Hold details

**What You Get:**
- ✅ All notification types (Email, SMS, Voice, Mail)
- ✅ Notification status (sent, failed, etc.)
- ✅ Counts (overdues, holds, fines)
- ✅ Real-time data
- ✅ Historical data (unlimited lookback)

**What You DON'T Get:**
- ❌ Shoutbomb-specific delivery details (actual SMS/Voice delivery status)
- ❌ Patron interactions (keyword responses like RHL, RA)
- ❌ Registration statistics
- ❌ Invalid phone number tracking
- ❌ Opt-out tracking

#### Shoutbomb FTP Reports (File Ingestion)
**Files:**
- Monthly Report (automatic)
- Weekly Report (manual request)
- Daily Invalid Phone Numbers (6:00 AM)
- Daily Undelivered Voice (4:10 PM)

**What You Get:**
- ✅ SMS/Voice delivery confirmation (actually delivered vs sent)
- ✅ Keyword usage statistics (RHL used 62 times, etc.)
- ✅ Registration stats (13,307 text, 5,199 voice subscribers)
- ✅ Opt-out tracking
- ✅ Invalid phone numbers
- ✅ Failed delivery details specific to SMS/Voice carrier issues

**What You DON'T Get:**
- ❌ Email notification details
- ❌ Mail notification details
- ❌ Item-level details (which book, etc.)
- ❌ Real-time data (delayed by report schedule)

---

## Recommended Architecture

### Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    POLARIS ILS SERVER                        │
│                                                              │
│  ┌──────────────────┐    ┌──────────────────┐              │
│  │ Polaris.Polaris  │    │ Results.Polaris  │              │
│  │ (Patrons, Items) │    │ (Notifications)  │              │
│  └──────────────────┘    └──────────────────┘              │
│           │                       │                          │
│           └───────────┬───────────┘                          │
│                       │ Direct MSSQL (sqlsrv)                │
└───────────────────────┼──────────────────────────────────────┘
                        │
                        ▼
         ┌──────────────────────────────┐
         │   LARAVEL WEB APPLICATION    │
         │                              │
         │  ┌────────────────────────┐  │
         │  │  MSSQL Import Service  │  │
         │  │  (Real-time queries)   │  │
         │  └────────────────────────┘  │
         │             │                 │
         │             ▼                 │
         │  ┌────────────────────────┐  │
         │  │   Local MySQL Database │  │
         │  │  (Cached/Aggregated)   │  │
         │  └────────────────────────┘  │
         │             ▲                 │
         │             │                 │
         │  ┌────────────────────────┐  │
         │  │  File Import Service   │  │
         │  │  (Parse Shoutbomb)     │  │
         │  └────────────────────────┘  │
         │             │                 │
         └─────────────┼─────────────────┘
                       │
                       │ FTP/SFTP/Network Share
                       │
         ┌─────────────┴─────────────┐
         │   SHOUTBOMB REPORTS       │
         │   (Text Files)            │
         │                           │
         │  - Monthly Report         │
         │  - Weekly Report          │
         │  - Daily Invalid Phones   │
         │  - Daily Undelivered      │
         └───────────────────────────┘
```

---

## Database Schema Design

### Local MySQL/MariaDB Database

This is YOUR database (not Polaris) where you'll store processed/aggregated data.

#### Core Tables

```sql
-- Main notification tracking (imported from Polaris)
CREATE TABLE notification_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    polaris_log_id INT UNIQUE,              -- Original NotificationLogID from Polaris
    patron_id INT NOT NULL,
    patron_barcode VARCHAR(20),
    notification_date DATETIME NOT NULL,
    notification_type_id INT NOT NULL,      -- 1=Overdue, 2=Hold, 7=AlmostOverdue, etc.
    delivery_option_id INT NOT NULL,        -- 1=Mail, 2=Email, 3=Voice, 8=SMS
    notification_status_id INT NOT NULL,    -- 12=Success, 14=Failed, etc.
    delivery_string VARCHAR(255),           -- Email/phone where sent
    holds_count INT DEFAULT 0,
    overdues_count INT DEFAULT 0,
    cancels_count INT DEFAULT 0,
    bills_count INT DEFAULT 0,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_date (notification_date),
    INDEX idx_patron (patron_id),
    INDEX idx_type (notification_type_id),
    INDEX idx_delivery (delivery_option_id),
    INDEX idx_status (notification_status_id)
) ENGINE=InnoDB;

-- Shoutbomb delivery tracking (imported from FTP reports)
CREATE TABLE shoutbomb_deliveries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    phone_number VARCHAR(20),
    patron_barcode VARCHAR(20),
    notification_type VARCHAR(50),          -- 'hold_text', 'renewal_text', 'overdue_voice', etc.
    delivery_status VARCHAR(50),            -- 'delivered', 'failed', 'opted_out', 'invalid_phone'
    carrier VARCHAR(100),
    failure_reason TEXT,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_report_date (report_date),
    INDEX idx_phone (phone_number),
    INDEX idx_barcode (patron_barcode),
    INDEX idx_status (delivery_status)
) ENGINE=InnoDB;

-- Keyword interactions (from Shoutbomb reports)
CREATE TABLE shoutbomb_keyword_usage (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    keyword VARCHAR(20) NOT NULL,           -- 'RHL', 'RA', 'OI', 'MYBOOK', etc.
    usage_count INT NOT NULL,
    report_type VARCHAR(20),                -- 'monthly', 'weekly'
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_report_date (report_date),
    INDEX idx_keyword (keyword)
) ENGINE=InnoDB;

-- Registration statistics (from Shoutbomb reports)
CREATE TABLE shoutbomb_registrations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    total_text_subscribers INT,
    total_voice_subscribers INT,
    new_text_registrations INT,
    new_voice_registrations INT,
    cancellations INT DEFAULT 0,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_report_date (report_date)
) ENGINE=InnoDB;

-- Daily summary (aggregated for fast dashboard queries)
CREATE TABLE daily_notification_summary (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    summary_date DATE NOT NULL,
    notification_type_id INT NOT NULL,
    delivery_option_id INT NOT NULL,
    total_sent INT DEFAULT 0,
    total_success INT DEFAULT 0,
    total_failed INT DEFAULT 0,

    UNIQUE KEY unique_daily_summary (summary_date, notification_type_id, delivery_option_id),
    INDEX idx_date (summary_date)
) ENGINE=InnoDB;
```

#### Reference Tables (Small, Static)

```sql
-- Notification types (can be seeded from Polaris or hardcoded)
CREATE TABLE notification_types (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO notification_types VALUES
(1, '1st Overdue', 'First overdue notice'),
(2, 'Hold Ready', 'Item available for pickup'),
(7, 'Almost Overdue', 'Auto-renew reminder (3 days before due)'),
(8, 'Fine Notice', 'Outstanding fines notification'),
(12, '2nd Overdue', 'Second overdue notice'),
(13, '3rd Overdue', 'Final overdue notice');

-- Delivery options
CREATE TABLE delivery_options (
    id INT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO delivery_options VALUES
(1, 'Mail', 'Physical mail'),
(2, 'Email', 'Email delivery'),
(3, 'Voice', 'Phone call (Shoutbomb)'),
(8, 'SMS', 'Text message (Shoutbomb)');

-- Notification statuses
CREATE TABLE notification_statuses (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_success BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB;

INSERT INTO notification_statuses VALUES
(12, 'Email Success', TRUE),
(14, 'Email Failed', FALSE),
(1, 'Voice Call Answered', TRUE),
(2, 'Voice Call Voicemail', TRUE),
(16, 'SMS Sent', TRUE);
-- (Add more as discovered)
```

---

## Laravel Package Structure

```
packages/dcplibrary/notices/
├── config/
│   └── notifications.php           # Configuration file
│
├── database/
│   ├── migrations/
│   │   ├── 2025_11_07_000001_create_notification_logs_table.php
│   │   ├── 2025_11_07_000002_create_shoutbomb_deliveries_table.php
│   │   ├── 2025_11_07_000003_create_shoutbomb_keyword_usage_table.php
│   │   ├── 2025_11_07_000004_create_shoutbomb_registrations_table.php
│   │   ├── 2025_11_07_000005_create_daily_notification_summary_table.php
│   │   ├── 2025_11_07_000006_create_notification_types_table.php
│   │   ├── 2025_11_07_000007_create_delivery_options_table.php
│   │   └── 2025_11_07_000008_create_notification_statuses_table.php
│   └── seeders/
│       └── NotificationReferenceDataSeeder.php
│
├── src/
│   ├── Commands/
│   │   ├── ImportNotifications.php    # Import from MSSQL
│   │   └── ImportShoutbombReports.php        # Import from FTP files
│   │
│   ├── Models/
│   │   ├── NotificationLog.php               # Local database
│   │   ├── ShoutbombDelivery.php
│   │   ├── ShoutbombKeywordUsage.php
│   │   ├── ShoutbombRegistration.php
│   │   ├── DailyNotificationSummary.php
│   │   ├── NotificationType.php
│   │   ├── DeliveryOption.php
│   │   ├── NotificationStatus.php
│   │   └── Polaris/
│   │       ├── PolarisNotificationLog.php    # Polaris MSSQL (read-only)
│   │       └── PolarisNotificationHistory.php
│   │
│   ├── Services/
│   │   ├── PolarisImportService.php          # Handles MSSQL queries
│   │   ├── ShoutbombFileParser.php           # Parses text reports
│   │   ├── NotificationAggregator.php        # Builds daily summaries
│   │   └── DashboardStatsService.php         # Prepares dashboard data
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   ├── NotificationLogController.php
│   │   │   ├── ShoutbombController.php
│   │   │   └── ReportsController.php
│   │   └── Middleware/
│   │       └── RequireEntraAuth.php
│   │
│   └── NotificationsServiceProvider.php
│
├── routes/
│   └── web.php
│
├── resources/
│   └── views/
│       ├── dashboard.blade.php
│       ├── notifications/
│       │   ├── index.blade.php
│       │   └── show.blade.php
│       ├── shoutbomb/
│       │   ├── keywords.blade.php
│       │   └── registrations.blade.php
│       └── reports/
│           ├── daily.blade.php
│           └── monthly.blade.php
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
└── composer.json
```

---

## Import Strategy

### Daily Scheduled Tasks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Import Polaris notifications every hour
    $schedule->command('notices:import')
        ->hourly()
        ->runInBackground();

    // Import Shoutbomb files daily at 9 AM
    $schedule->command('notices:import-shoutbomb')
        ->dailyAt('09:00')
        ->runInBackground();

    // Aggregate daily summaries at 11 PM
    $schedule->command('notices:aggregate-notifications')
        ->dailyAt('23:00')
        ->runInBackground();
}
```

### Polaris MSSQL Import (Artisan Command)

```php
// src/Commands/ImportNotifications.php
<?php

namespace Dcplibrary\Notices\Commands;

use Illuminate\Console\Command;
use Dcplibrary\Notices\Services\PolarisImportService;

class ImportNotifications extends Command
{
    protected $signature = 'notices:import
                            {--days=1 : Number of days to import}
                            {--full : Full historical import}';

    protected $description = 'Import notifications from Polaris MSSQL database';

    public function handle(PolarisImportService $importer)
    {
        $this->info('Starting Polaris notification import...');

        $days = $this->option('full') ? null : $this->option('days');

        $result = $importer->importNotifications($days);

        $this->info("Imported {$result['count']} notifications");
        $this->table(
            ['Type', 'Count'],
            $result['breakdown']
        );

        return Command::SUCCESS;
    }
}
```

### Shoutbomb FTP Import (Artisan Command)

```php
// src/Commands/ImportShoutbombReports.php
<?php

namespace Dcplibrary\Notices\Commands;

use Illuminate\Console\Command;
use Dcplibrary\Notices\Services\ShoutbombFileParser;

class ImportShoutbombReports extends Command
{
    protected $signature = 'notices:import-shoutbomb
                            {--path= : Path to report files}';

    protected $description = 'Import Shoutbomb reports from FTP directory';

    public function handle(ShoutbombFileParser $parser)
    {
        $this->info('Starting Shoutbomb report import...');

        $path = $this->option('path') ?? config('notices.shoutbomb_path');

        // Find all unprocessed report files
        $files = glob($path . '/*.txt');

        $this->info("Found " . count($files) . " report files");

        foreach ($files as $file) {
            $this->info("Processing: " . basename($file));

            $result = $parser->parseReport($file);

            $this->line("  Imported {$result['deliveries']} deliveries");
            $this->line("  Imported {$result['keywords']} keyword interactions");
            $this->line("  Imported {$result['registrations']} registration stats");

            // Move processed file to archive
            rename($file, $path . '/processed/' . basename($file));
        }

        return Command::SUCCESS;
    }
}
```

---

## Configuration

```php
// config/notices.php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Polaris Database Connection
    |--------------------------------------------------------------------------
    */
    'polaris_connection' => env('POLARIS_DB_CONNECTION', 'polaris'),

    /*
    |--------------------------------------------------------------------------
    | Shoutbomb FTP Settings
    |--------------------------------------------------------------------------
    */
    'shoutbomb_path' => env('SHOUTBOMB_FTP_PATH', storage_path('app/shoutbomb')),
    'shoutbomb_archive_days' => env('SHOUTBOMB_ARCHIVE_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    */
    'import_batch_size' => env('POLARIS_IMPORT_BATCH_SIZE', 1000),
    'import_days_default' => env('POLARIS_IMPORT_DAYS', 1),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    */
    'dashboard_date_range_default' => env('DASHBOARD_DATE_RANGE', 30),
];
```

```env
# .env additions
POLARIS_DB_CONNECTION=polaris
POLARIS_DB_HOST=polaris-server.local
POLARIS_DB_PORT=1433
POLARIS_DB_DATABASE=Polaris
POLARIS_DB_USERNAME=readonly_user
POLARIS_DB_PASSWORD=secure_password

SHOUTBOMB_FTP_PATH=/mnt/shoutbomb/reports
SHOUTBOMB_ARCHIVE_DAYS=90
```

---

## Advantages of Hybrid Approach

### ✅ Benefits

1. **Best of Both Worlds**
   - Real-time Polaris data via MSSQL
   - Shoutbomb details via file import

2. **Reduced Database Load**
   - Cache Polaris data locally (don't query every page load)
   - FTP files already exported, no Shoutbomb API needed

3. **Complete Picture**
   - Polaris: What was SENT
   - Shoutbomb: What was DELIVERED and patron interactions

4. **Flexibility**
   - Can adjust import frequency independently
   - Can add more data sources later

5. **Resilience**
   - If Polaris DB is slow, use cached local data
   - If FTP fails, Polaris data still updates

### ⚠️ Considerations

1. **Data Reconciliation**
   - Polaris log might say "SMS sent"
   - Shoutbomb report might show "delivery failed"
   - Need to merge these intelligently

2. **Timing Delays**
   - Polaris data: Real-time
   - Shoutbomb reports: Daily/weekly/monthly
   - Dashboard shows "as of last import"

3. **Storage**
   - Storing data in two places (Polaris MSSQL + Your MySQL)
   - But much smaller subset (only notifications, not entire Polaris DB)

---

## Next Steps

1. ✅ Confirm hybrid approach
2. ⏳ Create Laravel package skeleton
3. ⏳ Set up database connections
4. ⏳ Build MSSQL import service
5. ⏳ Build Shoutbomb file parser
6. ⏳ Create basic dashboard

**Ready to proceed?**
