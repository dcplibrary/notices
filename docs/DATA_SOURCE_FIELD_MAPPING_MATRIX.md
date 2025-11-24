# DATA SOURCE FIELD MAPPING MATRIX

**Purpose:** Master reference showing which database fields come from which source files and required transformations  
**System Owner:** Brian Lashbrook  
**Date:** November 22, 2025

---

## DATA SOURCES INCLUDED

### Export Files (from Local FTP Server)
1. **holds*.txt** - Hold notifications (4x daily: 8am, 9am, 1pm, 5pm)
2. **overdue*.txt** - Overdue/fine/bill notifications (daily 8:04am)
3. **renew*.txt** - Renewal reminders (daily 8:03am)
4. **voice_patrons*.txt** - Voice notification patron list (daily 4am)
5. **text_patrons*.txt** - Text notification patron list (daily 5am)
6. **PhoneNotices.csv** - Polaris native export (daily ~8:04am)

### Email Reports (from ShoutBomb via Outlook)
7. **"Invalid patron phone number [Date]"** - Daily invalid/opted-out phones (6am)
8. **"Voice notices that were not delivered on [Date]"** - Daily voice failures (4:10pm)
9. **"Shoutbomb Rpt [Month]"** - Monthly statistics
10. **"WEEKLY_REPORT+{MM-YYYY}"** - Weekly statistics (on-demand)

### Additional Sources (Not Yet Documented)
**❓ Did you mean any of these?**
- ~~"Calls that generated errors"~~ → Likely same as "Voice notices not delivered"
- SMS delivery failures? (Currently only in monthly aggregates)
- Patron-initiated keyword responses? (In monthly stats only)

---

## LEGEND

| Symbol | Meaning |
|--------|---------|
| **✓** | Field available directly, no transformation |
| **→ Transform** | Field exists but requires transformation |
| **⊕ Calculated** | Derived from other fields or system |
| **📧 Email Parse** | Extracted via email parsing |
| **—** | Not available in this source |

---

## MAPPING MATRIX

**CRITICAL NOTES:**

1. **Patron list file names indicate delivery method:**
   - **voice_patrons** → All patrons use DeliveryOptionID 3 (Voice)
   - **text_patrons** → All patrons use DeliveryOptionID 8 (Text/SMS)

2. **Export file names indicate notification type:**
   - **holds** → notification_type_id = 2 (Hold)
   - **renew** → notification_type_id = 7 (Renewal Reminder)
   - **overdue** → notification_type_id = 1, 7, 8, 11, 12, or 13 (requires enrichment for exact type)

---

### TABLE: polaris_phone_notices

**Purpose:** Validation baseline - what Polaris queued for notification

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | ⊕ Auto-increment | Primary key |
| **delivery_method** | — | — | — | — | — | ✓ Field 1 | — | — | — | 'V' or 'T' |
| **language** | — | — | — | — | — | ✓ Field 2 | — | — | — | ISO 639-2/T code |
| **notice_type** | — | — | — | — | — | ✓ Field 3 | — | — | — | 1-4 (i-tiva types) |
| **notification_level** | — | — | — | — | — | ✓ Field 4 | — | — | — | 1=default, 2=2nd, 3=3rd |
| **patron_barcode** | ✓ Field 7 | ✓ Field 13 | ✓ Field 13 | ✓ Field 2 | ✓ Field 2 | ✓ Field 5 | 📧 Parse line | 📧 Parse line | — | Universal key |
| **patron_title** | — | — | — | — | — | ✓ Field 6 | — | — | — | Mr., Mrs., Dr., etc. |
| **name_first** | — | — | — | — | — | ✓ Field 7 | — | 📧 Parse (LAST, FIRST) | — | |
| **name_last** | — | — | — | — | — | ✓ Field 8 | — | 📧 Parse (LAST, FIRST) | — | |
| **phone_number** | — | — | — | → Transform Field 1 | → Transform Field 1 | ✓ Field 9 | 📧 Parse line | — | — | May have formatting |
| **email_address** | — | — | — | — | — | ✓ Field 10 | — | — | — | |
| **site_code** | — | — | — | — | — | ✓ Field 11 | — | — | — | "DCPL" |
| **site_name** | — | — | — | — | — | ✓ Field 12 | — | — | — | Branch name |
| **item_barcode** | — | ✓ Field 2 | ✓ Field 2 | — | — | ✓ Field 13 | — | — | — | |
| **due_date** | — | ✓ Field 4 | ✓ Field 4 | — | — | → Transform Field 14 | — | — | — | PhoneNotices: MM/DD/YYYY → YYYY-MM-DD |
| **browse_title** | ✓ Field 1 | ✓ Field 3 | ✓ Field 3 | — | — | ✓ Field 15 | — | 📧 Parse (message_type) | — | |
| **reporting_org_id** | ✓ Field 5 (always 3) | — | — | — | — | ✓ Field 16 | — | — | — | Branch ID |
| **language_id** | — | — | — | — | — | ✓ Field 17 | — | — | — | 1033=English |
| **notification_type_id** | ⊕ Infer from filename: holds*.txt → 2 | ⊕ Infer from filename: overdue*.txt → 1/7/8/11/12/13 | ⊕ Infer from filename: renew*.txt → 7 | — | — | ✓ Field 18 | — | — | — | **File name indicates notification type** |
| **delivery_option_id** | ⊕ Infer from patron list | ⊕ Infer from patron list | ⊕ Infer from patron list | ⊕ Infer: patron in voice_patrons*.txt → 3 | ⊕ Infer: patron in text_patrons*.txt → 8 | ✓ Field 19 | 📧 Parse "SMS" → 8 | ⊕ Always 3 (voice) | — | **3=Phone1 (Voice), 8=TXT Messaging** |
| **patron_id** | ✓ Field 4 | ✓ Field 1 | ✓ Field 1 | — | — | ✓ Field 20 | 📧 Parse line | — | — | Internal Polaris ID |
| **item_record_id** | — | ✓ Field 5 | ✓ Field 5 | — | — | ✓ Field 21 | — | — | — | |
| **sys_hold_request_id** | ✓ Field 3 | — | — | — | — | ✓ Field 22 | — | — | — | Hold-specific |
| **pickup_area_description** | — | — | — | — | — | ✓ Field 23 | — | — | — | Conditional field |
| **txn_id** | — | — | — | — | — | ✓ Field 24 | — | — | — | Manual bills only |
| **account_balance** | — | — | — | — | — | ✓ Field 25 | — | — | — | Fines/bills only |
| **import_date** | ⊕ File timestamp | ⊕ File timestamp | ⊕ File timestamp | ⊕ File timestamp | ⊕ File timestamp | ⊕ File timestamp | ⊕ Email received date | ⊕ Email received date | ⊕ Report month | |
| **import_timestamp** | ⊕ Import time | ⊕ Import time | ⊕ Import time | ⊕ Import time | ⊕ Import time | ⊕ Import time | ⊕ Import time | ⊕ Import time | ⊕ Import time | |
| **source_file** | ⊕ Filename | ⊕ Filename | ⊕ Filename | ⊕ Filename | ⊕ Filename | ⊕ Filename | ⊕ Email subject | ⊕ Email subject | ⊕ Email subject | |

---

### TABLE: notifications_holds

**Purpose:** Hold notifications sent to ShoutBomb

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | ⊕ Auto-increment | — | — | — | — | — | — | — | — | Primary key |
| **browse_title** | ✓ Field 1 | — | — | — | — | — | — | — | — | |
| **creation_date** | ✓ Field 2 | — | — | — | — | — | — | — | — | YYYY-MM-DD |
| **sys_hold_request_id** | ✓ Field 3 | — | — | — | — | — | — | — | — | Primary linking key |
| **patron_id** | ✓ Field 4 | — | — | — | — | — | — | — | — | |
| **pickup_organization_id** | ✓ Field 5 | — | — | — | — | — | — | — | — | Always 3 |
| **hold_till_date** | ✓ Field 6 | — | — | — | — | — | — | — | — | YYYY-MM-DD |
| **patron_barcode** | ✓ Field 7 | — | — | — | — | — | — | — | — | |
| **export_timestamp** | ⊕ From filename | — | — | — | — | — | — | — | — | Parse: holds_submitted_YYYY-MM-DD_HH-MM-SS_.txt |
| **notification_type_id** | ⊕ Infer from filename | — | — | — | — | — | — | — | — | holds*.txt → ALWAYS 2 (Hold) |
| **source_file** | ⊕ Filename | — | — | — | — | — | — | — | — | |

---

### TABLE: notifications_overdue

**Purpose:** Overdue/fine/bill notifications sent to ShoutBomb

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | — | ⊕ Auto-increment | — | — | — | — | — | — | — | Primary key |
| **patron_id** | — | ✓ Field 1 | — | — | — | — | — | — | — | |
| **item_barcode** | — | ✓ Field 2 | — | — | — | — | — | — | — | |
| **title** | — | ✓ Field 3 | — | — | — | — | — | — | — | |
| **due_date** | — | ✓ Field 4 | — | — | — | — | — | — | — | YYYY-MM-DD |
| **item_record_id** | — | ✓ Field 5 | — | — | — | — | — | — | — | |
| **dummy1-4** | — | ✓ Fields 6-9 | — | — | — | — | — | — | — | Always empty (||||) |
| **renewals** | — | ✓ Field 10 | — | — | — | — | — | — | — | Times renewed |
| **bibliographic_record_id** | — | ✓ Field 11 | — | — | — | — | — | — | — | |
| **renewal_limit** | — | ✓ Field 12 | — | — | — | — | — | — | — | Max renewals |
| **patron_barcode** | — | ✓ Field 13 | — | — | — | — | — | — | — | |
| **export_timestamp** | — | ⊕ From filename | — | — | — | — | — | — | — | Parse: overdue_submitted_YYYY-MM-DD_HH-MM-SS.txt |
| **notification_type_id** | — | ⊕ Infer from filename + context | — | — | — | — | — | — | — | overdue*.txt → 1, 7, 8, 11, 12, or 13 (requires PhoneNotices for exact type) |
| **source_file** | — | ⊕ Filename | — | — | — | — | — | — | — | |

---

### TABLE: notifications_renewal

**Purpose:** Renewal reminder notifications sent to ShoutBomb

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | — | — | ⊕ Auto-increment | — | — | — | — | — | — | Primary key |
| **patron_id** | — | — | ✓ Field 1 | — | — | — | — | — | — | |
| **item_barcode** | — | — | ✓ Field 2 | — | — | — | — | — | — | |
| **title** | — | — | ✓ Field 3 | — | — | — | — | — | — | |
| **due_date** | — | — | ✓ Field 4 | — | — | — | — | — | — | YYYY-MM-DD (3-4 days future) |
| **item_record_id** | — | — | ✓ Field 5 | — | — | — | — | — | — | |
| **dummy1-4** | — | — | ✓ Fields 6-9 | — | — | — | — | — | — | Always empty (||||) |
| **renewals** | — | — | ✓ Field 10 | — | — | — | — | — | — | Times renewed |
| **bibliographic_record_id** | — | — | ✓ Field 11 | — | — | — | — | — | — | |
| **renewal_limit** | — | — | ✓ Field 12 | — | — | — | — | — | — | Max renewals |
| **patron_barcode** | — | — | ✓ Field 13 | — | — | — | — | — | — | |
| **export_timestamp** | — | — | ⊕ From filename | — | — | — | — | — | — | Parse: renew_submitted_YYYY-MM-DD_HH-MM-SS.txt |
| **notification_type_id** | — | — | ⊕ Infer from filename | — | — | — | — | — | — | renew*.txt → ALWAYS 7 (Renewal Reminder) |
| **source_file** | — | — | ⊕ Filename | — | — | — | — | — | — | |

---

### TABLE: patrons_notification_preferences

**Purpose:** Phone-to-barcode mapping for voice and text delivery

**CRITICAL NOTE:** The file name itself indicates the delivery method:
- **voice_patrons*.txt** = All patrons in this file use DeliveryOptionID 3 (Phone1 - Voice)
- **text_patrons*.txt** = All patrons in this file use DeliveryOptionID 8 (TXT Messaging)

Both use the same phone field (PhoneVoice1) but different delivery methods.

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | — | — | — | ⊕ Auto-increment | ⊕ Auto-increment | — | — | — | — | Primary key |
| **patron_barcode** | — | — | — | ✓ Field 2 | ✓ Field 2 | — | — | — | — | |
| **phone_voice1** | — | — | — | ✓ Field 1 | ✓ Field 1 | — | — | — | — | 10 digits, no dashes |
| **delivery_method** | — | — | — | ⊕ Always 'voice' | ⊕ Always 'text' | — | — | — | — | Enum |
| **import_date** | — | — | — | ⊕ File timestamp | ⊕ File timestamp | — | — | — | — | |
| **import_timestamp** | — | — | — | ⊕ Import time | ⊕ Import time | — | — | — | — | |
| **source_file** | — | — | — | ⊕ Filename | ⊕ Filename | — | — | — | — | |

---

### TABLE: notice_failure_reports (Enhanced)

**Purpose:** Delivery failures from ShoutBomb with enrichment

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | — | — | — | — | — | — | ⊕ Auto-increment | ⊕ Auto-increment | — | Primary key |
| **outlook_message_id** | — | — | — | — | — | — | 📧 Email ID | 📧 Email ID | — | From Graph API |
| **subject** | — | — | — | — | — | — | 📧 Email subject | 📧 Email subject | — | |
| **from_address** | — | — | — | — | — | — | 📧 From: field | 📧 From: field | — | |
| **patron_phone** | — | — | — | — | — | — | 📧 Parse: phone :: | 📧 Parse line | — | 10 digits |
| **patron_id** | — | — | — | — | — | — | 📧 Parse: patron_id :: | — | — | If available |
| **patron_barcode** | — | — | — | — | — | — | 📧 Parse: barcode :: | 📧 Parse barcode | — | May be partial (last 4) |
| **barcode_partial** | — | — | — | — | — | — | 📧 Detect XXXX#### | — | — | True if redacted |
| **patron_name** | — | — | — | — | — | — | — | 📧 Parse: LAST, FIRST | — | |
| **contact_type** | — | — | — | — | — | — | ⊕ Always 'phone' | ⊕ Always 'phone' | — | NEW field |
| **contact_value** | — | — | — | — | — | — | 📧 Parse: phone | 📧 Parse phone | — | NEW field |
| **notification_type_id** | — | — | — | — | — | — | ⊕ Enrich from PhoneNotices | ⊕ Enrich from PhoneNotices | — | NEW field |
| **delivery_option_id** | — | — | — | — | — | — | 📧 Parse "SMS" → 8 | ⊕ Always 3 | — | NEW field |
| **item_record_id** | — | — | — | — | — | — | ⊕ Enrich from PhoneNotices | ⊕ Enrich from PhoneNotices | — | NEW field |
| **sys_hold_request_id** | — | — | — | — | — | — | ⊕ Enrich from PhoneNotices | ⊕ Enrich from PhoneNotices | — | NEW field |
| **bibliographic_record_id** | — | — | — | — | — | — | ⊕ Enrich from PhoneNotices | ⊕ Enrich from PhoneNotices | — | NEW field |
| **notification_queued_at** | — | — | — | — | — | — | ⊕ Enrich from PhoneNotices | ⊕ Enrich from PhoneNotices | — | NEW field |
| **notification_sent_at** | — | — | — | — | — | — | 📧 Email date (approx) | 📧 Email date (approx) | — | NEW field |
| **export_timestamp** | — | — | — | — | — | — | ⊕ Enrich from exports | ⊕ Enrich from exports | — | NEW field |
| **delivery_method** | — | — | — | — | — | — | 📧 Parse "SMS"/"Voice" | ⊕ Always 'voice' | — | RENAMED from notice_type |
| **failure_type** | — | — | — | — | — | — | 📧 Parse section (opted-out/invalid) | 📧 Always 'voice-not-delivered' | — | |
| **failure_reason** | — | — | — | — | — | — | 📧 Parse details | 📧 Parse details | — | |
| **failure_category** | — | — | — | — | — | — | ⊕ Categorize failure_type | ⊕ Categorize failure_type | — | NEW field |
| **account_status** | — | — | — | — | — | — | 📧 Parse if present | 📧 Parse if present | — | |
| **notice_description** | — | — | — | — | — | — | — | 📧 Parse: message_type | — | |
| **attempt_count** | — | — | — | — | — | — | — | — | — | If available |
| **phone_notices_import_id** | — | — | — | — | — | — | ⊕ Enrich: link to PhoneNotices | ⊕ Enrich: link to PhoneNotices | — | NEW field (FK) |
| **notification_export_id** | — | — | — | — | — | — | ⊕ Enrich: link to export | ⊕ Enrich: link to export | — | NEW field |
| **received_at** | — | — | — | — | — | — | 📧 Email received timestamp | 📧 Email received timestamp | — | |
| **processed_at** | — | — | — | — | — | — | ⊕ Processing timestamp | ⊕ Processing timestamp | — | |
| **raw_content** | — | — | — | — | — | — | 📧 Full email body | 📧 Full email body | — | Optional debug |

---

### TABLE: shoutbomb_monthly_stats

**Purpose:** Aggregate statistics from monthly reports

| Database Field | holds*.txt | overdue*.txt | renew*.txt | voice_patrons | text_patrons | PhoneNotices.csv | Invalid Phone Email | Voice Failed Email | Monthly Rpt | Notes |
|----------------|------------|--------------|------------|---------------|--------------|------------------|--------------------|--------------------|-------------|-------|
| **id** | — | — | — | — | — | — | — | — | ⊕ Auto-increment | Primary key |
| **outlook_message_id** | — | — | — | — | — | — | — | — | 📧 Email ID | |
| **subject** | — | — | — | — | — | — | — | — | 📧 Email subject | |
| **report_month** | — | — | — | — | — | — | — | — | 📧 Parse subject/body | |
| **branch_name** | — | — | — | — | — | — | — | — | 📧 Parse body | |
| **hold_text_notices** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **hold_text_reminders** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **hold_voice_notices** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **hold_voice_reminders** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **overdue_text_notices** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **overdue_text_eligible_renewal** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **overdue_text_ineligible_renewal** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **overdue_voice_notices** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| *(All other monthly stat fields)* | — | — | — | — | — | — | — | — | 📧 Parse counts | See migration file |
| **total_registered_users** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **total_registered_text** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **total_registered_voice** | — | — | — | — | — | — | — | — | 📧 Parse count | |
| **keyword_usage** | — | — | — | — | — | — | — | — | 📧 Parse JSON | Array of keyword counts |
| **received_at** | — | — | — | — | — | — | — | — | 📧 Email timestamp | |
| **processed_at** | — | — | — | — | — | — | — | — | ⊕ Processing time | |

---

## TRANSFORMATION DETAILS

### Phone Number Standardization

**From voice_patrons/text_patrons:**
```
Input:  "5551234567" (already standardized)
Output: phone_voice1 = "5551234567"
```

**From PhoneNotices.csv:**
```
Input:  "555-123-4567" or "(555) 123-4567" or "555.123.4567"
Output: phone_number = REGEXP_REPLACE(input, '[^0-9]', '')
Result: "5551234567"
```

**From Invalid Phone Email:**
```
Input:  Line format: "5551234567 :: 23307000001234 :: 100001 :: 3 :: SMS"
Output: patron_phone = SPLIT(line, ' :: ')[0]
Result: "5551234567"
```

### Notification Type Inference from Export File Names

**Rule:** The export file name indicates which notification type(s) it contains.

**By File Name:**
```
holds*.txt   → notification_type_id = 2 (Hold ready for pickup)
renew*.txt   → notification_type_id = 7 (Renewal reminder / Almost overdue)
overdue*.txt → notification_type_id IN (1, 7, 8, 11, 12, 13) - Multiple types
```

**Implementation:**
```php
// When importing notification export files
public function inferNotificationTypeId(string $filename): int|array
{
    if (str_contains($filename, 'holds')) {
        return 2; // Hold
    }
    
    if (str_contains($filename, 'renew')) {
        return 7; // Renewal Reminder / Almost Overdue
    }
    
    if (str_contains($filename, 'overdue')) {
        // Multiple notification types in overdue export
        // Exact type requires cross-reference with PhoneNotices
        return [1, 7, 8, 11, 12, 13];
    }
    
    return null;
}
```

**SQL Implementation:**
```sql
-- When importing holds.txt
INSERT INTO notifications_holds (..., notification_type_id)
VALUES (..., 2);  -- Inferred from filename

-- When importing renew.txt
INSERT INTO notifications_renewal (..., notification_type_id)
VALUES (..., 7);  -- Inferred from filename

-- When importing overdue.txt (requires enrichment for exact type)
INSERT INTO notifications_overdue (..., notification_type_id)
VALUES (..., NULL);  -- Will be enriched from PhoneNotices

-- Enrich overdue notifications with exact type
UPDATE notifications_overdue o
INNER JOIN polaris_phone_notices pn
    ON o.patron_id = pn.patron_id
   AND o.item_record_id = pn.item_record_id
   AND DATE(o.export_timestamp) = pn.import_date
SET o.notification_type_id = pn.notification_type_id
WHERE o.notification_type_id IS NULL
  AND pn.notification_type_id IN (1, 7, 8, 11, 12, 13);
```

**Notification Type Reference:**

| notification_type_id | Type Name | Export File | Notes |
|---------------------|-----------|-------------|-------|
| 1 | 1st Overdue | overdue.txt | Day 1 after due date |
| 2 | Hold | **holds.txt** | Item ready for pickup |
| 7 | Almost overdue / Renewal reminder | renew.txt OR overdue.txt | 3-1 days before due OR pre-due |
| 8 | Fine | overdue.txt | Fine/fee notice |
| 11 | Bill | overdue.txt | Billing notice (lost item) |
| 12 | 2nd Overdue | overdue.txt | Day 7 after due date |
| 13 | 3rd Overdue | overdue.txt | Day 14 after due date |

**Key Points:**

1. **holds.txt** - Simple: ONLY contains Type 2 (Hold)
2. **renew.txt** - Simple: ONLY contains Type 7 (Renewal Reminder)
3. **overdue.txt** - Complex: Contains MULTIPLE types (1, 7, 8, 11, 12, 13)

**Why overdue.txt has multiple types:**
- Type 7: Pre-due reminders (3 days and 1 day before due)
- Type 1: 1st overdue (Day 1 after due)
- Type 12: 2nd overdue (Day 7 after due)
- Type 13: 3rd overdue (Day 14 after due)
- Type 8: Fine notices
- Type 11: Bill notices

**Determining exact type for overdue.txt records:**

```sql
-- Option 1: Cross-reference with PhoneNotices (most accurate)
SELECT o.*, pn.notification_type_id
FROM notifications_overdue o
INNER JOIN polaris_phone_notices pn
    ON o.patron_id = pn.patron_id
   AND o.item_record_id = pn.item_record_id
   AND DATE(o.export_timestamp) = pn.import_date;

-- Option 2: Calculate from due_date (approximate)
SELECT 
    *,
    CASE 
        WHEN due_date > CURDATE() THEN 7  -- Pre-due (Type 7)
        WHEN DATEDIFF(CURDATE(), due_date) = 1 THEN 1  -- 1st overdue
        WHEN DATEDIFF(CURDATE(), due_date) = 7 THEN 12 -- 2nd overdue
        WHEN DATEDIFF(CURDATE(), due_date) = 14 THEN 13 -- 3rd overdue
        ELSE NULL  -- Fine or Bill (can't determine)
    END as calculated_notification_type
FROM notifications_overdue;
```

### Date Format Conversions

**From holds/overdue/renew exports:**
```
Input:  "2025-11-22" (YYYY-MM-DD)
Output: Direct insert (already correct format)
```

**From PhoneNotices.csv:**
```
Input:  "11/22/2025" (MM/DD/YYYY)
Output: STR_TO_DATE(input, '%m/%d/%Y')
Result: DATE '2025-11-22'
```

### Barcode Handling

**Standard barcode:**
```
Input:  "23307000001234"
Output: patron_barcode = "23307000001234", barcode_partial = FALSE
```

**Partial/redacted barcode (from email):**
```
Input:  "XXXXXXXXXX1234" (last 4 digits only)
Output: patron_barcode = "XXXXXXXXXX1234", barcode_partial = TRUE
```

### Timestamp Extraction from Filenames

**holds.txt archive:**
```
Filename: holds_submitted_2025-11-22_08-05-30_.txt
Extract:  export_timestamp = '2025-11-22 08:05:30'
```

**overdue.txt archive:**
```
Filename: overdue_submitted_2025-11-22_08-04-15.txt
Extract:  export_timestamp = '2025-11-22 08:04:15'
```

### Notification Type Inference

**From export file context:**
```
If from holds*.txt → notification_type_id = 2 (Hold)
If from renew*.txt → notification_type_id = 7 (Renewal Reminder)
If from overdue*.txt → notification_type_id = 1 (or 7/8/11/12/13 - requires PhoneNotices for exact type)
```

### Delivery Option Inference from Patron Lists

**Critical Rule:** A patron's delivery method is determined by which patron list file they appear in.

**By File Name:**
```
voice_patrons*.txt → delivery_option_id = 3 (Phone1 - Voice calls)
text_patrons*.txt  → delivery_option_id = 8 (TXT Messaging - SMS)
```

**Implementation:**
```php
// When importing notification exports (holds/overdue/renew),
// delivery_option_id can be populated by checking patron list membership

public function inferDeliveryOptionId(string $patronBarcode, string $importDate): ?int
{
    // Check voice_patrons list
    $isVoicePatron = DB::table('patrons_notification_preferences')
        ->where('patron_barcode', $patronBarcode)
        ->where('delivery_method', 'voice')
        ->where('import_date', $importDate)
        ->exists();
    
    if ($isVoicePatron) {
        return 3; // Phone1 (Voice)
    }
    
    // Check text_patrons list
    $isTextPatron = DB::table('patrons_notification_preferences')
        ->where('patron_barcode', $patronBarcode)
        ->where('delivery_method', 'text')
        ->where('import_date', $importDate)
        ->exists();
    
    if ($isTextPatron) {
        return 8; // TXT Messaging
    }
    
    return null; // Patron not in either list (shouldn't happen for voice/text notifications)
}
```

**SQL Enrichment Example:**
```sql
-- Enrich notifications_holds with delivery_option_id from patron lists
UPDATE notifications_holds h
INNER JOIN patrons_notification_preferences pp
    ON h.patron_barcode = pp.patron_barcode
   AND DATE(h.export_timestamp) = pp.import_date
SET h.delivery_option_id = CASE 
    WHEN pp.delivery_method = 'voice' THEN 3
    WHEN pp.delivery_method = 'text' THEN 8
END
WHERE h.delivery_option_id IS NULL;
```

**Validation Query:**
```sql
-- Verify all notification export records have correct delivery_option_id
SELECT 
    h.patron_barcode,
    pp.delivery_method,
    CASE 
        WHEN pp.delivery_method = 'voice' THEN 3
        WHEN pp.delivery_method = 'text' THEN 8
    END as expected_delivery_option,
    h.delivery_option_id as actual_delivery_option,
    CASE 
        WHEN pp.delivery_method = 'voice' AND h.delivery_option_id != 3 THEN '❌ MISMATCH'
        WHEN pp.delivery_method = 'text' AND h.delivery_option_id != 8 THEN '❌ MISMATCH'
        ELSE '✓ Correct'
    END as validation_status
FROM notifications_holds h
INNER JOIN patrons_notification_preferences pp
    ON h.patron_barcode = pp.patron_barcode
   AND DATE(h.export_timestamp) = pp.import_date
WHERE h.delivery_option_id IS NOT NULL;
```

**From Polaris DeliveryOptionID Reference:**

| delivery_option_id | Polaris Description | Patron List File | Notes |
|-------------------|---------------------|------------------|-------|
| 1 | Mailing Address | (neither) | Physical mail - not in patron lists |
| 2 | Email Address | (neither) | Email - not in patron lists |
| 3 | Phone 1 (Voice) | **voice_patrons*.txt** | Voice calls to PhoneVoice1 |
| 4 | Phone 2 | (not used by DCPL) | Secondary phone |
| 5 | Phone 3 | (not used by DCPL) | Tertiary phone |
| 6 | FAX | (not used) | Legacy |
| 7 | EDI | (not used) | Legacy |
| 8 | TXT Messaging | **text_patrons*.txt** | SMS to PhoneVoice1 |

**DCPL Usage:**
- Only DeliveryOptionID **3** (Voice) and **8** (Text) are actively used
- Both use the same phone field: **PhoneVoice1**
- Distinction is made by which patron list file contains the patron barcode

### Delivery Option Inference from Email Context

**From email parsing:**
```
If "SMS" in line → delivery_option_id = 8
If voice failure email → delivery_option_id = 3
```

---

## ENRICHMENT PROCESS

### Step 1: Import Base Data (Patron Lists FIRST)
```
1. Import voice_patrons.txt → patrons_notification_preferences (delivery_method='voice')
2. Import text_patrons.txt → patrons_notification_preferences (delivery_method='text')
   
   ⚠️ CRITICAL: These must be imported FIRST because they determine delivery_option_id
                for all notification exports that follow.
```

### Step 2: Import Validation Baseline
```
3. Import PhoneNotices.csv → polaris_phone_notices
```

### Step 3: Import & Enrich Notification Exports
```
4. Import holds.txt → notifications_holds
   - Enrich: Set delivery_option_id based on patron list membership
   
5. Import overdue.txt → notifications_overdue
   - Enrich: Set delivery_option_id based on patron list membership
   
6. Import renew.txt → notifications_renewal
   - Enrich: Set delivery_option_id based on patron list membership
```

**Enrichment SQL for Notification Exports:**
```sql
-- After importing holds.txt, enrich with delivery_option_id
UPDATE notifications_holds h
SET h.delivery_option_id = (
    SELECT CASE 
        WHEN pp.delivery_method = 'voice' THEN 3
        WHEN pp.delivery_method = 'text' THEN 8
    END
    FROM patrons_notification_preferences pp
    WHERE pp.patron_barcode = h.patron_barcode
      AND pp.import_date = DATE(h.export_timestamp)
    LIMIT 1
)
WHERE h.delivery_option_id IS NULL;

-- Repeat for notifications_overdue and notifications_renewal
```

### Step 2: Parse Email Failures
```
7. Parse "Invalid phone" emails → notice_failure_reports (basic fields only)
8. Parse "Voice not delivered" emails → notice_failure_reports (basic fields only)
```

### Step 3: Enrich Failures with Polaris Context
```sql
-- Link failures to PhoneNotices
UPDATE notice_failure_reports nfr
INNER JOIN polaris_phone_notices pn
    ON nfr.patron_id = pn.patron_id
   AND DATE(nfr.received_at) = pn.import_date
SET nfr.notification_type_id = pn.notification_type_id,
    nfr.phone_notices_import_id = pn.id,
    nfr.item_record_id = pn.item_record_id,
    nfr.sys_hold_request_id = pn.sys_hold_request_id,
    nfr.notification_queued_at = pn.import_date
WHERE nfr.notification_type_id IS NULL;

-- Link failures to export records
UPDATE notice_failure_reports nfr
INNER JOIN notifications_holds h
    ON nfr.sys_hold_request_id = h.sys_hold_request_id
   AND DATE(nfr.received_at) = DATE(h.export_timestamp)
SET nfr.notification_export_id = h.id,
    nfr.export_timestamp = h.export_timestamp
WHERE nfr.notification_type_id = 2;
```

### Step 4: Parse Monthly Statistics
```
9. Parse "Shoutbomb Rpt" emails → shoutbomb_monthly_stats
```

---

## MISSING DATA GAPS

### Data Available in PhoneNotices but NOT in Exports

| Field | Available In | Missing From | Impact |
|-------|-------------|--------------|--------|
| **name_first, name_last** | PhoneNotices.csv | All exports | Need PhoneNotices for patron names |
| **email_address** | PhoneNotices.csv | All exports | Need PhoneNotices for email |
| **notification_type_id (exact)** | PhoneNotices.csv | overdue.txt | Can't distinguish Type 1/7/8/11/12/13 from overdue.txt alone |
| **bibliographic_record_id** | PhoneNotices.csv | All exports | Need PhoneNotices for catalog record linking |
| **pickup_area_description** | PhoneNotices.csv | holds.txt | Need PhoneNotices for hold shelf location |

### Data Available in Exports but NOT in PhoneNotices

| Field | Available In | Missing From | Impact |
|-------|-------------|--------------|--------|
| **creation_date** | holds.txt | PhoneNotices.csv | When hold was placed |
| **hold_till_date** | holds.txt | PhoneNotices.csv | Hold expiration date |
| **renewals** | overdue.txt, renew.txt | PhoneNotices.csv | How many times renewed |
| **renewal_limit** | overdue.txt, renew.txt | PhoneNotices.csv | Max renewals allowed |

### Data ONLY in Email Reports

| Field | Source | Notes |
|-------|--------|-------|
| **failure_type** | Invalid Phone / Voice Failed emails | Why delivery failed |
| **failure_reason** | Invalid Phone / Voice Failed emails | Detailed error |
| **barcode_partial** | Invalid Phone emails | Indicates redacted barcode |
| **keyword_usage** | Monthly statistics | Patron interactions (HL, RHL, etc.) |
| **monthly aggregates** | Monthly statistics | Success counts by type |

---

## ADDITIONAL SOURCES TO CONSIDER

### Currently Not Documented - Should We Add?

1. **Weekly Report Emails** ("WEEKLY_REPORT+{MM-YYYY}")
   - Similar to monthly but broken down by week
   - Contains same metrics as monthly
   - **Recommendation:** Add if you want weekly granularity in stats

2. **Patron Keyword Response Logs** (if available)
   - Individual patron interactions with system
   - "RA" (renew all), "HL" (hold list), etc.
   - **Question:** Does ShoutBomb send these individually or only in aggregates?

3. **SMS Delivery Failure Reports** (if separate from Invalid Phone)
   - Similar to voice failures but for SMS
   - **Question:** Are these in "Invalid Phone" report or separate?

4. **Hold Cancellation Reports**
   - When holds are cancelled (patron or system)
   - **Question:** Available as separate report?

5. **Patron Registration Confirmation**
   - When patrons sign up for ShoutBomb
   - **Question:** Tracked anywhere besides monthly aggregates?

---

## EXPORT FILE NAME → NOTIFICATION TYPE WORKFLOW

### Complete Example: Determining Notification Type from File Name

**Scenario:** Processing three different notification export files.

**File 1: holds_submitted_2025-11-22_08-05-00_.txt**
```
The Silent Patient|2025-11-22|900001|100001|3|2025-11-29|23307000001234
```

**Processing:**
```php
$filename = 'holds_submitted_2025-11-22_08-05-00_.txt';

if (str_contains($filename, 'holds')) {
    $notificationTypeId = 2;  // Hold ready for pickup
}

// Insert with notification_type_id
insert('notifications_holds', [
    'browse_title' => 'The Silent Patient',
    'sys_hold_request_id' => 900001,
    'patron_barcode' => '23307000001234',
    'notification_type_id' => 2,  // ← Inferred from filename
    'export_timestamp' => '2025-11-22 08:05:00'
]);
```

**Result:** notification_type_id = 2 (Hold)

---

**File 2: renew_submitted_2025-11-22_08-03-00.txt**
```
100003|33307000002003|Educated|2025-11-15|200003|||||1|300003|2|23307000001003
```

**Processing:**
```php
$filename = 'renew_submitted_2025-11-22_08-03-00.txt';

if (str_contains($filename, 'renew')) {
    $notificationTypeId = 7;  // Renewal reminder
}

// Insert with notification_type_id
insert('notifications_renewal', [
    'patron_id' => 100003,
    'item_barcode' => '33307000002003',
    'title' => 'Educated',
    'due_date' => '2025-11-15',
    'patron_barcode' => '23307000001003',
    'notification_type_id' => 7,  // ← Inferred from filename
    'export_timestamp' => '2025-11-22 08:03:00'
]);
```

**Result:** notification_type_id = 7 (Renewal Reminder)

---

**File 3: overdue_submitted_2025-11-22_08-04-00.txt**
```
100002|33307000002002|Where the Crawdads Sing|2025-10-25|200002|||||0|300002|2|23307000001002
```

**Processing:**
```php
$filename = 'overdue_submitted_2025-11-22_08-04-00.txt';

if (str_contains($filename, 'overdue')) {
    // Can't determine EXACT type from filename alone
    // Could be: 1 (1st Overdue), 7 (Pre-due), 8 (Fine), 11 (Bill), 12 (2nd), 13 (3rd)
    $notificationTypeId = null;  // Will enrich from PhoneNotices
}

// Insert without notification_type_id initially
insert('notifications_overdue', [
    'patron_id' => 100002,
    'item_barcode' => '33307000002002',
    'title' => 'Where the Crawdads Sing',
    'due_date' => '2025-10-25',
    'patron_barcode' => '23307000001002',
    'notification_type_id' => null,  // ← Cannot infer exact type from filename
    'export_timestamp' => '2025-11-22 08:04:00'
]);

// Later: Enrich with exact type from PhoneNotices
$phoneNotice = select('polaris_phone_notices', [
    'patron_id' => 100002,
    'item_record_id' => 200002,
    'import_date' => '2025-11-22'
]);

update('notifications_overdue', [
    'notification_type_id' => $phoneNotice->notification_type_id  // e.g., 1
]);
```

**Result:** notification_type_id = 1 (1st Overdue) - after enrichment

---

### Summary Table

| File Pattern | Notification Type(s) | Can Infer Exact Type? | Notes |
|-------------|---------------------|----------------------|-------|
| **holds*.txt** | 2 (Hold) | ✅ Yes - Always Type 2 | Simple: One file, one type |
| **renew*.txt** | 7 (Renewal) | ✅ Yes - Always Type 7 | Simple: One file, one type |
| **overdue*.txt** | 1, 7, 8, 11, 12, 13 | ❌ No - Multiple types | Complex: Requires PhoneNotices for exact type |

### Implementation Decision Tree

```
When importing notification export:

1. Parse filename
   │
   ├─ Contains "holds"?
   │  └─ Set notification_type_id = 2 (Hold)
   │     ✓ DONE - No enrichment needed
   │
   ├─ Contains "renew"?
   │  └─ Set notification_type_id = 7 (Renewal)
   │     ✓ DONE - No enrichment needed
   │
   └─ Contains "overdue"?
      └─ Set notification_type_id = NULL
         ⚠️ MUST ENRICH: Join to PhoneNotices to get exact type
         
         SELECT pn.notification_type_id
         FROM polaris_phone_notices pn
         WHERE pn.patron_id = overdue.patron_id
           AND pn.item_record_id = overdue.item_record_id
           AND pn.import_date = DATE(overdue.export_timestamp)
```

---

## PATRON LIST FILE → DELIVERY METHOD WORKFLOW

### Complete Example: Determining How a Notification Will Be Delivered

**Scenario:** Patron "23307000001234" has a hold ready for pickup.

**Step 1: Check Which Patron List File Contains This Barcode**

```bash
# Check voice_patrons_submitted_2025-11-22_04-00-00.txt
grep "23307000001234" voice_patrons_submitted_2025-11-22_04-00-00.txt
# Result: 5551234567|23307000001234

# Check text_patrons_submitted_2025-11-22_05-00-00.txt  
grep "23307000001234" text_patrons_submitted_2025-11-22_05-00-00.txt
# Result: (not found)
```

**Conclusion:** Patron is in **voice_patrons.txt** → They will receive **voice call**

**Step 2: Import to Database**

```sql
-- Insert into patrons_notification_preferences
INSERT INTO patrons_notification_preferences 
(patron_barcode, phone_voice1, delivery_method, import_date)
VALUES 
('23307000001234', '5551234567', 'voice', '2025-11-22');
```

**Step 3: When Hold Notification is Exported**

```sql
-- holds.txt contains: The Silent Patient|2025-11-22|900001|100001|3|2025-11-29|23307000001234
-- Import to notifications_holds table

INSERT INTO notifications_holds
(browse_title, creation_date, sys_hold_request_id, patron_id, 
 pickup_organization_id, hold_till_date, patron_barcode, export_timestamp)
VALUES 
('The Silent Patient', '2025-11-22', 900001, 100001, 
 3, '2025-11-29', '23307000001234', '2025-11-22 08:05:00');
```

**Step 4: Enrich with Delivery Method**

```sql
-- Look up patron in patron list to determine delivery_option_id
UPDATE notifications_holds h
SET h.delivery_option_id = 3  -- Voice (from patron list membership)
WHERE h.patron_barcode = '23307000001234'
  AND h.sys_hold_request_id = 900001;
```

**Final Result:**
```
patron_barcode: 23307000001234
notification_type_id: 2 (Hold)
delivery_option_id: 3 (Voice)
→ ShoutBomb will make a VOICE CALL to 5551234567
```

### Contrasting Example: Text Notification

**Scenario:** Patron "23307000001002" has the same hold.

```bash
# Check voice_patrons
grep "23307000001002" voice_patrons*.txt
# Result: (not found)

# Check text_patrons  
grep "23307000001002" text_patrons*.txt
# Result: 5559876543|23307000001002
```

**Conclusion:** Patron is in **text_patrons.txt** → They will receive **SMS text**

**Final Result:**
```
patron_barcode: 23307000001002
notification_type_id: 2 (Hold)
delivery_option_id: 8 (TXT Messaging)
→ ShoutBomb will send SMS to 5559876543
```

### Key Takeaway

**The patron list file name IS the delivery method indicator:**

```
FILE NAME              CONTAINS                    RESULTS IN
─────────────────────────────────────────────────────────────────
voice_patrons*.txt  → Patron Barcode + Phone  → delivery_option_id = 3 (Voice Calls)
text_patrons*.txt   → Patron Barcode + Phone  → delivery_option_id = 8 (SMS Text)
```

**Implementation Rule:**
```
IF patron_barcode IN voice_patrons*.txt THEN
    delivery_option_id = 3
    delivery_method = 'voice'
    ShoutBomb action = Phone call
    
ELSE IF patron_barcode IN text_patrons*.txt THEN
    delivery_option_id = 8
    delivery_method = 'sms'
    ShoutBomb action = Text message
    
ELSE
    delivery_option_id = NULL
    (Patron uses email or mail, not phone/text)
END IF
```

---

## USAGE EXAMPLES

### Example 1: Complete Hold Notification Lifecycle

```sql
-- Track a hold from queue to delivery/failure
SELECT 
    pn.patron_barcode,
    pn.name_first, pn.name_last,
    pn.browse_title,
    pn.import_date as queued_date,
    h.export_timestamp as sent_date,
    nfr.received_at as failure_date,
    CASE 
        WHEN nfr.id IS NOT NULL THEN 'FAILED'
        WHEN h.id IS NOT NULL THEN 'DELIVERED'
        ELSE 'QUEUED'
    END as status,
    nfr.failure_type,
    nfr.failure_reason
FROM polaris_phone_notices pn
LEFT JOIN notifications_holds h 
    ON pn.sys_hold_request_id = h.sys_hold_request_id
LEFT JOIN notice_failure_reports nfr 
    ON pn.id = nfr.phone_notices_import_id
WHERE pn.patron_barcode = '23307000001234'
  AND pn.notification_type_id = 2;
```

**Data Sources Used:**
- PhoneNotices.csv → patron info, title, queued date
- holds.txt → sent date
- Invalid Phone / Voice Failed email → failure info

### Example 2: Monthly Success Rate Calculation

```sql
-- Calculate success rate for November 2025
SELECT 
    rt.type_name,
    COUNT(DISTINCT pn.id) as total_queued,
    COUNT(DISTINCT nfr.id) as total_failed,
    COUNT(DISTINCT pn.id) - COUNT(DISTINCT nfr.id) as total_delivered,
    ROUND((COUNT(DISTINCT pn.id) - COUNT(DISTINCT nfr.id)) * 100.0 / 
          COUNT(DISTINCT pn.id), 2) as success_rate_pct
FROM polaris_phone_notices pn
INNER JOIN ref_notification_types rt 
    ON pn.notification_type_id = rt.notification_type_id
LEFT JOIN notice_failure_reports nfr 
    ON pn.id = nfr.phone_notices_import_id
WHERE pn.import_date >= '2025-11-01'
  AND pn.import_date < '2025-12-01'
  AND pn.delivery_option_id IN (3, 8)
GROUP BY pn.notification_type_id, rt.type_name;
```

**Data Sources Used:**
- PhoneNotices.csv → total queued
- Invalid Phone / Voice Failed emails → failures
- Calculated → success rate

---

## RECOMMENDATIONS

### High Priority
1. ✅ **Document weekly report parsing** - Add if you use these for stats
2. ✅ **Clarify SMS failure reporting** - Is this separate from "Invalid Phone"?
3. ✅ **Add patron registration tracking** - If available beyond monthly stats

### Medium Priority
4. **Build enrichment service** - Automatically link failures to exports
5. **Create validation reports** - Flag missing expected data
6. **Set up automated imports** - Daily cron jobs for all sources

### Low Priority
7. **Archive old emails** - Move processed emails to folder
8. **Monitor for new report types** - ShoutBomb may add new reports

---

**Document Version:** 1.0  
**Last Updated:** November 22, 2025  
**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org)

---

## QUESTIONS FOR BRIAN

1. **"Calls that generated errors"** - Is this a separate email or same as "Voice notices not delivered"?

2. **SMS delivery failures** - Are these only in "Invalid Phone" report or is there a separate "SMS not delivered" email?

3. **Weekly reports** - Do you want to import/track these separately or are monthly stats sufficient?

4. **Patron keyword responses** - Can we get individual response logs or only monthly aggregates?

5. **Hold cancellations** - Is there a separate report for cancelled holds?

6. **Patron registration** - Do we get notifications when patrons sign up, or only monthly totals?
