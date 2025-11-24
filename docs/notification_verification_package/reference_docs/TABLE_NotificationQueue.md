# DATABASE TABLE: Results.Polaris.NotificationQueue

---

## TABLE METADATA

**Database:** Results

**Schema:** Polaris

**Table Name:** NotificationQueue

**Purpose:** Queue of pending notifications to be sent to patrons. This is the source table for hold, overdue, fine, and bill notifications that get exported to Shoutbomb. Records remain until processed/delivered.

**Primary Key:** NotificationQueueID (IDENTITY, auto-incrementing)

**Indexes:** Primary clustered index on NotificationQueueID

**Related Documentation:** 
- SHOUTBOMB_HOLDS_EXPORT.md (uses this table)
- SHOUTBOMB_OVERDUE_EXPORT.md (uses this table)
- Polaris_Notification_Guide_PAPIClient.md (API access)

---

## FIELD DEFINITIONS

| Field # | Field Name | Data Type | Nullable? | Default | Description |
|---------|------------|-----------|-----------|---------|-------------|
| 1 | ItemRecordID | int | YES | NULL | Internal Polaris item identifier. NULL for non-item notifications (fines, bills) |
| 2 | NotificationTypeID | int | NO | (required) | Type of notification (2=Hold, 1=1st Overdue, 8=Fine, etc.) |
| 3 | PatronID | int | NO | (required) | Internal Polaris patron identifier |
| 4 | DeliveryOptionID | int | NO | (required) | Delivery method (1=Mail, 2=Email, 3=Phone1/Voice, 8=Text) |
| 5 | Processed | bit | NO | 0 | Whether notification has been processed/sent |
| 6 | MinorPatronID | int | YES | NULL | For minor patrons linked to adult accounts |
| 7 | ReportingOrgID | int | YES | NULL | Branch/organization ID for reporting purposes |
| 8 | Amount | money | YES | NULL | Dollar amount for fine/bill notifications |
| 9 | CreationDate | datetime | YES | getdate() | When notification was queued |
| 10 | IsAdditionalTxt | bit | YES | 0 | Flag for additional text message recipients |
| 11 | NotificationQueueID | int | NO | IDENTITY | Primary key, auto-incrementing |

**Total Field Count:** 11

---

## LOOKUP TABLES

### NotificationTypeID Reference
| ID | Description | Used For |
|----|-------------|----------|
| 1 | 1st Overdue | Overdue items |
| 2 | Hold | Hold ready for pickup |
| 7 | Almost overdue/Auto-renew reminder | Pre-due courtesy |
| 8 | Fine | Outstanding fines |
| 11 | Bill | Outstanding bills |
| 12 | 2nd Overdue | Second overdue notice |
| 13 | 3rd Overdue | Third overdue notice |

### DeliveryOptionID Reference
| ID | Description | Shoutbomb Export |
|----|-------------|------------------|
| 1 | Mail (postcard) | No - handled by Polaris |
| 2 | Email | No - handled by Polaris |
| 3 | Phone1 (Voice) | Yes - holds.txt, overdue.txt |
| 8 | TXT Messaging | Yes - holds.txt, overdue.txt |

---

## SAMPLE DATA

```sql
-- Hold notification ready for pickup (Voice)
INSERT INTO Results.Polaris.NotificationQueue 
VALUES (300001, 2, 200001, 3, 0, NULL, 1, NULL, '2025-11-04 08:00:00', 0);

-- Overdue notification (Text)
INSERT INTO Results.Polaris.NotificationQueue 
VALUES (300002, 1, 200002, 8, 0, NULL, 1, NULL, '2025-11-04 08:00:00', 0);

-- Fine notification (Voice)
INSERT INTO Results.Polaris.NotificationQueue 
VALUES (NULL, 8, 200003, 3, 0, NULL, 1, 15.50, '2025-11-04 08:00:00', 0);

-- Bill notification (Text)
INSERT INTO Results.Polaris.NotificationQueue 
VALUES (NULL, 11, 200004, 8, 0, NULL, 1, 25.00, '2025-11-04 08:00:00', 0);
```

---

## CROSS-REFERENCE KEYS

| Field in THIS Table | Links to Table | Field in OTHER Table | Relationship |
|---------------------|----------------|---------------------|--------------|
| PatronID | Polaris.Polaris.Patrons | PatronID | Many:1 - One patron, many notifications |
| ItemRecordID | Polaris.Polaris.CircItemRecords | ItemRecordID | Many:1 - One item, many notifications |
| NotificationTypeID | Polaris.Polaris.NotificationTypes | NotificationTypeID | Many:1 - Lookup table |
| DeliveryOptionID | Polaris.Polaris.SA_DeliveryOptions | DeliveryOptionID | Many:1 - Lookup table |
| ReportingOrgID | Polaris.Polaris.Organizations | OrganizationID | Many:1 - Branch lookup |

---

## KNOWN QUIRKS

**Processed Flag Behavior:**
- Records with Processed = 0 are pending
- Records with Processed = 1 have been exported to Shoutbomb
- Records are NOT automatically deleted after processing
- Must be manually purged or moved to history

**ItemRecordID NULL Values:**
- NULL for fine/bill notifications (NotificationTypeID 8, 11)
- Required for hold/overdue notifications (NotificationTypeID 1, 2, 7, 12, 13)

**DeliveryOptionID Filtering:**
- Shoutbomb exports ONLY process DeliveryOptionID 3 (Voice) and 8 (Text)
- Mail (1) and Email (2) are handled by Polaris directly
- Query filters: `WHERE (DeliveryOptionID = 3 OR DeliveryOptionID = 8)`

**CreationDate Filtering:**
- Overdue exports filter: `WHERE CreationDate > DATEADD(day, -1, GETDATE())`
- Only new notifications in last 24 hours are exported
- Hold exports filter: `WHERE HoldTillDate > GETDATE()` (still valid holds)

**IsAdditionalTxt Flag:**
- Used for patrons who want SMS notifications sent to multiple phone numbers
- Rare usage in most library systems

---

## TYPICAL QUERIES

### Export Pending Hold Notifications (Shoutbomb)
```sql
SELECT 
    q.PatronID,
    q.ItemRecordID,
    q.NotificationTypeID,
    q.DeliveryOptionID,
    q.CreationDate
FROM Results.Polaris.NotificationQueue q
WHERE q.NotificationTypeID = 2  -- Hold ready
  AND (q.DeliveryOptionID = 3 OR q.DeliveryOptionID = 8)  -- Voice or Text
  AND q.Processed = 0;  -- Not yet sent
```

### Export Pending Overdue Notifications (Shoutbomb)
```sql
SELECT 
    q.PatronID,
    q.ItemRecordID,
    q.NotificationTypeID,
    q.DeliveryOptionID,
    q.CreationDate
FROM Results.Polaris.NotificationQueue q
WHERE q.NotificationTypeID IN (1, 7, 8, 11, 12, 13)  -- Overdue types
  AND (q.DeliveryOptionID = 3 OR q.DeliveryOptionID = 8)  -- Voice or Text
  AND q.CreationDate > DATEADD(day, -1, GETDATE())  -- Last 24 hours
  AND q.Processed = 0;  -- Not yet sent
```

### Count Pending Notifications by Type
```sql
SELECT 
    NotificationTypeID,
    DeliveryOptionID,
    COUNT(*) as PendingCount
FROM Results.Polaris.NotificationQueue
WHERE Processed = 0
GROUP BY NotificationTypeID, DeliveryOptionID
ORDER BY NotificationTypeID, DeliveryOptionID;
```

---

## VALIDATION RULES

**Data Integrity Checks:**
- [ ] PatronID must exist in Polaris.Polaris.Patrons
- [ ] ItemRecordID (if not NULL) must exist in Polaris.Polaris.CircItemRecords
- [ ] NotificationTypeID must be valid (1, 2, 7, 8, 11, 12, 13, etc.)
- [ ] DeliveryOptionID must be valid (1, 2, 3, 8)
- [ ] Processed must be 0 or 1
- [ ] Amount should only be populated for fine/bill notifications (types 8, 11)
- [ ] ItemRecordID should be NULL for fine/bill notifications
- [ ] CreationDate should not be in the future

**Business Logic Validation:**
- If NotificationTypeID = 2 (Hold), ItemRecordID should not be NULL
- If NotificationTypeID IN (1, 7, 12, 13) (Overdue), ItemRecordID should not be NULL
- If NotificationTypeID IN (8, 11) (Fine/Bill), ItemRecordID is typically NULL
- If Amount > 0, NotificationTypeID should be 8 or 11

---

## PROCESSING NOTES

**Record Lifecycle:**
1. Polaris creates notification record with Processed = 0
2. Scheduled export query reads unprocessed records
3. Records exported to Shoutbomb submission files
4. Records updated to Processed = 1 (or moved to NotificationHistory)
5. Periodically purged based on retention policy

**Export Dependencies:**
- holds.txt export reads this table 4x daily (8am, 9am, 1pm, 5pm)
- overdue.txt export reads this table 1x daily (8:04am)
- Must join to HoldNotices, OverdueNotices, or FineNotices for complete details

**Performance Considerations:**
- Index on Processed column recommended for export queries
- Index on (NotificationTypeID, DeliveryOptionID, Processed) recommended
- Index on CreationDate recommended for date-based filtering

---

## API ACCESS

**Polaris API Endpoint:**
```
GET /REST/protected/v1/{LangID}/{AppID}/{OrgID}/{AccessToken}/notifications/{NotificationTypeID}/{rows}/{organizations}
```

**Response Format:** XML/JSON containing NotificationQueue rows

**Fields Returned:**
- ReportingOrgID
- NotificationTypeID
- PatronID
- DeliveryOptionID
- ItemRecordID
- DueDate (for overdue)
- BrowseTitle (for item notifications)
- Renewals (for overdue)
- AutoRenewal flag
- IsAdditionalTxt flag

**Example Usage (Laravel papiclient):**
```php
$notifications = $client->notifications()
    ->getQueue(
        notificationTypeId: 2,  // Holds
        rows: 100,
        organizations: '3'  // DCPL branch
    );
```

---

## RELATED TABLES

**Upstream (Source Data):**
- Polaris.Polaris.Patrons - Patron master records
- Polaris.Polaris.CircItemRecords - Item records
- Polaris.Polaris.SysHoldRequests - Hold requests
- Polaris.Polaris.ItemCheckouts - Current checkouts

**Downstream (Detailed Notices):**
- Results.Polaris.HoldNotices - Hold notice details
- Results.Polaris.OverdueNotices - Overdue notice details
- Results.Polaris.FineNotices - Fine notice details

**Logging/History:**
- PolarisTransactions.Polaris.NotificationLog - Aggregate sent notifications
- Results.Polaris.NotificationHistory - Individual notification history

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-19 | Initial documentation created | Complete table structure documented |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Documentation:** This file, plus SHOUTBOMB_HOLDS_EXPORT.md, SHOUTBOMB_OVERDUE_EXPORT.md

**Last Reviewed:** 2025-11-19
