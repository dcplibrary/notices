# POLARIS NOTIFICATION TABLES - MASTER INDEX

---

## OVERVIEW

This document provides a master index of all Polaris tables involved in the notification system, showing how they relate to each other and to the Shoutbomb integration.

**Total Tables Documented:** 7

**Databases:**
- **Results** - Temporary/reporting data (NotificationQueue, HoldNotices, OverdueNotices, FineNotices, NotificationHistory)
- **PolarisTransactions** - Transaction logs (NotificationLog)
- **Polaris** - Master data (SysHoldRequests, Patrons, CircItemRecords)

---

## TABLE SUMMARY

| Table | Database | Purpose | Shoutbomb Export | Documentation |
|-------|----------|---------|------------------|---------------|
| NotificationQueue | Results.Polaris | Queue of pending notifications | Source data | TABLE_NotificationQueue.md |
| NotificationLog | PolarisTransactions.Polaris | Aggregate log of sent notifications | Status tracking | TABLE_NotificationLog.md |
| HoldNotices | Results.Polaris | Hold notification details | Via holds.txt | TABLE_HoldNotices.md |
| OverdueNotices | Results.Polaris | Overdue notification details | Via overdue.txt | *(Create)* |
| FineNotices | Results.Polaris | Fine notification details | Via overdue.txt | *(Create)* |
| SysHoldRequests | Polaris.Polaris | Hold request master | Joined for metadata | *(Documented)* |
| NotificationHistory | Results.Polaris | Individual notification history | Reporting | *(Query only)* |

---

## DATA FLOW DIAGRAM

```
┌─────────────────────────────────────────────────────────────────┐
│ POLARIS ILS - NOTIFICATION CREATION                             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ Results.Polaris.NotificationQueue                               │
│ • Pending notifications to be sent                              │
│ • Filtered by DeliveryOptionID (3=Voice, 8=SMS)                │
│ • Linked to PatronID and ItemRecordID                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                 ┌────────────┴────────────┐
                 │                         │
                 ▼                         ▼
┌──────────────────────────┐  ┌──────────────────────────┐
│ Results.Polaris.         │  │ Results.Polaris.         │
│ HoldNotices              │  │ OverdueNotices           │
│                          │  │                          │
│ • Item/patron details    │  │ • Item/patron details    │
│ • BrowseTitle            │  │ • DueDate                │
│ • HoldTillDate           │  │ • Overdue charges        │
│ • Pickup location        │  │ • BillingNotice flag     │
└──────────────────────────┘  └──────────────────────────┘
                 │                         │
                 └────────────┬────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ SQL EXPORT QUERIES (holds.sql, overdue.sql)                    │
│ • Joins Queue + Detail tables                                   │
│ • Filters: DeliveryOptionID IN (3, 8)                          │
│ • Formats: Pipe-delimited text                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ SHOUTBOMB FTP SERVER                                            │
│ • holds.txt (4x daily: 8am, 9am, 1pm, 5pm)                     │
│ • overdue.txt (1x daily: 8:04am)                               │
│ • text_patrons.txt, voice_patrons.txt (1x daily: 2am)         │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ SHOUTBOMB PROCESSING                                            │
│ • Sends SMS/Voice notifications                                 │
│ • Generates failure reports                                     │
│ • Returns delivery status                                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ MANUAL STATUS UPDATE (Current)                                  │
│ • Review failure reports                                        │
│ • Contact patrons about failed notifications                    │
│ • Update patron records if needed                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                 ┌────────────┴────────────┐
                 │                         │
                 ▼                         ▼
┌──────────────────────────┐  ┌──────────────────────────┐
│ PolarisTransactions.     │  │ Results.Polaris.         │
│ Polaris.NotificationLog  │  │ NotificationHistory      │
│                          │  │                          │
│ • Aggregate record       │  │ • Individual record      │
│ • One per patron contact │  │ • One per item/patron    │
│ • Delivery status        │  │ • Complete audit trail   │
└──────────────────────────┘  └──────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ FUTURE ENHANCEMENT: Polaris API Integration                    │
│ • Automatic status updates via NotificationUpdatePut endpoint   │
│ • Real-time failure tracking                                    │
│ • Eliminates manual status review                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## TABLE RELATIONSHIPS

### Core Queue System

```
NotificationQueue (Results.Polaris)
    ├── PatronID → Patrons (Polaris.Polaris)
    ├── ItemRecordID → CircItemRecords (Polaris.Polaris)
    ├── NotificationTypeID → NotificationTypes (Polaris.Polaris)
    └── DeliveryOptionID → SA_DeliveryOptions (Polaris.Polaris)
```

### Hold Notifications

```
NotificationQueue
    └── JOIN → HoldNotices (Results.Polaris)
              ON ItemRecordID, PatronID, NotificationTypeID
        └── JOIN → SysHoldRequests (Polaris.Polaris)
                  ON PatronID, TrappingItemRecordID
```

### Overdue Notifications

```
NotificationQueue
    └── JOIN → OverdueNotices (Results.Polaris)
              ON ItemRecordID, PatronID
        └── JOIN → ItemCheckouts (Polaris.Polaris)
                  ON ItemRecordID
```

### Fine Notifications

```
NotificationQueue
    └── JOIN → FineNotices (Results.Polaris)
              ON PatronID, NotificationTypeID
```

### Logging/History

```
NotificationLog (PolarisTransactions.Polaris)
    ├── PatronID → Patrons (Polaris.Polaris)
    ├── NotificationTypeID → NotificationTypes (Polaris.Polaris)
    └── DeliveryOptionID → SA_DeliveryOptions (Polaris.Polaris)

NotificationHistory (Results.Polaris)
    ├── PatronID → Patrons (Polaris.Polaris)
    ├── ItemRecordID → CircItemRecords (Polaris.Polaris)
    └── NotificationTypeID → NotificationTypes (Polaris.Polaris)
```

---

## KEY FIELDS ACROSS TABLES

### Common Identifiers

| Field | Data Type | Found In | Purpose |
|-------|-----------|----------|---------|
| PatronID | int | ALL tables | Internal patron identifier |
| ItemRecordID | int | Queue, HoldNotices, OverdueNotices, History | Internal item identifier |
| NotificationTypeID | int | ALL tables | Type of notification (2=Hold, 1=Overdue, etc.) |
| DeliveryOptionID | int | ALL tables | Delivery method (1=Mail, 3=Voice, 8=SMS, etc.) |
| NotificationStatusID | int | Log, History | Delivery status (3=Delivered SMS, 9=Delivered Voice, etc.) |

### Unique Identifiers

| Field | Data Type | Found In | Purpose |
|-------|-----------|----------|---------|
| NotificationQueueID | int IDENTITY | NotificationQueue | Queue record primary key |
| NotificationLogID | int IDENTITY | NotificationLog | Log record primary key |
| SysHoldRequestID | int IDENTITY | SysHoldRequests | Hold request primary key |
| OverdueNoticeID | int IDENTITY | OverdueNotices | Overdue notice primary key |

### Display/Contact Fields

| Field | Data Type | Found In | Purpose |
|-------|-----------|----------|---------|
| PatronBarcode | nvarchar(20) | NotificationLog, Patrons | Patron-visible library card number |
| ItemBarcode | nvarchar(20) | HoldNotices, OverdueNotices | Item-visible barcode |
| DeliveryString | nvarchar(255) | NotificationLog | Phone number or email address |
| BrowseTitle | nvarchar(255) | HoldNotices, OverdueNotices | Item title for display |

---

## CRITICAL FILTERING RULES

### Shoutbomb Exports (Voice & SMS Only)

```sql
-- ALL Shoutbomb exports must include this filter:
WHERE (DeliveryOptionID = 3 OR DeliveryOptionID = 8)

-- DeliveryOptionID = 3 → Voice calls
-- DeliveryOptionID = 8 → Text messages (SMS)
-- DeliveryOptionID = 1 → Mail (handled by Polaris, not Shoutbomb)
-- DeliveryOptionID = 2 → Email (handled by Polaris, not Shoutbomb)
```

### Hold Notifications

```sql
-- Must check HoldTillDate to exclude expired holds:
WHERE HoldTillDate > GETDATE()

-- NotificationTypeID is always 2 for holds
WHERE NotificationTypeID = 2
```

### Overdue Notifications

```sql
-- Only new notifications in last 24 hours:
WHERE CreationDate > DATEADD(day, -1, GETDATE())

-- NotificationTypeID for overdue types:
WHERE NotificationTypeID IN (1, 7, 8, 11, 12, 13)
-- 1 = 1st Overdue
-- 7 = Almost overdue/Auto-renew reminder
-- 8 = Fine
-- 11 = Bill
-- 12 = 2nd Overdue
-- 13 = 3rd Overdue
```

---

## TYPICAL CROSS-TABLE QUERIES

### Find All Notifications for a Patron

```sql
-- Get all pending notifications
SELECT 'Queue' as Source, NotificationTypeID, ItemRecordID, CreationDate
FROM Results.Polaris.NotificationQueue
WHERE PatronID = 200001

UNION ALL

-- Get all sent notifications (aggregate)
SELECT 'Log' as Source, NotificationTypeID, NULL as ItemRecordID, NotificationDateTime
FROM PolarisTransactions.Polaris.NotificationLog
WHERE PatronID = 200001

UNION ALL

-- Get all notification history (detail)
SELECT 'History' as Source, NotificationTypeID, ItemRecordID, NoticeDate
FROM Results.Polaris.NotificationHistory
WHERE PatronID = 200001

ORDER BY CreationDate DESC;
```

### Verify Notification Was Sent

```sql
-- Check if hold notification was queued
SELECT 'Queued' as Status, * 
FROM Results.Polaris.NotificationQueue
WHERE PatronID = 200001 AND ItemRecordID = 300001 AND NotificationTypeID = 2

UNION ALL

-- Check if it was sent
SELECT 'Sent' as Status, * 
FROM PolarisTransactions.Polaris.NotificationLog
WHERE PatronID = 200001 AND HoldsCount > 0

UNION ALL

-- Check detailed history
SELECT 'History' as Status, * 
FROM Results.Polaris.NotificationHistory
WHERE PatronID = 200001 AND ItemRecordID = 300001 AND NotificationTypeID = 2;
```

### Track Notification Success Rate

```sql
SELECT 
    nl.NotificationTypeID,
    nl.DeliveryOptionID,
    COUNT(*) as TotalSent,
    SUM(CASE WHEN nl.NotificationStatusID IN (3, 7, 9, 15) THEN 1 ELSE 0 END) as Successful,
    SUM(CASE WHEN nl.NotificationStatusID IN (4, 5, 6, 8, 10) THEN 1 ELSE 0 END) as Failed
FROM PolarisTransactions.Polaris.NotificationLog nl
WHERE nl.NotificationDateTime >= DATEADD(day, -30, GETDATE())
GROUP BY nl.NotificationTypeID, nl.DeliveryOptionID;
```

---

## DOCUMENTATION FILES

| File | Purpose |
|------|---------|
| **TABLE_NotificationQueue.md** | Results.Polaris.NotificationQueue - Pending notifications queue |
| **TABLE_NotificationLog.md** | PolarisTransactions.Polaris.NotificationLog - Sent notification log |
| **TABLE_HoldNotices.md** | Results.Polaris.HoldNotices - Hold notification details |
| **SHOUTBOMB_HOLDS_EXPORT.md** | Hold export file format and SQL query |
| **SHOUTBOMB_OVERDUE_EXPORT.md** | Overdue export file format and SQL query |
| **SHOUTBOMB_DOCUMENTATION_INDEX.md** | Master index of all Shoutbomb documentation |
| **Polaris_Notification_Guide_PAPIClient.md** | Polaris API integration guide |
| **Polaris-API-swagger.json** | Polaris API specification |

---

## QUICK REFERENCE

### Most Important Tables for Troubleshooting

1. **NotificationQueue** - Check if notification was queued
2. **HoldNotices/OverdueNotices** - Get item details
3. **NotificationLog** - Check if notification was sent and status
4. **NotificationHistory** - Get complete audit trail

### Most Common Patron Issues

| Patron Says | Check These Tables | Look For |
|-------------|-------------------|----------|
| "I didn't get my hold notification" | NotificationQueue → HoldNotices → NotificationLog | Verify queued, check DeliveryOptionID, check NotificationStatusID |
| "I didn't get my overdue notice" | NotificationQueue → OverdueNotices → NotificationLog | Check CreationDate filter, verify DeliveryOptionID |
| "My phone number is wrong" | Patrons table | Update PhoneVoice1 field |
| "I want text instead of voice" | Patrons.DeliveryOptionID | Change from 3 to 8 |

---

## NEXT STEPS

**Immediate:**
1. Review TABLE_NotificationQueue.md for queue structure
2. Review TABLE_NotificationLog.md for logging structure
3. Review TABLE_HoldNotices.md for hold details

**Future Enhancements:**
1. Create TABLE_OverdueNotices.md documentation
2. Create TABLE_FineNotices.md documentation
3. Implement Polaris API integration for automatic status updates
4. Build web-based notification verification tool

---

## CONTACT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org)

**Last Updated:** 2025-11-19
