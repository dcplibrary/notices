# SHOUTBOMB & POLARIS INTEGRATION DOCUMENTATION INDEX

**Last Updated:** November 14, 2025  
**System Owner:** Brian Lashbrook, Daviess County Public Library  
**Purpose:** Central index for all ShoutBomb automated patron notification system documentation

---

## TABLE OF CONTENTS

- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Documentation Structure](#documentation-structure)
- [Quick Reference Guide](#quick-reference-guide)
- [Data Flow Diagram](#data-flow-diagram)
- [Troubleshooting Index](#troubleshooting-index)
- [Future App Integration](#future-app-integration)
- [API Integration Resources](#api-integration-resources)

---

## OVERVIEW

### What is ShoutBomb?

ShoutBomb is a third-party automated notification service that delivers hold, overdue, and renewal reminders to library patrons via voice calls and text messages. The system integrates with the Polaris ILS through daily FTP file exchanges and provides delivery confirmation reports.

### System Components

1. **Polaris ILS** - Library management system (source of truth)
2. **ShoutBomb Service** - External notification delivery platform
3. **FTP Exchange** - File transfer mechanism
4. **Windows Scheduled Tasks** - Automation
5. **Email Reports** - Delivery confirmations and failure reports
6. **Polaris API** (future) - Two-way integration for delivery confirmations

### Key Workflows

- **Overnight Processing** (1:30am - 4:00am): Conflict resolution and patron list exports
- **Morning Exports** (8:00am - 9:00am): Hold and overdue notifications
- **Afternoon Exports** (1:00pm - 5:00pm): Additional hold notifications
- **Incoming Reports** (Daily): Failure reports and delivery statistics from ShoutBomb

---

## SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────┐
│                        POLARIS ILS                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Patrons     │  │ Circulation  │  │ Notification │          │
│  │  Database    │  │   Records    │  │    Queue     │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                   │
│         └──────────────────┴──────────────────┘                   │
│                            │                                       │
│                            ▼                                       │
│              ┌─────────────────────────┐                          │
│              │   SQL Export Scripts    │                          │
│              │  (Custom & Standard)    │                          │
│              └────────────┬────────────┘                          │
└───────────────────────────┼────────────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────┐
              │   Local File System     │
              │  C:\shoutbomb\ftp\*     │
              └────────────┬────────────┘
                            │
                            ▼
              ┌─────────────────────────┐
              │   WinSCP FTP Upload     │
              │  (Automated Transfer)   │
              └────────────┬────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SHOUTBOMB SERVICE                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   Patron     │  │   Voice      │  │    Text      │          │
│  │  Processing  │  │  Dialing     │  │   Messaging  │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                   │
│         └──────────────────┴──────────────────┘                   │
│                            │                                       │
│                            ▼                                       │
│              ┌─────────────────────────┐                          │
│              │   Delivery Attempts     │                          │
│              └────────────┬────────────┘                          │
└───────────────────────────┼────────────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────┐
              │   Email Reports to      │
              │   Library Staff         │
              └─────────────────────────┘
```

---

## DOCUMENTATION STRUCTURE

### Core Data Source Documentation

#### Exports TO ShoutBomb (Outgoing)

| Document | Description | Frequency | File Location |
|----------|-------------|-----------|---------------|
| [SHOUTBOMB_VOICE_PATRONS.md](SHOUTBOMB_VOICE_PATRONS.md) | Patrons opted for voice notifications | Daily 4:00am | `/voice_patrons/voice_patrons.txt` |
| [SHOUTBOMB_TEXT_PATRONS.md](SHOUTBOMB_TEXT_PATRONS.md) | Patrons opted for text notifications | Daily 4:00am | `/text_patrons/text_patrons.txt` |
| [SHOUTBOMB_HOLDS_EXPORT.md](SHOUTBOMB_HOLDS_EXPORT.md) | Hold ready for pickup notifications | 4x daily | `/holds/holds.txt` |
| [SHOUTBOMB_OVERDUE_EXPORT.md](SHOUTBOMB_OVERDUE_EXPORT.md) | Overdue item notifications | Daily 8:04am | `/overdue/overdue.txt` |
| [SHOUTBOMB_RENEW_EXPORT.md](SHOUTBOMB_RENEW_EXPORT.md) | Renewal reminder notifications | Daily 8:06am | `/renew/renew.txt` |

#### Reports FROM ShoutBomb (Incoming)

| Document | Description | Frequency | Delivery Method |
|----------|-------------|-----------|-----------------|
| [SHOUTBOMB_REPORTS_INCOMING.md](SHOUTBOMB_REPORTS_INCOMING.md) | Failure reports and statistics | Daily + Monthly | Email |

#### Polaris Standard Exports

| Document | Description | Frequency | File Location |
|----------|-------------|-----------|---------------|
| [POLARIS_PHONE_NOTICES.md](POLARIS_PHONE_NOTICES.md) | Native Polaris phone notification export (25 fields) | Daily | Standard Polaris export |

### API Integration Documentation

| Document | Description | Purpose |
|----------|-------------|---------|
| [Polaris_Notification_Guide_PAPIClient.md](Polaris_Notification_Guide_PAPIClient.md) | Laravel package for Polaris API integration | Future: Delivery confirmations |
| [Polaris-API-swagger.json](Polaris-API-swagger.json) | Complete Polaris API specification | API endpoint reference |

### Templates & Reference

| Document | Description | Purpose |
|----------|-------------|---------|
| [DATA_SOURCE_TEMPLATE.md](DATA_SOURCE_TEMPLATE.md) | Template for documenting new data sources | Standardization |

---

## QUICK REFERENCE GUIDE

### File Naming Patterns

| Export Type | File Pattern | Example |
|-------------|-------------|----------|
| Voice Patrons | `voice_patrons.txt` | `voice_patrons_submitted_20251114_040001.txt` |
| Text Patrons | `text_patrons.txt` | `text_patrons_submitted_20251114_040002.txt` |
| Holds | `holds.txt` | `holds_submitted_20251114_080001.txt` |
| Overdue | `overdue.txt` | `overdue_submitted_20251114_080401.txt` |
| Renew | `renew.txt` | `renew_submitted_20251114_080601.txt` |

### Field Delimiters

| Export Type | Delimiter | Header Row? | Example |
|-------------|-----------|-------------|---------|
| Voice Patrons | Pipe (`\|`) | No | `5551234567\|23307000001234` |
| Text Patrons | Pipe (`\|`) | No | `5551234567\|23307000001234` |
| Holds | Pipe (`\|`) | No | `23307000001234\|...` |
| Overdue | Pipe (`\|`) | No | `100001\|33307000012345\|...` |
| Renew | Pipe (`\|`) | No | `23307000001234\|...` |
| Phone Notices | Comma (`,`) | Yes | `"V","eng","2",...` |

### Critical Phone Number Rules

**MUST NEVER OVERLAP:**
- A phone number can appear multiple times on voice_patrons.txt (same phone, multiple patrons)
- A phone number can appear multiple times on text_patrons.txt (same phone, multiple patrons)
- **A phone number CANNOT appear on BOTH lists** (conflict resolution prevents this)

### Notification Type Mappings

| Polaris NotificationTypeID | Description | Used by ShoutBomb? | Export File |
|---------------------------|-------------|-------------------|-------------|
| 2 | Hold ready for pickup | Yes | holds.txt |
| 1 | 1st Overdue | Yes | overdue.txt |
| 7 | Almost overdue/Renewal reminder | Yes | renew.txt |
| 8 | Fine | Yes | overdue.txt |
| 11 | Bill | Yes | overdue.txt |
| 12 | 2nd Overdue | Yes | overdue.txt |
| 13 | 3rd Overdue | Yes | overdue.txt |

### Delivery Option IDs

| DeliveryOptionID | Description | Used by ShoutBomb? |
|------------------|-------------|-------------------|
| 1 | Mailing Address | No |
| 2 | Email Address | No |
| 3 | Phone 1 (Voice) | Yes |
| 4 | Phone 2 (Voice) | No |
| 5 | Phone 3 (Voice) | No |
| 6 | FAX | No |
| 7 | EDI | No |
| 8 | TXT Messaging | Yes |

---

## DATA FLOW DIAGRAM

### Daily Processing Timeline

```
1:30am - Conflict Resolution Script #1
         └─> Resolves phone number conflicts between voice/text preferences

1:45am - Conflict Resolution Script #2
         └─> Final conflict check and patron preference sync

4:00am - Patron List Exports
         ├─> voice_patrons.txt (DeliveryOptionID = 3)
         └─> text_patrons.txt (DeliveryOptionID = 8)

8:00am - Hold Notification Export #1
         └─> holds.txt (First morning batch)

8:04am - Overdue Notification Export
         └─> overdue.txt (Types 1, 7, 8, 11, 12, 13)

8:04-8:05am - Polaris PhoneNotices Export
         └─> PhoneNotices.csv uploaded to local archive FTP (validation/supplemental data)

8:06am - Renewal Reminder Export
         └─> renew.txt (Items due in 3 days)

9:00am - Hold Notification Export #2
         └─> holds.txt (Second morning batch)

1:00pm - Hold Notification Export #3
         └─> holds.txt (Afternoon batch)

5:00pm - Hold Notification Export #4
         └─> holds.txt (Evening batch)

6:01am (Next Day) - Invalid Phone Report Email
                    └─> Lists opt-outs and invalid numbers

4:10pm (Same Day) - Voice Failure Report Email
                    └─> Lists failed voice deliveries
```

### File Upload Process

```
1. SQL Export → Local file system (C:\shoutbomb\ftp\*)
2. WinSCP Upload → ShoutBomb FTP server
3. On Success → Move to logs (C:\shoutbomb\logs\*_submitted_*.txt)
4. Archive Copy → Local FTP server (backup)
5. WinSCP Log → Activity log (C:\shoutbomb\logs\*.log)
```

### Archive and Backup System

**Purpose:** The local FTP server (${LOCAL_FTP_HOST}) serves as a central archive for both ShoutBomb submission files and Polaris phone notification exports, enabling backup, auditing, and potential app integration.

**Two Upload Sources:**

**1. ShoutBomb Export Archives**
- After successful ShoutBomb submission, files are moved to `C:\shoutbomb\logs\` with timestamped filenames
- Windows Task Scheduler runs `shoutbomb_logs_to_local_ftp.bat` to upload dated copies
- Files uploaded to FTP server root directory

**2. Polaris PhoneNotices Export**
- Polaris automated export process uploads `PhoneNotices.csv` daily
- Timing: Approximately 8:04-8:05 AM (4-5 minutes after 8:00 AM)
- Uploaded directly to FTP root directory by Polaris
- Used for validation and supplemental data (NOT sent to ShoutBomb)

**Local FTP Server Details:**
- **Server:** ${LOCAL_FTP_HOST} (internal network - from environment)
- **Protocol:** FTP
- **Upload Location:** Root directory `/`
- **Access:** Available for download by internal applications

**File Naming Patterns:**

*ShoutBomb Archives (timestamped):*
```
voice_patrons_submitted_2025-11-14_040001.txt
text_patrons_submitted_2025-11-14_040002.txt
holds_submitted_2025-11-14_080001.txt
overdue_submitted_2025-11-14_080401.txt
renew_submitted_2025-11-14_080601.txt
```

*Polaris Export (overwrites daily):*
```
PhoneNotices.csv
```

**Use Cases:**
- **Backup:** Historical record of all submitted files
- **Auditing:** Verify what was sent to ShoutBomb on specific dates
- **Validation:** Cross-reference ShoutBomb exports with Polaris PhoneNotices
- **App Integration:** Potential future app can download files for analysis
- **Troubleshooting:** Compare submitted files with ShoutBomb reports and Polaris data

**Retention:** Files accumulate on FTP server (manual cleanup as needed)

**Scripts:**
- **ShoutBomb Upload:** `shoutbomb_logs_to_local_ftp.bat`
- **Task Scheduler:** `Upload_Shoutbomb_logs_to_DS-MGMT_FTP_server.xml`
- **Log File:** `C:\shoutbomb\logs\notice_submissions.log`

---

## TROUBLESHOOTING INDEX

### Common Issues & Solutions

| Issue | Possible Cause | Solution Document | Section |
|-------|----------------|-------------------|---------|
| Phone appears on both voice and text lists | Conflict resolution failed | SHOUTBOMB_VOICE_PATRONS.md | Conflict Resolution System |
| 3rd overdue not triggering billing | No delivery confirmation | SHOUTBOMB_OVERDUE_EXPORT.md | Third Overdue Confirmation Gap |
| Missing holds in export | Hold status changed | SHOUTBOMB_HOLDS_EXPORT.md | Known Quirks |
| Duplicate notifications | Multiple patron accounts with same phone | SHOUTBOMB_VOICE_PATRONS.md | Duplicate Phone Numbers |
| Invalid phone numbers | Patron data entry errors | SHOUTBOMB_REPORTS_INCOMING.md | Daily Invalid Phone Report |
| Voice delivery failures | Disconnected/blocked numbers | SHOUTBOMB_REPORTS_INCOMING.md | Daily Voice Failure Report |

### Validation Queries

All documentation includes SQL validation queries in their respective "VALIDATION QUERIES" sections.

### Contact Information

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org)  
**Library:** Daviess County Public Library  
**Vendor:** ShoutBomb support  
**ILS:** Innovative Interfaces - Polaris

---

## FUTURE APP INTEGRATION

### Integration Point: Local FTP Server

**The local FTP server (${LOCAL_FTP_HOST}) serves as the integration boundary for future notification monitoring applications.**

**What the App Needs to Know:**
- **FTP Server:** `${LOCAL_FTP_HOST}` (configured via environment variable)
- **Credentials:** `${LOCAL_FTP_USER}` and `${LOCAL_FTP_PASSWORD}` (from environment/config)
- **Protocol:** FTP
- **Download Location:** Root directory `/`
- **Available Files:**
  - `PhoneNotices.csv` - Polaris native export (updated daily ~8:04 AM)
  - `*_submitted_YYYY-MM-DD_HHMMSS.txt` - ShoutBomb submission archives (timestamped)
- **Configuration:** See [Configuration Requirements](#configuration-requirements) section below

**What the App Does NOT Need to Know:**
- How files are generated (SQL queries, batch scripts, etc.)
- How files get uploaded to ShoutBomb FTP
- Windows Task Scheduler details
- Polaris internal export mechanisms
- Actual FTP server hostname/credentials (use environment variables)
- **The app simply loads config and downloads files from the FTP server**

### File Types and Purposes

#### 1. ShoutBomb Submission Archives (Timestamped)

**Purpose:** Historical record of exactly what was submitted to ShoutBomb for delivery

**Files Available:**
```
voice_patrons_submitted_YYYY-MM-DD_HHMMSS.txt    # Daily ~4:00 AM
text_patrons_submitted_YYYY-MM-DD_HHMMSS.txt     # Daily ~4:00 AM
holds_submitted_YYYY-MM-DD_HHMMSS.txt            # 4x daily (8am, 9am, 1pm, 5pm)
overdue_submitted_YYYY-MM-DD_HHMMSS.txt          # Daily ~8:04 AM
renew_submitted_YYYY-MM-DD_HHMMSS.txt            # Daily ~8:06 AM
```

**File Format:** Pipe-delimited (`|`) text files, no header row

**App Use Cases:**
- Track what notifications were actually sent to ShoutBomb
- Compare against ShoutBomb delivery reports (from emails)
- Audit trail for notification delivery
- Historical analysis of notification patterns

**Key Detail:** These files contain the custom SQL extracts that were sent to ShoutBomb for actual notification delivery.

#### 2. PhoneNotices.csv (Polaris Native Export)

**Purpose:** Validation and enrichment data - **NOT sent to ShoutBomb**

**File:** `PhoneNotices.csv` (overwrites daily, not timestamped)

**Updated:** Daily at approximately 8:04-8:05 AM

**File Format:** Comma-delimited CSV with header row, 25 fields

**Critical Understanding:**
- **NOT sent to ShoutBomb** - This is Polaris' native export format
- **For validation only** - Used to verify ShoutBomb exports are correct
- **Supplemental data** - Contains additional fields not in ShoutBomb exports

**App Use Cases:**

**1. Validation - Verify ShoutBomb Has Correct Notices:**
```
Compare:
- ShoutBomb holds_submitted.txt PatronBarcodes
- PhoneNotices.csv patron_barcode where notification_type_id = 2
→ Flag any mismatches (notifications that should have been sent but weren't)
```

**2. Validation - Verify Correct Delivery Method:**
```
Compare:
- ShoutBomb voice_patrons.txt phone numbers
- PhoneNotices.csv delivery_method = 'V' (voice) phone numbers
→ Ensure delivery method preferences are correctly applied
```

**3. Validation - Verify Correct Information:**
```
Cross-reference:
- Item barcodes match
- Due dates match
- Patron IDs match
→ Ensure data integrity across systems
```

**4. Enrichment - Add Supplemental Data:**
```
PhoneNotices.csv provides additional fields NOT in ShoutBomb exports:
- Patron names (NameFirst, NameLast)
- Email addresses
- Site/branch information
- Language preferences
- Notification level (1st/2nd/3rd overdue)
- Pickup area descriptions (for holds)
- Account balances (for fines/bills)

→ Use this to create a fuller picture of each notification
```

### App Architecture Recommendations

**Download Strategy:**
1. **Daily Download:** Pull fresh PhoneNotices.csv each morning after 8:10 AM
2. **Continuous Monitoring:** Check for new `*_submitted_*.txt` files periodically
3. **Timestamp Tracking:** Track which submission files have been processed
4. **File Retention:** Keep processed files for audit trail

**Processing Pipeline:**
```
1. Download Files from FTP
   ↓
2. Parse and Validate File Formats
   ↓
3. Store ShoutBomb Submissions in Database
   ↓
4. Store PhoneNotices Data in Database
   ↓
5. Cross-Reference and Validation
   ├─> Flag missing notifications
   ├─> Flag delivery method mismatches
   ├─> Flag data inconsistencies
   └─> Enrich notification records with supplemental data
   ↓
6. Generate Reports/Dashboard
   ├─> Delivery success rates
   ├─> Validation results
   ├─> Notification patterns
   └─> Exception handling queue
```

**Database Design Considerations:**
- **shoutbomb_submissions** table - Store timestamped submission files
- **polaris_phone_notices** table - Store PhoneNotices.csv daily snapshots
- **notification_validation** table - Store cross-reference results
- **notification_enriched** table - Merged view with supplemental data

**Key Validation Queries:**

**1. Missing Notifications:**
```sql
-- Find notifications in PhoneNotices but not submitted to ShoutBomb
SELECT pn.* 
FROM polaris_phone_notices pn
LEFT JOIN shoutbomb_submissions sb 
  ON pn.patron_barcode = sb.patron_barcode 
  AND pn.item_barcode = sb.item_barcode
  AND pn.notification_type_id = sb.notification_type
WHERE sb.id IS NULL
  AND pn.delivery_option_id IN (3, 8); -- Phone or text only
```

**2. Delivery Method Mismatches:**
```sql
-- Find patrons who should get voice but are in text list (or vice versa)
SELECT pn.patron_barcode, pn.delivery_method, pn.delivery_option_id
FROM polaris_phone_notices pn
WHERE (pn.delivery_method = 'V' AND pn.delivery_option_id != 3)
   OR (pn.delivery_method = 'T' AND pn.delivery_option_id != 8);
```

**3. Enriched Notification View:**
```sql
-- Create enriched view combining ShoutBomb + PhoneNotices data
SELECT 
  sb.patron_barcode,
  sb.item_barcode,
  sb.notification_type,
  sb.submitted_timestamp,
  pn.name_first,
  pn.name_last,
  pn.email_address,
  pn.browse_title,
  pn.due_date,
  pn.site_name,
  pn.notification_level,
  pn.account_balance
FROM shoutbomb_submissions sb
LEFT JOIN polaris_phone_notices pn
  ON sb.patron_barcode = pn.patron_barcode
  AND sb.item_barcode = pn.item_barcode;
```

### Configuration Requirements

**The app requires the following environment variables or configuration settings:**

| Variable | Description | Example |
|----------|-------------|---------|
| `LOCAL_FTP_HOST` | Local FTP server hostname or IP | `192.168.1.100` or `ftp.example.local` |
| `LOCAL_FTP_USER` | FTP username | `ftpuser` or `anonymous` |
| `LOCAL_FTP_PASSWORD` | FTP password | `secretpassword` |
| `LOCAL_FTP_PATH` | Path to files (usually root) | `/` |

**Configuration Methods:**

**Option 1: Environment Variables**
```bash
# Linux/Mac
export LOCAL_FTP_HOST="192.168.1.100"
export LOCAL_FTP_USER="ftpuser"
export LOCAL_FTP_PASSWORD="secretpassword"
export LOCAL_FTP_PATH="/"

# Windows
set LOCAL_FTP_HOST=192.168.1.100
set LOCAL_FTP_USER=ftpuser
set LOCAL_FTP_PASSWORD=secretpassword
set LOCAL_FTP_PATH=/
```

**Option 2: Configuration File (Recommended)**
```yaml
# config.yaml
ftp:
  host: "192.168.1.100"
  user: "ftpuser"
  password: "secretpassword"
  path: "/"
```

**Option 3: .env File**
```ini
# .env
LOCAL_FTP_HOST=192.168.1.100
LOCAL_FTP_USER=ftpuser
LOCAL_FTP_PASSWORD=secretpassword
LOCAL_FTP_PATH=/
```

**Security Note:** Never commit credentials to version control. Use environment variables or secure configuration management.

### FTP Access Pattern

**Connection Details:**
```python
# Example Python FTP access with environment variables
import ftplib
import os

# Load from environment variables
FTP_HOST = os.getenv('LOCAL_FTP_HOST')
FTP_USER = os.getenv('LOCAL_FTP_USER', 'anonymous')
FTP_PASSWORD = os.getenv('LOCAL_FTP_PASSWORD')

# Establish connection
ftp = ftplib.FTP(FTP_HOST)
ftp.login(FTP_USER, FTP_PASSWORD)
ftp.cwd('/')  # Navigate to root directory

# List available files
files = ftp.nlst()

# Download PhoneNotices.csv
with open('PhoneNotices.csv', 'wb') as f:
    ftp.retrbinary('RETR PhoneNotices.csv', f.write)

# Download all submitted files from today
import datetime
today = datetime.date.today().strftime('%Y-%m-%d')
for filename in files:
    if f'submitted_{today}' in filename:
        with open(filename, 'wb') as f:
            ftp.retrbinary(f'RETR {filename}', f.write)

ftp.quit()
```

**Alternative: Using python-dotenv**
```python
# Load from .env file
from dotenv import load_dotenv
import os
import ftplib

load_dotenv()  # Load .env file

ftp = ftplib.FTP(os.getenv('LOCAL_FTP_HOST'))
ftp.login(os.getenv('LOCAL_FTP_USER'), os.getenv('LOCAL_FTP_PASSWORD'))
# ... rest of code
```

### Success Metrics

**App should track and report:**
- **Validation Rate:** % of PhoneNotices notifications found in ShoutBomb submissions
- **Mismatch Rate:** % of delivery method conflicts detected
- **Enrichment Rate:** % of notifications with complete supplemental data
- **Processing Time:** Time to download, process, and validate daily files
- **Exception Count:** Number of flagged issues requiring manual review

### Integration Summary

**The app's role is simple:**
1. ✅ Download files from FTP server
2. ✅ Store ShoutBomb submissions (what was sent)
3. ✅ Store PhoneNotices data (what should have been sent)
4. ✅ Cross-reference to validate correctness
5. ✅ Enrich notifications with supplemental data
6. ✅ Report discrepancies and provide monitoring dashboard

**The app does NOT need to:**
- ❌ Know about SQL queries or database structure
- ❌ Interact with Polaris directly
- ❌ Submit notifications to ShoutBomb
- ❌ Manage file uploads or Windows Task Scheduler
- ❌ Understand batch script logic

**The FTP server is the clean integration boundary - everything the app needs is there.**

---

## API INTEGRATION RESOURCES

### Current State: File-Based Integration

All notification delivery is currently handled through FTP file exchanges. ShoutBomb processes the files and delivers notifications, but **there is no automated feedback loop** to confirm deliveries back to Polaris.

### Future Enhancement: Polaris API Integration

**Goal:** Implement two-way integration using Polaris API to:
1. Read notifications from NotificationQueue (already exported via files)
2. Confirm successful deliveries back to Polaris using NotificationUpdatePut endpoint
3. Solve the "3rd overdue confirmation gap" issue

**Key Resources:**
- Laravel Package: `blashbrook/papiclient` - Custom package for Polaris API integration
- API Documentation: See Polaris_Notification_Guide_PAPIClient.md
- API Specification: Polaris-API-swagger.json

**Critical Endpoint:**
```
PUT /REST/protected/v1/{LangID}/{AppID}/{OrgID}/{AccessToken}/notification/{NotificationTypeID}
```

**Use Case Example:**
```php
// After ShoutBomb confirms 3rd overdue delivery
$notificationService->markAsSent(
    notificationTypeId: 13, // 3rd Overdue
    data: [
        'NotificationStatusID' => 16, // Sent
        'PatronID' => $patronId,
        'PatronBarcode' => $patronBarcode,
        'DeliveryOptionID' => 8, // Text
        'DeliveryString' => $phoneNumber,
        'ItemRecordID' => $itemRecordId
    ]
);
```

### API Reference Tables

Complete lookup tables available in:
- **Polaris_Notification_Guide_PAPIClient.md** - Quick reference tables
- **POLARIS_PHONE_NOTICES.md** - Comprehensive reference tables with 44 languages, 22 notification types, 16 status codes

---

## DOCUMENT MAINTENANCE

### Version History

| Date | Document | Change Description |
|------|----------|-------------------|
| 2025-11-14 | POLARIS_PHONE_NOTICES.md | Complete field mapping correction (25 fields) |
| 2025-11-14 | All documentation | Added comprehensive lookup tables |
| 2025-11-13 | SHOUTBOMB_HOLDS_EXPORT.md | Fixed pipe character handling in titles |
| 2025-11-13 | SHOUTBOMB_RENEW_EXPORT.md | Documented Thursday edge case |

### Documentation Standards

When creating or updating documentation, follow the DATA_SOURCE_TEMPLATE.md structure:
1. Source Metadata
2. Field Definitions (exact order, with data types)
3. Sample Data (anonymized)
4. Cross-Reference Keys
5. Known Quirks
6. Source SQL Query (if applicable)
7. Validation Rules
8. Processing Notes
9. Change Log

### Review Schedule

- **Monthly:** Verify all documentation reflects current system state
- **After Changes:** Update documentation immediately when SQL queries or file formats change
- **Quarterly:** Review validation queries and known quirks for accuracy

---

## APPENDIX: FILE LOCATIONS

### Production System Paths

```
C:\shoutbomb\
├── shoutbomb.bat              # Master batch script
├── sql\
│   ├── voice_patrons.sql      # Voice patron list export
│   ├── text_patrons.sql       # Text patron list export
│   ├── holds.sql              # Hold notifications export
│   ├── overdue.sql            # Overdue notifications export
│   └── renew.sql              # Renewal reminder export
├── ftp\
│   ├── voice_patrons\         # Voice patron staging
│   ├── text_patrons\          # Text patron staging
│   ├── holds\                 # Hold notification staging
│   ├── overdue\               # Overdue notification staging
│   └── renew\                 # Renewal reminder staging
└── logs\
    ├── *_submitted_*.txt      # Archive of uploaded files
    └── *.log                  # WinSCP activity logs
```

### ShoutBomb FTP Structure

```
/voice_patrons/voice_patrons.txt
/text_patrons/text_patrons.txt
/holds/holds.txt
/overdue/overdue.txt
/renew/renew.txt
```

### Local Archive FTP Server (${LOCAL_FTP_HOST})

**Purpose:** Backup and app integration access

**Server Configuration:**
- **Hostname:** Set via `${LOCAL_FTP_HOST}` environment variable
- **Credentials:** Set via `${LOCAL_FTP_USER}` and `${LOCAL_FTP_PASSWORD}` environment variables
- **Protocol:** FTP
- **Path:** Root directory `/`
- **Network:** Internal network only

**For configuration details, see the [Configuration Requirements](#configuration-requirements) section.**

**Structure:**
```
/ (root directory)
├── PhoneNotices.csv                                    # Polaris daily export (~8:04 AM, overwrites)
├── voice_patrons_submitted_2025-11-14_040001.txt     # ShoutBomb archive
├── text_patrons_submitted_2025-11-14_040002.txt      # ShoutBomb archive
├── holds_submitted_2025-11-14_080001.txt             # ShoutBomb archive
├── holds_submitted_2025-11-14_090001.txt             # ShoutBomb archive
├── holds_submitted_2025-11-14_130001.txt             # ShoutBomb archive
├── holds_submitted_2025-11-14_170001.txt             # ShoutBomb archive
├── overdue_submitted_2025-11-14_080401.txt           # ShoutBomb archive
├── renew_submitted_2025-11-14_080601.txt             # ShoutBomb archive
└── [historical dated ShoutBomb files...]
```

**Access:**
- **Protocol:** FTP
- **Authentication:** `${LOCAL_FTP_USER}` with `${LOCAL_FTP_PASSWORD}` (from environment variables)
- **Upload Sources:**
  - ShoutBomb archives: Automated via `shoutbomb_logs_to_local_ftp.bat` (Windows Task Scheduler)
  - PhoneNotices.csv: Automated via Polaris internal export process (daily ~8:04 AM)
- **Download:** Available to internal applications for retrieval
- **Retention:** 
  - PhoneNotices.csv: Overwrites daily (only current day available)
  - ShoutBomb archives: Accumulate (manual cleanup as needed)

**File Naming Pattern:** `{export_type}_submitted_{YYYY-MM-DD}_{HHMMSS}.txt`

---

**END OF DOCUMENTATION INDEX**

*For detailed information on any specific component, refer to the individual documentation files listed above.*
