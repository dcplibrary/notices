# ShoutBomb Notification Process - Workflow Overview

## Process Summary

The ShoutBomb notification system is an automated workflow that sends voice and text notifications to library patrons about holds, overdues, and renewal reminders. The process runs multiple times daily with specific timing sequences to ensure timely delivery.

---

## Daily Timeline

### Overnight Processing (1:30am - 5:00am)
**Purpose:** Prepare patron delivery lists and resolve conflicts

1. **1:30am - Conflict Resolution Script 1**
   - Identifies patrons registered for both voice AND text
   - Resolves conflicts to prevent duplicate notifications
   - Ensures each phone number maps to only ONE delivery method

2. **1:45am - Conflict Resolution Script 2**
   - Final validation pass
   - Confirms no phone number overlaps between delivery methods

3. **4:00am - Voice Patrons Export**
   - File: `voice_patrons.txt`
   - Contains: Phone numbers + Barcodes for voice delivery (DeliveryOptionID = 3)
   - Note: Same phone can appear multiple times (family accounts)

4. **5:00am - Text Patrons Export**
   - File: `text_patrons.txt`
   - Contains: Phone numbers + Barcodes for text delivery (DeliveryOptionID = 8)
   - Note: Same phone can appear multiple times (family accounts)

### Morning Exports (8:00am - 9:00am)
**Purpose:** Export notifications for patron delivery

1. **8:00am - Hold Notifications Export #1**
   - File: `holds.txt`
   - Contains: Items ready for pickup

2. **8:03am - Renewal Reminders Export**
   - File: `renew.txt`
   - Sent 3 days before due date (Mon-Wed, Fri-Sun)
   - Sent 4 days before due date (Thursday only - accounts for Sunday)
   - Single notification per item

3. **8:04am - Overdue Notices Export**
   - File: `overdue.txt`
   - Contains ALL currently overdue items (daily snapshot)
   - Notification Types:
     - Type 7: Almost Overdue (Due-3 days, Due-1 day)
     - Type 1: 1st Overdue (Due+1 day)
     - Type 12: 2nd Overdue (Due+7 days)
     - Type 13: 3rd Overdue (Due+14 days)
     - Type 8: Fine notices
     - Type 11: Bill notices

4. **9:00am - Hold Notifications Export #2**
   - Same format as 8:00am run
   - Captures new holds processed overnight

### Afternoon Exports (1:00pm - 5:00pm)
**Purpose:** Additional hold notification updates

1. **1:00pm - Hold Notifications Export #3**
2. **5:00pm - Hold Notifications Export #4**

**Rationale:** Holds are exported 4x daily because items become available throughout the day as they're checked in and processed.

---

## FTP Upload Process

After each export:

1. **SQL Query Execution**
   - Queries Polaris database
   - Writes pipe-delimited text file to `C:\shoutbomb\ftp\[type]\[file].txt`

2. **WinSCP Upload**
   - Uploads to Shoutbomb FTP server
   - Destination: `/[type]/[file].txt`

3. **Archive on Success**
   - Moves file to: `C:\shoutbomb\logs\[type]_submitted_YYYY-MM-DD_HH-MM-SS.txt`
   - Preserves audit trail

4. **Backup Copy**
   - Separate scheduled task copies to local FTP server
   - Additional redundancy

---

## ShoutBomb Processing

Once files are uploaded to Shoutbomb:

### 1. Phone Number Matching
- Shoutbomb reads patron lists (voice_patrons.txt, text_patrons.txt)
- Builds phone-to-barcode mappings
- One phone can map to multiple barcodes (family accounts)

### 2. Notification Batching
- Groups multiple notifications for same patron
- Example: Patron with 3 overdue items receives 1 combined notification
- Reduces notification fatigue

### 3. Delivery Scheduling
- Shoutbomb manages delivery timing based on notification type
- Sends on appropriate schedule:
  - Holds: Immediately upon receipt
  - Renewals: 3-4 days before due
  - Overdues: Day 1, Day 7, Day 14 after due

### 4. Notification Delivery
- **Voice:** Automated phone calls with recorded message
- **Text:** SMS text messages

---

## Notification Type Details

### Hold Notifications
- **Frequency:** 4 times daily (8am, 9am, 1pm, 5pm)
- **Content:** Item ready for pickup at branch
- **Expiration:** HoldTillDate (typically 7-10 days)
- **Reminders:** Sent if not picked up

### Renewal Reminders
- **Frequency:** Once daily (8:03am)
- **Timing:** 3 days before due (4 days on Thursday)
- **Purpose:** Remind patrons to renew if desired
- **Eligibility:** Item must be renewable (Renewals < RenewalLimit)

### Overdue Notices
- **Frequency:** Once daily (8:04am)
- **Export contains:** ALL currently overdue items (not just new overdues)
- **Progression Timeline:**
  - **Due Date - 3 days:** Almost Overdue (Type 7)
  - **Due Date - 1 day:** Almost Overdue (Type 7)
  - **Due Date + 1 day:** 1st Overdue (Type 1)
  - **Due Date + 7 days:** 2nd Overdue (Type 12)
  - **Due Date + 14 days:** 3rd Overdue (Type 13)
  - **Due Date + 21 days:** Item declared LOST (should auto-bill)

---

## Feedback & Reporting

Shoutbomb sends email reports back to the library:

### 1. Daily Activity Report
- **Timing:** Sent each morning
- **Content:** Statistics on notifications sent previous day
- **Format:** Text email with counts by type

### 2. Text Delivery Failures
- **Timing:** As failures occur
- **Content:** Invalid/disconnected phone numbers
- **Format:** Patron barcode, phone, branch ID
- **Action Required:** Update patron records

### 3. Voice Delivery Failures
- **Timing:** As failures occur
- **Content:** Failed voice calls (3 attempts made)
- **Format:** Pipe-delimited with patron details
- **Action Required:** Update patron records or contact patron

### 4. Monthly Statistics Report
- **Timing:** 1st-2nd of each month
- **Content:** Comprehensive 15+ page report
- **Sections:**
  - Notification counts by type and branch
  - Registered patron counts
  - Daily call volumes
  - Opted-out patrons
  - Invalid phone numbers
  - Keyword usage frequency
  - New registrations/cancellations

---

## Critical Known Issue

### 3rd Overdue Confirmation Gap

**Problem:**
- Polaris requires confirmation that 3rd Overdue notice was delivered before auto-billing
- **Email/Mail notifications:** Polaris handles delivery, auto-confirms, triggers lost status
- **Text/Voice notifications:** Shoutbomb handles delivery but does NOT confirm back to Polaris

**Impact:**
- Text/voice overdue patrons must be manually billed
- Email/mail overdue patrons are billed automatically
- Inconsistent patron experience based on notification preference

**Timeline:**
- Day 14: 3rd Overdue sent via Shoutbomb
- Day 21: SHOULD trigger lost status + auto-billing
- Reality: Only email/mail auto-bill; text/voice require manual intervention

**Workaround:**
- Staff manually review 3rd overdue text/voice notifications
- Staff manually bill patrons for lost items

**Future Enhancement Needed:**
- Automated process to confirm 3rd overdue delivery to Polaris
- Possible solutions:
  - SQL update to Polaris after export
  - API call to mark notification as sent
  - Shoutbomb integration to report delivery status

---

## Key Design Principles

### 1. Conflict Resolution First
- Overnight scripts (1:30am, 1:45am) ensure no phone appears on both voice AND text lists
- Critical for proper notification routing

### 2. Daily Snapshots
- Overdue export contains ALL overdue items daily (not just new overdues)
- Shoutbomb manages notification schedule (Day 1, 7, 14)
- When item returned, disappears from export and notifications stop

### 3. Family Account Support
- Same phone number can appear multiple times with different barcodes
- Shoutbomb links one phone to multiple patron accounts
- Useful for parents managing children's accounts

### 4. Grace Period for Expired Accounts
- Includes patrons expired within last 3 months
- Allows recently expired patrons to receive notifications
- Gives time to renew before losing notification service

### 5. Archive Everything
- Every export archived with timestamp
- Provides audit trail for troubleshooting
- Can reconstruct notification history

---

## File Formats

All export files use:
- **Format:** Pipe-delimited text (`|`)
- **Header:** NO header row
- **Encoding:** Windows-1252/UTF-8
- **Line Endings:** Windows (CRLF)

### Patron Lists (voice_patrons.txt, text_patrons.txt)
```
[PhoneNumber]|[PatronBarcode]
5551234567|23307001234567
```

### Holds (holds.txt)
```
[Title]|[CreationDate]|[HoldRequestID]|[PatronID]|[BranchID]|[HoldTillDate]|[PatronBarcode]
```

### Renewals (renew.txt)
```
[PatronID]|[ItemBarcode]|[Title]|[DueDate]|[ItemRecordID]|[Dummy1]|[Dummy2]|[Dummy3]|[Dummy4]|[Renewals]|[BibRecordID]|[RenewalLimit]|[PatronBarcode]
```

### Overdues (overdue.txt)
```
[PatronID]|[ItemBarcode]|[Title]|[DueDate]|[ItemRecordID]|[Dummy1]|[Dummy2]|[Dummy3]|[Dummy4]|[Renewals]|[BibRecordID]|[RenewalLimit]|[PatronBarcode]
```

---

## Success Metrics

Monitor the process health by:

1. **File Generation**
   - All scheduled exports complete on time
   - Files contain expected data (not empty when should have records)

2. **Archive Integrity**
   - All submitted files archived with timestamps
   - No missing archives in logs directory

3. **Conflict Resolution**
   - Zero phone number overlap between voice and text lists
   - Conflict resolution scripts complete successfully

4. **Delivery Failures**
   - Low rate of invalid phone numbers
   - Failed deliveries addressed promptly

5. **Monthly Reconciliation**
   - Monthly report totals match sum of daily activities
   - Registered patron counts align with patron list exports

---

## Troubleshooting Guide

### Files Not Generated
- Check Windows Task Scheduler status
- Verify SQL Server connectivity
- Review batch script logs

### FTP Upload Failures
- Check WinSCP connection to Shoutbomb
- Verify FTP credentials
- Check network connectivity

### Phone Number Overlap Error
- Review conflict resolution script logs (1:30am, 1:45am)
- Identify patrons with DeliveryOptionID issues in Polaris
- Manually correct patron records

### High Delivery Failure Rate
- Review invalid phone number reports
- Bulk update patron records
- Consider patron outreach campaign

### Missing Monthly Report
- Contact Shoutbomb support
- Check email filters/spam folders
- Verify email address on file

---

## Related Systems

### Polaris ILS
- **Database:** DCPLPRO server, Polaris database
- **Tables Used:**
  - Polaris.Polaris.Patrons
  - Polaris.Polaris.ItemCheckouts
  - Polaris.Polaris.CircItemRecords
  - Polaris.Polaris.BibliographicRecords
  - Polaris.Polaris.SysHoldRequests
  - Results.Polaris.NotificationQueue
  - Results.Polaris.HoldNotices

### Windows Task Scheduler
- Manages all export timing
- Batch scripts in `C:\shoutbomb\`
- SQL scripts in `C:\shoutbomb\sql\`

### WinSCP
- Automated FTP uploads
- Script-driven transfer
- Archive on success

### Shoutbomb Platform
- Third-party notification service
- Manages delivery to patrons
- Provides reporting back to library
