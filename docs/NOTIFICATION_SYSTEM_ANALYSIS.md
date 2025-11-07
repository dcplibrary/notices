# Polaris Notification System - Complete Data Flow

## My Understanding of the System

Yes, I now understand how notifications work! Here's the complete picture:

### Notification Flow

```
SOURCE TABLES           QUEUE TABLES              LOG TABLES
--------------          --------------            -----------
SysHoldRequests    →    HoldNotices         →    NotificationHistory
ViewOverdueData    →    NotificationQueue   →    NotificationLogs
PatronFineNotices  →    FineNotices         →
```

### Key Patterns I Found:

#### 1. **Cross-Referenced Patrons** (Multiple Items)
- **Patron 47738**: 2 holds (Items 35108, 204586) - both for pickup by 11/8
- **Patron 68686**: 2 holds (Items 108608, 189803)
- **Patron 121394**: 3 holds on Mozart-themed books! (crossreferenced by topic)
- **Patron 41613**: 4 overdue items (different due dates: 10/7, 10/28)
- **Patron 42475**: 5 overdue items (all due 10/29)
- **Patron 21**: 7 overdue items (varying due dates)
- **Patron 23454**: 2 hold notifications sent on same day

#### 2. **Date/Time Patterns**

**Batch Processing Times:**
- Notifications sent hourly: 10:01, 11:01, 12:01, 13:01, 14:01, 15:01, 16:01, 17:01
- Within each batch, variance is <60 seconds
- Example: Patron 23454's two items sent at:
  - 14:01:11.5 (Item 886627)
  - 14:01:11.4 (Item 882373) - **0.1 second apart!**

**Notification Timing:**
```
Hold Ready → HoldNotificationDate set → Batch process runs → Email sent (same hour)
Item Overdue → Added to queue → Next day batch → Email sent
```

#### 3. **Delivery Methods** (DeliveryOptionID)
- **1** = Mailing Address
- **2** = Email Address (most common)
- **3** = Phone 1
- **8** = TXT Messaging

**Critical Relationship:**
```
PatronRegistration.DeliveryOptionID
    ↓
HoldNotices/NotificationQueue.DeliveryOptionID
    ↓
NotificationLogs.DeliveryString (actual email/phone)
```

#### 4. **Notification States**

**Queue → History Flow:**
- **NotificationQueue**: Items waiting to be sent (overdue notices)
- **HoldNotices**: Holds ready for pickup waiting to be sent
- **NotificationHistory**: Successfully sent notifications
- **NotificationLogs**: Transaction log of send attempts

**Status Codes:**
- **12** = Email Completed (success)
- **13** = Email Failed - Invalid address
- **14** = Email Failed (general)
- **16** = Sent (generic)

### How to Verify Notifications Were Sent Correctly

#### Verification Path for HOLD Notices:

1. **Check Source**: Does `SysHoldRequests` have the hold?
   - PatronID, ItemBarcode, SysHoldStatusID = 6 (Held)
   - HoldNotificationDate populated

2. **Check Queue**: Is it in `Results.HoldNotices`?
   - Same PatronID, ItemRecordID
   - DeliveryOptionID matches patron preference

3. **Check Logs**: Is it in `Results.NotificationHistory`?
   - Same PatronID, ItemRecordID
   - NoticeDate within ~60 min of HoldNotificationDate
   - NotificationStatusID = 12 (success)
   - DeliveryOptionID matches

4. **Verify Email**: Check `NotificationLogs`
   - DeliveryString = patron's actual email
   - NotificationDateTime matches NoticeDate
   - HoldsCount = number of holds in that notification

#### Verification Path for OVERDUE Notices:

1. **Check Source**: Is it in `ViewOverdueNoticesData`?
   - DueDate is in the past
   - PatronID, ItemRecordID

2. **Check Queue**: Is it in `Results.NotificationQueue`?
   - Same PatronID, ItemRecordID
   - NotificationTypeID = 1 (1st Overdue)
   - OverdueNoticeID (sequence number)

3. **Check History**: Was it sent?
   - Appears in NotificationHistory
   - NoticeDate is day after DueDate (typically)
   - Times show batch processing (hourly)

### Data Interconnections to Maintain

#### Must Match Across Tables:

1. **PatronID** → Same patron in:
   - Patrons (basic info)
   - PatronRegistration (PII: name, email, phone)
   - HoldNotices/NotificationQueue (what they're being notified about)
   - NotificationHistory/Logs (proof notification sent)

2. **ItemRecordID + ItemBarcode** → Same item in:
   - HoldNotices (the hold notice)
   - NotificationHistory (the sent notification)
   - CircReserveItemRecords_View (circulation data)

3. **DeliveryOptionID** → Must match:
   - PatronRegistration (patron preference - determines notification method)
   - HoldNotices/Queue (how to send)
   - NotificationHistory (how it was sent)
   - DeliveryString relationship:
     * If DeliveryOptionID = 2 (Email), DeliveryString = PatronRegistration.EmailAddress
     * If DeliveryOptionID = 3 or 8 (Voice/SMS), DeliveryString = PatronRegistration.PhoneVoice1
     * If DeliveryOptionID = 1 (Mail), DeliveryString = empty (uses physical address)

4. **Dates with <60 min correlation:**
   - SysHoldRequests.HoldNotificationDate
   - NotificationHistory.NoticeDate (for that hold)
   - NotificationLogs.NotificationDateTime
   - All should be within same hour, often within seconds

5. **Multi-Item Notifications:**
   - If Patron has multiple holds ready same day
   - Can be sent as ONE notification (NotificationLogs.HoldsCount > 1)
   - OR separate notifications (multiple entries in NotificationHistory)
   - All within same batch window (<60 seconds apart)

### Reference Data to Keep Real:

✅ Keep unchanged:
- DeliveryOptions (1-8)
- NotificationStatuses (1-16)
- NotificationTypes (0-23)
- SysHoldStatuses (1-18)
- AddressTypes
- TransactionTypes/SubTypes
- ItemStatuses
- City, State, PostalCodes

### PII Data to Fake (but keep interconnected):

⚠️ Generate fake but maintain relationships:
- PatronRegistration: NameFirst, NameLast, NameMiddle, EmailAddress, Phone1/2/3
- Addresses: StreetOne, StreetTwo, StreetThree
- Patrons: Barcode (format: 233070##########)
- NotificationLogs: DeliveryString (must match patron's email if DeliveryOptionID=2)
- All patron-related names in view tables

### Date/Time Requirements:

- **Window**: 30 days total (20 days past, 10 days future from today)
- **DueDates**: Spread across past 20 days
- **HoldTillDates**: Spread across next 10 days
- **NoticeDate**: Within 60 min of source date (HoldNotificationDate or day after DueDate)
- **Batch times**: Use hourly batches (10:00, 11:00, 12:00...) with seconds variance
- **Same patron, multiple items**: Send within same minute

## What This Lets You Verify:

### Successful Notification Flow:
✅ Patron has email → Hold ready → Notice generated → Email sent (Status=12) → Email address matches

### Failed Notification:
❌ Patron has no email → Hold ready → Notice generated → Send fails (Status=13/14)

### Multi-Item Scenarios:
✅ Patron 41613 has 4 overdues → All in NotificationQueue → Should get 1 combined email → NotificationLogs.OverduesCount = 4

### Missing Notifications:
❌ Hold in SysHoldRequests → NOT in HoldNotices → Problem with queue generation
❌ In HoldNotices → NOT in NotificationHistory → Problem with sending process

## Next Steps:

I now understand the complete flow and can regenerate the data with:
1. Proper cross-references (patrons with multiple items)
2. Correct date/time correlations (<60 min variance)
3. Matching DeliveryOptionID and DeliveryString
4. Realistic batch processing times
5. All relationships intact for verification

Should I proceed with redesigning the data generation script?
