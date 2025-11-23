# DATABASE TABLE: PolarisTransactions.Polaris.NotificationLog

---

## TABLE METADATA

**Database:** PolarisTransactions

**Schema:** Polaris

**Table Name:** NotificationLog

**Purpose:** Aggregate log of patron notification batches that have been sent. One record per notification attempt, containing counts of holds/overdues/bills included in that notification. Used for reporting and tracking delivery success.

**Primary Key:** NotificationLogID (IDENTITY, auto-incrementing)

**Indexes:** Primary clustered index on NotificationLogID

**Related Documentation:** 
- TABLE_NotificationQueue.md (source data)
- Polaris_Notification_Guide_PAPIClient.md (API updates)

---

## FIELD DEFINITIONS

| Field # | Field Name | Data Type | Nullable? | Default | Description |
|---------|------------|-----------|-----------|---------|-------------|
| 1 | PatronID | int | YES | NULL | Internal Polaris patron identifier |
| 2 | NotificationDateTime | datetime | YES | NULL | When notification was sent |
| 3 | NotificationTypeID | int | YES | NULL | Type of notification (2=Hold, 1=Overdue, etc.) |
| 4 | DeliveryOptionID | int | YES | NULL | Delivery method (1=Mail, 3=Voice, 8=Text, etc.) |
| 5 | DeliveryString | nvarchar(255) | YES | NULL | Phone number or email address used |
| 6 | OverduesCount | int | YES | NULL | Number of overdue items in this notification |
| 7 | HoldsCount | int | YES | NULL | Number of holds in this notification |
| 8 | CancelsCount | int | YES | NULL | Number of canceled holds in this notification |
| 9 | RecallsCount | int | YES | NULL | Number of recalls in this notification |
| 10 | NotificationStatusID | int | YES | NULL | Delivery status (3=Delivered SMS, 9=Delivered Voice, etc.) |
| 11 | Details | nvarchar(255) | YES | NULL | Additional details or error messages |
| 12 | RoutingsCount | int | YES | 0 | Number of routing slips included |
| 13 | ReportingOrgID | int | YES | NULL | Branch/organization for reporting |
| 14 | PatronBarcode | nvarchar(20) | YES | NULL | Patron's library barcode |
| 15 | Reported | bit | YES | 0 | Whether this has been included in reports |
| 16 | Overdues2ndCount | int | YES | NULL | Number of 2nd overdue notices |
| 17 | Overdues3rdCount | int | YES | NULL | Number of 3rd overdue notices |
| 18 | BillsCount | int | YES | NULL | Number of bills included |
| 19 | LanguageID | int | YES | NULL | Language preference for notification |
| 20 | CarrierName | nvarchar(255) | YES | NULL | SMS carrier name (if applicable) |
| 21 | ManualBillCount | int | YES | NULL | Number of manual bills included |
| 22 | NotificationLogID | int | NO | IDENTITY | Primary key, auto-incrementing |

**Total Field Count:** 22

---

## LOOKUP TABLES

### NotificationStatusID Reference
| ID | Description | Channel |
|----|-------------|---------|
| 1 | Pending | All |
| 2 | Sent to Shoutbomb | SMS/Voice |
| 3 | Delivered (SMS) | SMS |
| 4 | Failed - Invalid Phone | SMS/Voice |
| 5 | Failed - No Answer | Voice |
| 6 | Failed - Invalid Email | Email |
| 7 | Delivered (Email) | Email |
| 8 | Failed - Disconnected | Voice |
| 9 | Delivered (Voice) | Voice |
| 10 | Failed - Opted Out | SMS |
| 15 | Mail Printed | Mail |
| 16 | Sent (generic) | All |

### DeliveryOptionID Reference
| ID | Description |
|----|-------------|
| 1 | Mail (postcard) |
| 2 | Email |
| 3 | Phone1 (Voice call) |
| 8 | TXT Messaging (SMS) |

---

## SAMPLE DATA

```sql
-- Successful SMS hold notification
INSERT INTO PolarisTransactions.Polaris.NotificationLog 
VALUES (200001, '2025-11-04 08:15:00', 2, 8, '270-555-0101', 
        0, 1, 0, 0, 3, NULL, 0, 1, '21234567890001', 0, 
        0, 0, 0, 1033, NULL, 0);

-- Failed SMS - Invalid Phone
INSERT INTO PolarisTransactions.Polaris.NotificationLog 
VALUES (200002, '2025-11-04 08:20:00', 2, 8, '270-555-9999', 
        0, 1, 0, 0, 4, 'Invalid phone number', 0, 1, '21234567890002', 0, 
        0, 0, 0, 1033, NULL, 0);

-- Successful Voice hold notification
INSERT INTO PolarisTransactions.Polaris.NotificationLog 
VALUES (200005, '2025-11-04 08:35:00', 2, 3, '270-555-0105', 
        0, 1, 0, 0, 9, NULL, 0, 1, '21234567890005', 0, 
        0, 0, 0, 1033, NULL, 0);

-- Overdue notification with multiple items
INSERT INTO PolarisTransactions.Polaris.NotificationLog 
VALUES (200010, '2025-11-04 09:00:00', 1, 8, '270-555-0110', 
        2, 0, 0, 0, 3, NULL, 0, 1, '21234567890010', 0, 
        0, 0, 0, 1033, NULL, 0);

-- Fine notification
INSERT INTO PolarisTransactions.Polaris.NotificationLog 
VALUES (200011, '2025-11-04 09:15:00', 8, 3, '270-555-0111', 
        0, 0, 0, 0, 9, NULL, 0, 1, '21234567890011', 0, 
        0, 0, 1, 1033, NULL, 0);
```

---

## CROSS-REFERENCE KEYS

| Field in THIS Table | Links to Table | Field in OTHER Table | Relationship |
|---------------------|----------------|---------------------|--------------|
| PatronID | Polaris.Polaris.Patrons | PatronID | Many:1 - One patron, many notifications |
| PatronBarcode | Polaris.Polaris.Patrons | Barcode | Many:1 - Alternate key for patron |
| NotificationTypeID | Polaris.Polaris.NotificationTypes | NotificationTypeID | Many:1 - Lookup table |
| DeliveryOptionID | Polaris.Polaris.SA_DeliveryOptions | DeliveryOptionID | Many:1 - Lookup table |
| NotificationStatusID | Polaris.Polaris.NotificationStatuses | NotificationStatusID | Many:1 - Lookup table |
| ReportingOrgID | Polaris.Polaris.Organizations | OrganizationID | Many:1 - Branch lookup |
| LanguageID | Polaris.Polaris.Languages | LanguageID | Many:1 - Language preference |

---

## KNOWN QUIRKS

**Aggregate Counts:**
- One NotificationLog record can represent multiple items (e.g., patron with 3 overdue books = 1 record with OverduesCount = 3)
- Individual item details are in NotificationHistory, not here
- Empty counts should be 0, not NULL, but may be NULL in older records

**DeliveryString Contains:**
- Phone numbers for Voice (DeliveryOptionID = 3) and SMS (DeliveryOptionID = 8)
- Email addresses for Email (DeliveryOptionID = 2)
- NULL for Mail (DeliveryOptionID = 1)
- Format varies: may include dashes, spaces, or be numeric only

**NotificationStatusID Updates:**
- Initial insert may have status = 2 (Sent to Shoutbomb)
- Should be updated to final status when Shoutbomb reports back
- Many records remain at status = 2 because feedback loop is manual
- **Future enhancement:** API integration to update status automatically

**Multiple Notification Types:**
- A single patron contact can include multiple types (holds + overdues + bills)
- Separate count fields track each type
- Example: Patron gets 1 call with 2 holds, 1 overdue, 1 bill = HoldsCount=2, OverduesCount=1, BillsCount=1

**Reported Flag:**
- Used to track which notifications have been included in statistical reports
- Prevents double-counting in monthly/quarterly reports
- Should be reset carefully when regenerating reports

---

## TYPICAL QUERIES

### Find All Notifications for a Patron
```sql
SELECT 
    NotificationLogID,
    NotificationDateTime,
    NotificationTypeID,
    DeliveryOptionID,
    DeliveryString,
    HoldsCount,
    OverduesCount,
    BillsCount,
    NotificationStatusID
FROM PolarisTransactions.Polaris.NotificationLog
WHERE PatronID = 200001
ORDER BY NotificationDateTime DESC;
```

### Find Failed Notifications in Last 30 Days
```sql
SELECT 
    PatronID,
    PatronBarcode,
    NotificationDateTime,
    DeliveryString,
    NotificationStatusID,
    Details
FROM PolarisTransactions.Polaris.NotificationLog
WHERE NotificationStatusID IN (4, 5, 6, 8, 10)  -- Failed statuses
  AND NotificationDateTime >= DATEADD(day, -30, GETDATE())
ORDER BY NotificationDateTime DESC;
```

### Success Rate by Delivery Method
```sql
SELECT 
    DeliveryOptionID,
    CASE DeliveryOptionID
        WHEN 1 THEN 'Mail'
        WHEN 2 THEN 'Email'
        WHEN 3 THEN 'Voice'
        WHEN 8 THEN 'SMS'
    END as DeliveryMethod,
    COUNT(*) as TotalSent,
    SUM(CASE WHEN NotificationStatusID IN (3, 7, 9, 15) THEN 1 ELSE 0 END) as Successful,
    SUM(CASE WHEN NotificationStatusID IN (4, 5, 6, 8, 10) THEN 1 ELSE 0 END) as Failed,
    CAST(100.0 * SUM(CASE WHEN NotificationStatusID IN (3, 7, 9, 15) THEN 1 ELSE 0 END) / COUNT(*) AS DECIMAL(5,2)) as SuccessRate
FROM PolarisTransactions.Polaris.NotificationLog
WHERE NotificationDateTime >= DATEADD(day, -30, GETDATE())
GROUP BY DeliveryOptionID
ORDER BY DeliveryOptionID;
```

### Find Notifications for Specific Phone Number
```sql
SELECT 
    PatronID,
    PatronBarcode,
    NotificationDateTime,
    NotificationTypeID,
    NotificationStatusID,
    HoldsCount,
    OverduesCount
FROM PolarisTransactions.Polaris.NotificationLog
WHERE DeliveryString = '270-555-0101'
ORDER BY NotificationDateTime DESC;
```

---

## VALIDATION RULES

**Data Integrity Checks:**
- [ ] PatronID should exist in Polaris.Polaris.Patrons
- [ ] PatronBarcode should match PatronID's barcode
- [ ] NotificationDateTime should not be in the future
- [ ] Count fields should be >= 0
- [ ] At least one count field should be > 0
- [ ] DeliveryString should be populated for Voice, SMS, Email
- [ ] DeliveryString format should match DeliveryOptionID (phone vs email)
- [ ] NotificationStatusID should be valid
- [ ] Reported flag should be 0 or 1

**Business Logic Validation:**
- If DeliveryOptionID = 3 or 8, DeliveryString should look like a phone number
- If DeliveryOptionID = 2, DeliveryString should contain '@'
- If NotificationTypeID = 2, HoldsCount should be > 0
- If NotificationTypeID = 1, OverduesCount should be > 0
- Failed statuses should have Details populated (helpful for debugging)

---

## PROCESSING NOTES

**Record Creation:**
- Created by Polaris when notification is sent
- May also be created via Polaris API NotificationUpdatePut endpoint
- One record per notification batch (not per item)

**Status Updates:**
- Initial status typically 2 (Sent) or 16 (Sent)
- Should be updated when delivery confirmed/failed
- **Current gap:** Shoutbomb feedback not automatically integrated
- **Future:** API integration to update status automatically

**Relationship to NotificationHistory:**
- NotificationLog = aggregate (one record per patron contact)
- NotificationHistory = detail (one record per item per patron)
- Example: Patron with 2 holds = 1 NotificationLog record, 2 NotificationHistory records

**Retention:**
- Permanent retention recommended for auditing
- May be archived to separate database after 1-2 years
- Critical for patron dispute resolution

---

## API ACCESS

**Polaris API Endpoint (Update Status):**
```
PUT /REST/protected/v1/{LangID}/{AppID}/{OrgID}/{AccessToken}/notification/{NotificationTypeID}
```

**Request Body Example:**
```xml
<NotificationUpdateData>
    <NotificationStatusID>3</NotificationStatusID>
    <PatronID>200001</PatronID>
    <PatronBarcode>21234567890001</PatronBarcode>
    <DeliveryOptionID>8</DeliveryOptionID>
    <DeliveryString>270-555-0101</DeliveryString>
    <ItemRecordID>300001</ItemRecordID>
</NotificationUpdateData>
```

**Effect:** Creates/updates NotificationLog record with status

**Example Usage (Laravel papiclient):**
```php
$result = $client->notifications()
    ->markAsSent(
        notificationTypeId: 2,  // Hold
        data: [
            'NotificationStatusID' => 3,  // Delivered (SMS)
            'PatronID' => 200001,
            'PatronBarcode' => '21234567890001',
            'DeliveryOptionID' => 8,  // SMS
            'DeliveryString' => '270-555-0101',
            'ItemRecordID' => 300001
        ]
    );
```

---

## RELATED TABLES

**Upstream (Source Data):**
- Results.Polaris.NotificationQueue - Pending notifications
- Polaris.Polaris.Patrons - Patron master records

**Related Detail:**
- Results.Polaris.NotificationHistory - Individual item-level notification details

**Lookup Tables:**
- Polaris.Polaris.NotificationTypes - Notification type definitions
- Polaris.Polaris.SA_DeliveryOptions - Delivery method definitions
- Polaris.Polaris.NotificationStatuses - Status code definitions
- Polaris.Polaris.Languages - Language preferences

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-19 | Initial documentation created | Complete table structure documented |

---

## CONTACT / SUPPORT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org), Daviess County Public Library

**Documentation:** This file, plus TABLE_NotificationQueue.md, Polaris_Notification_Guide_PAPIClient.md

**Last Reviewed:** 2025-11-19
