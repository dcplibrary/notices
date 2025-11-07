# Comprehensive Data Generation Plan
## Interconnected, Realistic Polaris Notification Sample Data

Based on analysis of existing documentation and data structure.

---

## Goals

1. **Cross-Referenced Data**: Patrons with multiple holds/overdues to verify notification system
2. **Realistic Timelines**: 30-day window (20 days past, 10 days future from today)
3. **Time Correlation**: <60 minute variance for related notifications
4. **Verifiable Flow**: Can trace notification from source → queue → history → logs
5. **Privacy**: Fake PII but keep reference data real

---

## Patron Scenarios (25 patrons total)

### High-Volume Users (3 patrons)
1. **Patron A**: 7 overdue items, varying due dates, email delivery
2. **Patron B**: 5 overdue items, same due date (forgot about them), SMS delivery
3. **Patron C**: 4 overdue items, mix of due dates, email delivery

### Multi-Hold Users (3 patrons)
4. **Patron D**: 3 holds on same topic (e.g., Mozart books), all ready same day
5. **Patron E**: 2 holds, different pickup dates
6. **Patron F**: 2 holds, ready same day

### Mixed Activity (4 patrons)
7. **Patron G**: 2 holds + 1 overdue, email delivery
8. **Patron H**: 1 hold + 2 overdues, SMS delivery
9. **Patron I**: 3 holds + 1 overdue, voice delivery
10. **Patron J**: 1 hold + 3 overdues, email delivery

### Single Item Users (10 patrons)
11-20. **Patrons K-T**: Each with 1 hold OR 1 overdue, mix of delivery methods

### No Current Activity (5 patrons)
21-25. **Patrons U-Y**: In system but no current holds/overdues (historical data only)

---

## Date/Time Strategy

### Current Date Reference
- **Today**: Use system date as baseline
- **Past Window**: Today - 20 days
- **Future Window**: Today + 10 days

### Due Dates (Overdues)
Distribute across past 20 days:
- Days 1-7 overdue: First notice territory
- Days 8-14 overdue: Second notice territory
- Days 15+ overdue: Billing territory

### HoldTillDate (Holds)
Distribute across future 10 days:
- Next 1-3 days: Urgent pickups
- Days 4-7: Standard holds
- Days 8-10: Extended holds

### Notification Batch Times
**Hourly batches** at:
- 09:01, 10:01, 11:01, 12:01, 13:01, 14:01, 15:01, 16:01, 17:01

**Within-batch variance**:
- Same patron, multiple items: ±0.1-2 seconds
- Different patrons, same batch: ±10-59 seconds

### Date/Time Correlations
```
SysHoldRequests.HoldNotificationDate = 2025-11-06 14:00:00
  → NotificationHistory.NoticeDate    = 2025-11-06 14:01:15  (+1m 15s)
  → NotificationLogs.NotificationDateTime = 2025-11-06 14:01:15 (same)
```

```
Item DueDate = 2025-10-15 23:59:59
  → OverdueNotice generated = 2025-10-16 02:00:00 (overnight processing)
  → NoticeDate = 2025-10-16 10:01:23 (morning batch)
```

---

## Data Linkages

### Patron Data Chain
```
Patrons (PatronID, Barcode)
  → PatronRegistration (EmailAddress, PhoneVoice1, DeliveryOptionID)
    → HoldNotices (PatronID, DeliveryOptionID)
      → NotificationHistory (PatronId, NoticeDate)
        → NotificationLogs (PatronID, DeliveryString=EmailAddress)
```

**Requirements**:
- If PatronRegistration.DeliveryOptionID = 2 (Email), must have EmailAddress
- If PatronRegistration.DeliveryOptionID = 8 (SMS), must have PhoneVoice1
- NotificationLogs.DeliveryString must match the actual email/phone used

### Item Data Chain
```
CircReserveItemRecords_View (ItemRecordID, Barcode, BrowseTitle)
  → SysHoldRequests (TrappingItemRecordID, HoldNotificationDate)
    → HoldNotices (ItemRecordID, ItemBarcode, HoldTillDate)
      → NotificationHistory (ItemRecordId, NoticeDate)
```

### Multi-Item Notification Pattern
Patron with 3 holds ready same day:
```sql
-- All three notifications sent within seconds
NotificationHistory:
PatronId=121394, ItemRecordId=193755, NoticeDate=2025-11-04 14:01:17.0
PatronId=121394, ItemRecordId=793939, NoticeDate=2025-11-04 14:01:17.0
PatronId=121394, ItemRecordId=148279, NoticeDate=2025-11-04 14:01:17.0

-- Single log entry combining all three
NotificationLogs:
PatronID=121394, NotificationDateTime=2025-11-04 14:01:17.0, HoldsCount=3
```

---

## Reference Data (Keep Real)

✅ **DO NOT MODIFY**:
- DeliveryOptions (IDs 1-8)
- NotificationStatuses (IDs 1-16)
- NotificationTypes (IDs 0-21)
- SysHoldStatuses (IDs 1-18)
- ItemStatuses
- AddressTypes
- TransactionTypes/SubTypes
- Cities, States, PostalCodes (can reuse)

---

## PII Data (Generate Fake)

⚠️ **GENERATE REALISTIC BUT FAKE**:

### Names
- Use Faker library
- Mix of common and uncommon names
- Realistic middle names (some blank)
- Proper capitalization

### Email Addresses
Format: `{firstname}.{lastname}@{provider}`
Providers: gmail.com, yahoo.com, hotmail.com, outlook.com, icloud.com

**Must match** PatronRegistration.EmailAddress = NotificationLogs.DeliveryString (when DeliveryOptionID=2)

### Phone Numbers
Format: `270#######` (keep 270 area code for Owensboro, KY)

**Must match** PatronRegistration.PhoneVoice1 = NotificationLogs.DeliveryString (when DeliveryOptionID=8)

### Street Addresses
- Use Faker for realistic addresses
- Can reuse city/state/postal codes
- Keep PostalCodeID consistent

### Barcodes
**Patron**: `233070##########` (14 digits total)
**Item**: `333070##########` (14 digits total)

Generate sequentially but maintain uniqueness.

### Passwords
- Generate realistic bcrypt hashes: `$2a$10${53 random chars}`
- Generate obfuscated passwords: `{22 random chars}==`

---

## Delivery Methods & Integration

### Email Delivery (DeliveryOptionID = 2)
- Delivered directly through Polaris SMTP
- Logged in NotificationLogs with DeliveryString = EmailAddress
- NotificationStatusID = 12 (Email Completed) or 13/14 (Email Failed)

### SMS/Text Delivery (DeliveryOptionID = 8)
- **Uses CUSTOM Shoutbomb integration** (not standard Polaris)
- Exported via `shoutbomb.bat text_patrons` and `shoutbomb.bat holds/overdue/renew`
- Appears in PhoneNotices.csv with field 1 = "T"
- Phone number in PatronRegistration.PhoneVoice1
- May use SMS gateway format: `2703149477@carrier.domain`
- Logged in NotificationLogs with DeliveryString = phone number
- NotificationStatusID = 16 (Sent) or failure codes

### Voice Call Delivery (DeliveryOptionID = 3)
- **Uses CUSTOM Shoutbomb integration** (not standard Polaris)
- Exported via `shoutbomb.bat voice_patrons` and `shoutbomb.bat holds/overdue`
- Appears in PhoneNotices.csv with field 1 = "V"
- Phone number in PatronRegistration.PhoneVoice1
- Logged in NotificationLogs with DeliveryString = phone number
- NotificationStatusID = 1 (Call completed - Voice) or 2 (Answering machine) or failure codes

### Mail Delivery (DeliveryOptionID = 1)
- Physical mail printed by Polaris
- Logged in NotificationLogs
- NotificationStatusID = 15 (Mail Printed)

**Important**: When generating data, ensure patrons with DeliveryOptionID 3 or 8 appear correctly in PhoneNotices.csv export format.

---

## Item/Title Data

### Book Titles (generate ~50 books)
Mix of:
- Fiction (F {Author})
- Juvenile Fiction (JF {Author})
- Non-fiction (Dewey number)
- DVDs (DVD {Genre} {Title})
- Video Games (platform code)

**Author names**: Use Faker or real author-like names
**Call numbers**: Match format to type
**Prices**: Realistic range ($7.99 - $39.99)

---

## Table Generation Order

### Phase 1: Foundation (Patrons)
1. **Patrons** (25 records)
   - Generate PatronIDs (sequential or realistic IDs)
   - Generate Barcodes (233070##########)
   - Set realistic circulation counts

2. **PatronRegistration** (25 records, 1:1 with Patrons)
   - Generate names (First, Middle, Last)
   - Generate emails (some blank for mail/SMS users)
   - Generate phones (some blank for email-only users)
   - Assign DeliveryOptionID distribution:
     - 60% Email (2)
     - 20% SMS (8)
     - 10% Voice (3)
     - 10% Mail (1)

3. **Addresses** (15-20 records, shared across some patrons)
   - Generate fake street addresses
   - Reuse cities/postal codes

4. **PatronAddresses** (25 records, link patrons to addresses)

### Phase 2: Items
5. **CircReserveItemRecords_View** (100 records)
   - Generate ItemRecordIDs
   - Generate ItemBarcodes (333070##########)
   - Generate titles, authors, call numbers
   - Set prices

### Phase 3: Holds
6. **SysHoldRequests** (20 hold requests)
   - Link to patrons with holds
   - Set HoldNotificationDate (within date window)
   - Set HoldTillDate (future dates)
   - Set SysHoldStatusID = 6 (Held - on shelf)
   - Set DeliveryOptionID (matches patron preference)

7. **HoldNotices** (20 records, 1:1 with SysHoldRequests)
   - Copy data from SysHoldRequests
   - Add item details from CircReserveItemRecords_View
   - NotificationTypeID = 2 (Hold)

8. **SysHoldHistory** & **SysHoldHistoryDaily**
   - Generate history for holds

### Phase 4: Overdues
9. **NotificationQueue** (Overdue section)
   - Link to patrons with overdues
   - Set DueDates (past dates)
   - Set NotificationTypeID = 1 (1st Overdue)

10. **ViewOverdueNoticesData** (30 overdue items)
    - Link to patrons with overdues
    - Set DueDates in past
    - Set item details

### Phase 5: Notification Logs
11. **NotificationHistory** (50+ records)
    - For each hold: PatronId, ItemRecordId, NoticeDate, NotificationTypeId=2, NotificationStatusId=12
    - For each overdue: PatronId, ItemRecordId, NoticeDate, NotificationTypeId=1, NotificationStatusId=12
    - Set NoticeDates with proper correlation to source dates
    - Batch times: hourly intervals
    - Same patron multiple items: same or ±seconds

12. **NotificationLogs** (30+ records, may be fewer than History due to combining)
    - For each patron: create 1 log entry per batch
    - Set HoldsCount, OverduesCount based on items in that batch
    - DeliveryString = actual email or phone from PatronRegistration
    - NotificationStatusID = 12 (Email Completed) or 1 (Call completed - Voice)

### Phase 6: Other Tables
13. **FineNotices** & **FineNoticesReport** (5-10 patrons with fines)
14. **ManualBillNotices** (few records)
15. **CircReminders** (large volume, 100+ records)

### Phase 7: Shoutbomb Integration Files
16. **PhoneNotices.csv** (Voice/SMS export for Shoutbomb)
    - IMPORTANT: This uses the CUSTOM shoutbomb integration (not standard Polaris export)
    - Format: Comma-delimited with specific field order
    - Contains only DeliveryOptionID 3 (Voice) and 8 (SMS) notifications
    - Field 1: "V" (Voice) or "T" (Text/SMS)
    - Includes PatronBarcode, Name, Phone, Title, HoldTillDate/DueDate
    - Generated from custom SQL queries in shoutbomb/sql/ folder

---

## Verification Tests

After generation, verify:

### Test 1: Patron with Multiple Holds
```sql
-- Find patron with 3 holds
SELECT PatronID, COUNT(*) as HoldCount
FROM Results.Polaris.HoldNotices
GROUP BY PatronID
HAVING COUNT(*) >= 3;

-- Verify all 3 appear in NotificationHistory
SELECT PatronId, ItemRecordId, NoticeDate
FROM Results.Polaris.NotificationHistory
WHERE PatronId = ? AND NotificationTypeId = 2;

-- Verify combined in NotificationLogs
SELECT PatronID, HoldsCount, NotificationDateTime, DeliveryString
FROM PolarisTransactions.Polaris.NotificationLogs
WHERE PatronID = ?;
```

### Test 2: Email Delivery Match
```sql
-- Verify email matches across tables
SELECT
    pr.EmailAddress as RegistrationEmail,
    nl.DeliveryString as LoggedEmail,
    CASE WHEN pr.EmailAddress = nl.DeliveryString THEN 'MATCH' ELSE 'MISMATCH' END as Status
FROM Polaris.Polaris.PatronRegistration pr
INNER JOIN PolarisTransactions.Polaris.NotificationLogs nl ON pr.PatronID = nl.PatronID
WHERE pr.DeliveryOptionID = 2  -- Email delivery
    AND nl.DeliveryOptionID = 2;
```

### Test 3: Date/Time Correlation
```sql
-- Verify hold notification timing
SELECT
    shr.HoldNotificationDate as SourceDate,
    nh.NoticeDate as SentDate,
    DATEDIFF(MINUTE, shr.HoldNotificationDate, nh.NoticeDate) as MinutesDifference
FROM Polaris.Polaris.SysHoldRequests shr
INNER JOIN Results.Polaris.NotificationHistory nh
    ON shr.PatronID = nh.PatronId
    AND shr.TrappingItemRecordID = nh.ItemRecordId
WHERE nh.NotificationTypeId = 2;

-- Should all be < 60 minutes
```

### Test 4: Cross-Reference Integrity
```sql
-- Verify all PatronIDs exist in Patrons table
SELECT DISTINCT hn.PatronID
FROM Results.Polaris.HoldNotices hn
LEFT JOIN Polaris.Polaris.Patrons p ON hn.PatronID = p.PatronID
WHERE p.PatronID IS NULL;

-- Should return 0 rows
```

---

## Implementation Strategy

### Script Design
1. **Use object-oriented approach** with classes for:
   - Patron
   - Item
   - Hold
   - Notification
   - TimeScheduler (manages batch times)

2. **Generate in phases** (as outlined above)

3. **Maintain state** in dictionaries:
   - `patron_registry`: PatronID → Patron object (with email, phone, etc.)
   - `item_registry`: ItemRecordID → Item object
   - `notification_schedule`: Batch time → List of notifications

4. **CSV output** matching exact format of original files

### Libraries
- `faker`: Generate realistic names, addresses, emails
- `datetime`: Date/time manipulation
- `random`: Controlled randomization (with seed)
- `csv`: CSV file handling

### Seed for Reproducibility
Use `random.seed(42)` and `Faker.seed(42)` for reproducible results.

---

## Success Criteria

✅ Can trace a patron through complete notification flow
✅ Multi-item patrons correctly show combined/separate notifications
✅ All email/phone delivery strings match patron registration
✅ All dates within 30-day window
✅ Related dates correlated within 60 minutes
✅ No orphaned records (all FKs valid)
✅ Realistic distribution of delivery methods
✅ Mix of notification types and scenarios

---

## Next Steps

1. Implement comprehensive data generation script
2. Generate all CSV files
3. Run verification tests
4. Review sample data for realism
5. Commit and document changes
