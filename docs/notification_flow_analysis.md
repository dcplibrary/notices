# Polaris Notification System - Data Flow Analysis

## Data Flow for Notifications

### 1. SOURCE DATA (What triggers notifications)

#### A. HOLD Notifications (NotificationTypeID = 2)
**Flow:**
```
SysHoldRequests → ViewHoldNoticesData → Results.HoldNotices → NotificationHistory/NotificationLogs
```

**SysHoldRequests Table:**
- PatronID (who placed the hold)
- ItemBarcode (what item)
- HoldNotificationDate (when to send notification)
- ActivationDate, ExpirationDate, HoldTillDate
- DeliveryOptionID (how to notify: 1=Mail, 2=Email, 3=Phone1, 8=TXT)
- SysHoldStatusID (1=Inactive, 3=Active, 6=Held, etc.)

**Results.HoldNotices Table:**
- ItemRecordID (the specific item)
- PatronID (links to patron)
- ItemBarcode
- HoldTillDate (pickup deadline)
- DeliveryOptionID
- NotificationTypeID = 2 (Hold notice)

#### B. OVERDUE Notifications (NotificationTypeID = 1, 12, 13)
**Flow:**
```
ViewOverdueNoticesData → Results.NotificationQueue → NotificationHistory/NotificationLogs
```

**Results.NotificationQueue Table:**
- PatronID
- ItemRecordID
- DueDate (when it was due)
- NotificationTypeID = 1 (1st Overdue)
- OverdueNoticeID (sequence number)
- DeliveryOptionID

#### C. FINE Notifications (NotificationTypeID = 8)
**Flow:**
```
PatronFineNotices → ViewManualBillNoticesData → Results.FineNotices → NotificationHistory
```

### 2. NOTIFICATION LOGS (Tracking actual sends)

#### NotificationHistory (Results.Polaris.NotificationHistory)
- PatronId (who)
- ItemRecordId (what item - can be NULL for patron-level notices)
- NotificationTypeId (what type: 1=Overdue, 2=Hold, 8=Fine, 9=Inactive)
- NoticeDate (when notification was sent)
- DeliveryOptionId (how sent)
- NotificationStatusId (12=Email Complete, 13=Email Failed, etc.)
- Amount (for fines)

#### NotificationLogs (PolarisTransactions.Polaris.NotificationLogs)
- PatronID
- NotificationDateTime (when sent)
- NotificationTypeID
- DeliveryOptionID
- DeliveryString (actual email/phone used)
- HoldsCount, OverduesCount, BillsCount (how many items in this notification)
- NotificationStatusID

### 3. PATRON-ITEM CROSS-REFERENCES

From the data, I can see patrons with multiple items:

**HOLD Notices:**
- PatronID 47738: Items 35108, 204586 (2 holds)
- PatronID 68686: Items 108608, 189803 (2 holds)
- PatronID 121394: Items 193755, 793939, 148279 (3 holds on Mozart books!)

**OVERDUE Notices:**
- PatronID 41613: Items 387684, 512064, 704445, 811073 (4 overdue)
- PatronID 42475: Items 176805, 212689, 315245, 345521, 345557 (5 overdue)
- PatronID 21: 7 items overdue!
- PatronID 133: 3 items overdue

## Key Relationships to Maintain

### Date/Time Correlations (<60 min variance):
1. **SysHoldRequests.HoldNotificationDate** → **NotificationHistory.NoticeDate** (when hold notice sent)
2. **Items become available** → **HoldNotices generated** → **Notification sent** (same day, within hours)
3. **DueDate passed** → **OverdueNotices generated** → **Notification sent** (usually next day at specific time)

### ID Relationships:
1. **PatronID** → Links across all tables
2. **ItemRecordID** + **ItemBarcode** → Identifies specific items
3. **DeliveryOptionID** → Must match between source and logs
4. **NotificationTypeID** → Must match between source and logs

### Data Integrity:
1. If PatronID has email in PatronRegistration.EmailAddress, DeliveryOptionID should be 2 (Email)
2. If DeliveryOptionID = 2, NotificationLogs.DeliveryString should be the email
3. NotificationStatusID should be 12 (Email Complete) if successful, 14 (Email Failed) if failed
4. Multiple items for same patron can be in same notification (combined) or separate

## Questions to Verify:

1. Can one patron have both holds AND overdues at the same time?
2. Are notifications sent at specific times of day (batched)?
3. Do phone notifications use PhoneVoice1/2/3 from PatronRegistration?
4. What's the relationship between CircReserveItemRecords_View and other tables?
