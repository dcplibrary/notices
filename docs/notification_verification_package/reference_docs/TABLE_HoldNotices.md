# DATABASE TABLE: Results.Polaris.HoldNotices

---

## TABLE METADATA

**Database:** Results

**Schema:** Polaris

**Table Name:** HoldNotices

**Purpose:** Detailed information about hold notifications ready to be sent. Contains item and patron details for each hold that needs notification. Joined with NotificationQueue to create complete hold notification exports.

**Primary Key:** None explicitly defined (composite of ItemRecordID + PatronID + NotificationTypeID implied)

**Indexes:** Not specified in CREATE TABLE

**Related Documentation:** 
- SHOUTBOMB_HOLDS_EXPORT.md (uses this table)
- TABLE_NotificationQueue.md (queue source)

---

## FIELD DEFINITIONS

| Field # | Field Name | Data Type | Nullable? | Default | Description |
|---------|------------|-----------|-----------|---------|-------------|
| 1 | ItemRecordID | int | NO | (required) | Internal Polaris item identifier |
| 2 | AssignedBranchID | int | NO | (required) | Branch where item is currently located |
| 3 | PickupOrganizationID | int | NO | (required) | Branch where patron will pick up hold |
| 4 | PatronID | int | NO | (required) | Internal Polaris patron identifier |
| 5 | ItemBarcode | nvarchar(20) | NO | (required) | Physical barcode on item |
| 6 | BrowseTitle | nvarchar(255) | YES | NULL | Item title for display/notification |
| 7 | BrowseAuthor | nvarchar(255) | YES | NULL | Item author for display/notification |
| 8 | ItemCallNumber | nvarchar(370) | YES | NULL | Call number/shelf location |
| 9 | Price | money | YES | NULL | Item replacement cost |
| 10 | Abbreviation | nvarchar(15) | YES | NULL | Branch abbreviation (pickup location) |
| 11 | Name | nvarchar(50) | YES | NULL | Branch name (pickup location) |
| 12 | PhoneNumberOne | nvarchar(20) | YES | NULL | Patron's primary phone number |
| 13 | DeliveryOptionID | int | YES | 1 (Mail) | Delivery method preference |
| 14 | HoldTillDate | datetime | YES | NULL | Expiration date - patron must pick up by this date |
| 15 | ItemFormatID | int | YES | NULL | Format type (book, DVD, audiobook, etc.) |
| 16 | AdminLanguageID | int | YES | NULL | Language preference for notification |
| 17 | NotificationTypeID | int | YES | NULL | Always 2 for hold notifications |
| 18 | HoldPickupAreaID | int | YES | 0 | Designated pickup area within branch |

**Total Field Count:** 18

---

## LOOKUP TABLES

### DeliveryOptionID Reference
| ID | Description | Shoutbomb Export |
|----|-------------|------------------|
| 1 | Mail (postcard) | No - handled by Polaris |
| 2 | Email | No - handled by Polaris |
| 3 | Phone1 (Voice) | Yes - included in holds.txt |
| 8 | TXT Messaging | Yes - included in holds.txt |

### NotificationTypeID
- Always 2 for HoldNotices (Hold Ready for Pickup)

---

## SAMPLE DATA

```sql
-- Hold ready at Central Library for SMS patron
INSERT INTO Results.Polaris.HoldNotices 
VALUES (300001, 1, 1, 200001, '31234567890001', 
        'The Midnight Library', 'Matt Haig', '823.92 HAI', 
        19.99, 'CENTR', 'Central Library', '270-555-0101', 
        8, '2025-11-08', 1, 1033, 2, 0);

-- Hold ready at Cloverport for Voice patron
INSERT INTO Results.Polaris.HoldNotices 
VALUES (300002, 2, 2, 200005, '31234567890002', 
        'Project Hail Mary', 'Andy Weir', 'SF WEI', 
        28.99, 'CLVPT', 'Cloverport Library', '270-555-0105', 
        3, '2025-11-10', 1, 1033, 2, 0);

-- Hold ready with invalid email (won't be in Shoutbomb export)
INSERT INTO Results.Polaris.HoldNotices 
VALUES (300003, 1, 1, 200003, '31234567890003', 
        'Where the Crawdads Sing', 'Delia Owens', '813.6 OWE', 
        24.99, 'CENTR', 'Central Library', NULL, 
        2, '2025-11-09', 1, 1033, 2, 0);
```

---

## CROSS-REFERENCE KEYS

| Field in THIS Table | Links to Table | Field in OTHER Table | Relationship |
|---------------------|----------------|---------------------|--------------|
| ItemRecordID | Polaris.Polaris.CircItemRecords | ItemRecordID | 1:1 - Specific item |
| PatronID | Polaris.Polaris.Patrons | PatronID | Many:1 - One patron, many holds |
| AssignedBranchID | Polaris.Polaris.Organizations | OrganizationID | Many:1 - Branch location |
| PickupOrganizationID | Polaris.Polaris.Organizations | OrganizationID | Many:1 - Pickup branch |
| DeliveryOptionID | Polaris.Polaris.SA_DeliveryOptions | DeliveryOptionID | Many:1 - Delivery method |
| ItemFormatID | Polaris.Polaris.ItemFormats | ItemFormatID | Many:1 - Format lookup |
| AdminLanguageID | Polaris.Polaris.Languages | LanguageID | Many:1 - Language preference |
| NotificationTypeID | Polaris.Polaris.NotificationTypes | NotificationTypeID | Many:1 - Always type 2 |
| HoldPickupAreaID | Polaris.Polaris.HoldPickupAreas | HoldPickupAreaID | Many:1 - Pickup location |

**Key Join for Shoutbomb Export:**
```sql
FROM Results.Polaris.NotificationQueue q
JOIN Results.Polaris.HoldNotices hn 
  ON q.ItemRecordID = hn.ItemRecordID 
  AND q.PatronID = hn.PatronID 
  AND q.NotificationTypeID = hn.NotificationTypeID
```

---

## KNOWN QUIRKS

**HoldTillDate Filtering:**
- Must always check: `WHERE HoldTillDate > GETDATE()`
- Expired holds should not be notified
- HoldTillDate typically 3-7 days from hold fill date

**PhoneNumberOne Field:**
- Contains patron's Phone1 from patron record
- NOT used for Shoutbomb exports (uses patron table directly)
- May be NULL or outdated - always check current patron record

**BrowseTitle Pipe Characters:**
- May contain pipe characters which break delimited export
- Shoutbomb export SQL uses: `REPLACE(hn.BrowseTitle, '|', '-')`
- Always escape or replace pipe characters before export

**Branch Name vs Abbreviation:**
- Both Abbreviation and Name refer to pickup location
- Abbreviation is short code (e.g., "CENTR")
- Name is full name (e.g., "Central Library")
- Used for patron display, not for system processing

**DeliveryOptionID Values:**
- Default is 1 (Mail) if not specified
- Should match patron's preference in Polaris.Patrons table
- Shoutbomb only processes 3 (Voice) and 8 (SMS)

**HoldPickupAreaID:**
- Default 0 means general pickup area
- Used for libraries with multiple pickup desks
- Not commonly used at DCPL

---

## TYPICAL QUERIES

### Shoutbomb Hold Export (Voice & SMS Only)
```sql
SELECT 
    hn.BrowseTitle,
    CONVERT(VARCHAR(10), hr.CreationDate, 120) as CreationDate,
    hr.SysHoldRequestID,
    hn.PatronID,
    hn.PickupOrganizationID,
    CONVERT(VARCHAR(10), hn.HoldTillDate, 120) as HoldTillDate,
    p.Barcode as PatronBarcode
FROM Results.Polaris.NotificationQueue q (NOLOCK)
JOIN Results.Polaris.HoldNotices hn (NOLOCK) 
  ON q.ItemRecordID = hn.ItemRecordID 
  AND q.PatronID = hn.PatronID 
  AND q.NotificationTypeID = hn.NotificationTypeID
JOIN Polaris.Polaris.Patrons p (NOLOCK) 
  ON q.PatronID = p.PatronID
JOIN Polaris.Polaris.SysHoldRequests hr (NOLOCK)
  ON q.PatronID = hr.PatronID 
  AND q.ItemRecordID = hr.TrappingItemRecordID
WHERE (q.DeliveryOptionID = 3 OR q.DeliveryOptionID = 8)
  AND hn.HoldTillDate > GETDATE()
ORDER BY p.Barcode;
```

### Find Holds Expiring Soon
```sql
SELECT 
    PatronID,
    ItemBarcode,
    BrowseTitle,
    HoldTillDate,
    DATEDIFF(day, GETDATE(), HoldTillDate) as DaysRemaining
FROM Results.Polaris.HoldNotices
WHERE HoldTillDate BETWEEN GETDATE() AND DATEADD(day, 2, GETDATE())
ORDER BY HoldTillDate;
```

### Count Ready Holds by Pickup Location
```sql
SELECT 
    PickupOrganizationID,
    Abbreviation,
    COUNT(*) as ReadyHoldsCount
FROM Results.Polaris.HoldNotices
WHERE HoldTillDate > GETDATE()
GROUP BY PickupOrganizationID, Abbreviation
ORDER BY ReadyHoldsCount DESC;
```

### Find Patron's Ready Holds
```sql
SELECT 
    ItemRecordID,
    ItemBarcode,
    BrowseTitle,
    BrowseAuthor,
    Abbreviation as PickupBranch,
    HoldTillDate,
    DeliveryOptionID
FROM Results.Polaris.HoldNotices
WHERE PatronID = 200001
  AND HoldTillDate > GETDATE()
ORDER BY HoldTillDate;
```

---

## VALIDATION RULES

**Data Integrity Checks:**
- [ ] ItemRecordID must exist in Polaris.Polaris.CircItemRecords
- [ ] PatronID must exist in Polaris.Polaris.Patrons
- [ ] ItemBarcode must match ItemRecordID's barcode
- [ ] PickupOrganizationID must exist in Polaris.Polaris.Organizations
- [ ] AssignedBranchID must exist in Polaris.Polaris.Organizations
- [ ] HoldTillDate must be in the future (for active holds)
- [ ] DeliveryOptionID must be valid (1, 2, 3, 8)
- [ ] NotificationTypeID should always be 2

**Business Logic Validation:**
- If DeliveryOptionID = 3 or 8, PhoneNumberOne should be populated
- If DeliveryOptionID = 2, patron should have valid email
- HoldTillDate should be >= today (expired holds should be removed)
- BrowseTitle should not be NULL or empty
- ItemBarcode should match CircItemRecords.Barcode

---

## PROCESSING NOTES

**Record Lifecycle:**
1. Hold trapped and item pulled from shelf
2. HoldNotices record created when hold is ready
3. NotificationQueue record created for this hold
4. Export query joins HoldNotices + NotificationQueue
5. Notification sent to patron
6. After HoldTillDate expires, record is deleted or archived

**Export Dependencies:**
- Must join with NotificationQueue to get delivery preferences
- Must join with Patrons to get current patron barcode
- Must join with SysHoldRequests to get CreationDate and SysHoldRequestID
- Filtered by DeliveryOptionID (only 3 and 8 for Shoutbomb)

**Performance Considerations:**
- Index on (ItemRecordID, PatronID, NotificationTypeID) recommended
- Index on HoldTillDate recommended for expiration queries
- Index on PickupOrganizationID for branch reports

---

## RELATED TABLES

**Upstream (Source Data):**
- Polaris.Polaris.SysHoldRequests - Hold request master
- Polaris.Polaris.CircItemRecords - Item details
- Polaris.Polaris.Patrons - Patron details
- Polaris.Polaris.BibliographicRecords - Catalog records

**Parallel:**
- Results.Polaris.NotificationQueue - Pending notifications
- Results.Polaris.OverdueNotices - Overdue item details
- Results.Polaris.FineNotices - Fine notification details

**Downstream:**
- PolarisTransactions.Polaris.NotificationLog - Sent notification log

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-19 | Initial documentation created | Complete table structure documented |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Documentation:** This file, plus SHOUTBOMB_HOLDS_EXPORT.md, TABLE_NotificationQueue.md

**Last Reviewed:** 2025-11-19
