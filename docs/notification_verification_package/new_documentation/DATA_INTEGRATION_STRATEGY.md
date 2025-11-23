# NOTIFICATION VERIFICATION SYSTEM - DATA INTEGRATION STRATEGY

**Version:** 1.0  
**Date:** November 19, 2025  
**System Owner:** Brian Lashbrook, DC Public Library

---

## 📋 TABLE OF CONTENTS

1. [Executive Summary](#executive-summary)
2. [Current Data Sources](#current-data-sources)
3. [Field Availability Matrix](#field-availability-matrix)
4. [Missing Data & Gaps](#missing-data--gaps)
5. [Required SQL Queries](#required-sql-queries)
6. [Recommended Database Tables](#recommended-database-tables)
7. [Data Integration Architecture](#data-integration-architecture)
8. [Implementation Roadmap](#implementation-roadmap)

---

## 📊 EXECUTIVE SUMMARY

### Goal
Build a comprehensive notification verification tool that can trace the complete lifecycle of a patron notification from initiation to delivery, including all relevant dates (checkout, renewal, hold placement, notification sent, delivery status).

### Current State
- **Shoutbomb submission files** contain basic notification info (patron, item, title)
- **NotificationHistory table** has notification records but limited context
- **NotificationLog table** has aggregate delivery status
- **Missing:** Checkout dates, renewal dates, hold placed dates, complete patron contact info

### Required Enhancement
Query additional Polaris tables to gather:
- Complete patron information (PatronRegistration, Patrons)
- Item circulation history (CircItemRecords, ItemCheckouts)
- Hold request details with dates (SysHoldRequests)
- Checkout and renewal timeline data

---

## 🗂️ CURRENT DATA SOURCES

### 1. Shoutbomb Submission Files (FTP Export)

#### holds_submitted.txt
**Available Fields:**
- PatronID
- BrowseTitle (BTitle)
- SysHoldRequestID
- PatronBarcode (PBarcode)
- HoldTillDate

**Missing:**
- Item details (barcode, call number, author)
- Hold placed date
- Pickup branch details
- Patron contact info
- Notification sent date/time

#### overdue_submitted.txt
**Available Fields:**
- ItemBarcode
- DueDate
- BrowseTitle
- PatronBarcode
- BibliographicRecordID

**Missing:**
- Original checkout date
- Number of renewals
- Renewal dates
- Item call number
- Current fine amount
- Patron contact info

#### text_patrons_submitted.txt & voice_patrons_submitted.txt
**Available Fields:**
- Barcode (patron)
- PhoneVoice1

**Missing:**
- Patron name
- Email address
- Other phone numbers
- Delivery preference history
- Opt-out status

---

### 2. Database Tables (Currently Used)

#### Results.Polaris.NotificationQueue
**Available Fields:**
- ItemRecordID ✓
- PatronID ✓
- NotificationTypeID ✓
- DeliveryOptionID ✓
- Processed ✓
- ReportingOrgID ✓
- Amount (for fines) ✓
- CreationDate ✓

**Missing:**
- Patron name
- Item details
- Checkout/Hold dates
- Contact information

#### Results.Polaris.NotificationHistory
**Available Fields:**
- ItemRecordId ✓
- PatronID ✓
- Title (BrowseTitle) ✓
- NotificationTypeId ✓
- DeliveryOptionId ✓
- NoticeDate ✓
- NotificationStatusId ✓
- Amount ✓
- TxnId ✓
- ReportingOrgId ✓

**Missing:**
- Patron contact info
- Item barcode
- Checkout date
- Hold placed date
- Renewal dates

#### PolarisTransactions.Polaris.NotificationLog
**Available Fields:**
- PatronID ✓
- PatronBarcode ✓
- NotificationDateTime ✓
- NotificationTypeID ✓
- DeliveryOptionID ✓
- DeliveryString (phone/email) ✓
- NotificationStatusID ✓
- Details (error messages) ✓
- HoldsCount, OverduesCount, BillsCount ✓
- LanguageID ✓
- ReportingOrgID ✓

**Missing:**
- Individual item details (aggregated only)
- Timeline dates (checkout, hold placed)

#### Results.Polaris.HoldNotices
**Available Fields:**
- ItemRecordID ✓
- AssignedBranchID ✓
- PickupOrganizationID ✓
- PatronID ✓
- ItemBarcode ✓
- BrowseTitle ✓
- BrowseAuthor ✓
- ItemCallNumber ✓
- Abbreviation (branch) ✓
- Name (branch name) ✓
- PhoneNumberOne ✓
- DeliveryOptionID ✓
- HoldTillDate ✓
- ItemFormatID ✓
- AdminLanguageID ✓

**Missing:**
- Hold placed date
- Hold filled date
- SysHoldRequestID
- Patron name
- Patron email

---

## 🎯 FIELD AVAILABILITY MATRIX

| Data Field | Submission Files | NotificationQueue | NotificationHistory | NotificationLog | HoldNotices | **Need SQL?** |
|------------|-----------------|-------------------|---------------------|-----------------|-------------|---------------|
| **Patron Info** |
| PatronID | ✓ | ✓ | ✓ | ✓ | ✓ | No |
| PatronBarcode | ✓ | ❌ | ❌ | ✓ | ❌ | No |
| Patron Name | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Patron Email | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Phone1 | ✓ (patrons file) | ❌ | ❌ | ✓ (DeliveryString) | ✓ | **YES** |
| Phone2 | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Phone3 | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Delivery Preference | ❌ | ✓ | ✓ | ✓ | ✓ | No |
| Language Preference | ❌ | ❌ | ❌ | ✓ | ✓ | No |
| **Item Info** |
| ItemRecordID | ❌ (holds only) | ✓ | ✓ | ❌ | ✓ | No |
| ItemBarcode | ❌ (overdue only) | ✓ (via join) | ❌ | ❌ | ✓ | **YES** |
| BrowseTitle | ✓ | ✓ (via join) | ✓ | ❌ | ✓ | No |
| BrowseAuthor | ❌ | ❌ | ❌ | ❌ | ✓ | **YES** |
| Call Number | ❌ | ❌ | ❌ | ❌ | ✓ | **YES** |
| Material Type | ❌ | ❌ | ❌ | ❌ | ✓ (FormatID) | **YES** |
| **Timeline Dates** |
| Checkout Date | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Original Due Date | Partial (overdue) | ❌ | ❌ | ❌ | ❌ | **YES** |
| Current Due Date | ✓ (overdue) | ❌ | ❌ | ❌ | ❌ | **YES** |
| Renewal Count | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Renewal Dates | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Hold Placed Date | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Hold Filled Date | ❌ | ❌ | ❌ | ❌ | ❌ | **YES** |
| Hold Expiration | ✓ (HoldTillDate) | ❌ | ❌ | ❌ | ✓ | No |
| **Notification Info** |
| NotificationTypeID | ❌ | ✓ | ✓ | ✓ | ✓ (always 2) | No |
| Notification Sent Date | ❌ | ✓ (CreationDate) | ✓ (NoticeDate) | ✓ (NotificationDateTime) | ❌ | No |
| Delivery Status | ❌ | ❌ | ✓ | ✓ | ❌ | No |
| Delivery Method | ❌ | ✓ | ✓ | ✓ | ✓ | No |

---

## ⚠️ MISSING DATA & GAPS

### Critical Missing Fields

#### 1. Timeline Dates (Highest Priority)
**Why Needed:** To verify if patron should have received notification
- ❌ **Checkout Date** - When item was checked out
- ❌ **Renewal Dates** - Each time item was renewed
- ❌ **Hold Placed Date** - When patron placed the hold
- ❌ **Hold Filled Date** - When item was trapped and pulled for patron

**Impact:** Cannot verify:
- If patron had item long enough to be overdue
- If notification timing was appropriate
- Complete timeline of patron's interaction with item

#### 2. Complete Patron Contact Info
**Why Needed:** To verify correct delivery method was used
- ❌ **Patron Full Name** - For verification
- ❌ **Patron Email** - Email delivery verification
- ❌ **Phone2, Phone3** - Backup contact methods
- ❌ **SMS Opt-in Status** - Whether patron can receive SMS

**Impact:** Cannot verify:
- If correct phone/email was used
- If patron opted out of notifications
- If backup contact methods exist

#### 3. Item Details for Overdue Notifications
**Why Needed:** Submission files don't include complete item info
- ❌ **Item Call Number** - For staff verification
- ❌ **Item Author** - Complete bibliographic info
- ❌ **Material Type** - Book vs DVD vs audiobook

**Impact:** Cannot:
- Provide complete item details to patron
- Verify correct item was included in notification

---

## 🔍 REQUIRED SQL QUERIES

### Query Set 1: Complete Patron Information

#### Q1.1: Get Patron Contact Details
```sql
-- Purpose: Retrieve complete patron contact information
-- Source: Polaris.Polaris.Patrons + PatronRegistration
-- Used: For every verification lookup

SELECT 
    p.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst,
    pr.NameLast,
    pr.NameMiddle,
    pr.NameTitle,
    p.EmailAddress,
    pr.PhoneVoice1,
    pr.PhoneVoice2,
    pr.PhoneVoice3,
    pr.PhoneFAX,
    p.DeliveryOptionID,
    p.EnableSMS,
    p.TxtPhoneNumber,
    p.ExcludeFromAlmostOverdueAutoRenew,
    p.ExcludeFromPatronRecExpiration,
    p.ExcludeFromOverdue,
    p.ExcludeFromBills,
    p.ExcludeFromInactivePatron,
    p.PatronFullName,
    pr.LanguageID
FROM Polaris.Polaris.Patrons p
JOIN Polaris.Polaris.PatronRegistration pr 
    ON p.PatronID = pr.PatronID
WHERE p.PatronID = @PatronID;
```

**Returns:**
- Complete name (first, middle, last, title)
- All phone numbers (Voice1, Voice2, Voice3, FAX)
- Email address
- SMS opt-in status
- Delivery preference
- Exclusion flags (opted out of notifications)

---

### Query Set 2: Item Circulation Details

#### Q2.1: Get Current Checkout Details
```sql
-- Purpose: Get current checkout information for an item
-- Source: Polaris.Polaris.ItemCheckouts
-- Used: For overdue notification verification

SELECT 
    co.ItemRecordID,
    co.PatronID,
    co.CheckOutDate,
    co.DueDate AS OriginalDueDate,
    co.RenewalCount,
    co.LastRenewalDate,
    co.LoanPeriodCodeID,
    co.LoanPeriodEndDate AS CurrentDueDate,
    co.OrganizationID AS CheckoutBranchID,
    org.Name AS CheckoutBranch
FROM Polaris.Polaris.ItemCheckouts co
JOIN Polaris.Polaris.Organizations org 
    ON co.OrganizationID = org.OrganizationID
WHERE co.ItemRecordID = @ItemRecordID
  AND co.PatronID = @PatronID;
```

**Returns:**
- Original checkout date
- Original due date
- Number of renewals
- Last renewal date
- Current due date
- Checkout branch

#### Q2.2: Get Item Details
```sql
-- Purpose: Get complete item information
-- Source: Polaris.Polaris.CircItemRecords
-- Used: For all item-based notifications

SELECT 
    cir.ItemRecordID,
    cir.Barcode AS ItemBarcode,
    cir.CallNumber,
    cir.AssignedBranchID,
    cir.ShelvingLocationID,
    cir.MaterialTypeID,
    cir.CollectionID,
    cir.ItemStatusID,
    cir.LoanPeriodCodeID,
    cir.PriceAmount AS ReplacementCost,
    -- Branch info
    org.Name AS AssignedBranch,
    -- Material type
    mt.Description AS MaterialType,
    -- Shelving location
    sl.Description AS ShelvingLocation
FROM Polaris.Polaris.CircItemRecords cir
JOIN Polaris.Polaris.Organizations org 
    ON cir.AssignedBranchID = org.OrganizationID
LEFT JOIN Polaris.Polaris.MaterialTypes mt 
    ON cir.MaterialTypeID = mt.MaterialTypeID
LEFT JOIN Polaris.Polaris.ShelvingLocations sl 
    ON cir.ShelvingLocationID = sl.ShelvingLocationID
WHERE cir.ItemRecordID = @ItemRecordID;
```

**Returns:**
- Item barcode
- Call number
- Material type (book, DVD, etc.)
- Assigned branch
- Shelving location
- Replacement cost

#### Q2.3: Get Bibliographic Details
```sql
-- Purpose: Get title, author, and other bibliographic info
-- Source: Polaris.Polaris.BibliographicRecords
-- Used: For complete citation information

SELECT 
    br.BibliographicRecordID,
    br.BrowseTitle,
    br.BrowseAuthor,
    br.Publisher,
    br.PublicationYear,
    br.Edition,
    br.ISBN,
    br.MaterialTypeID,
    mt.Description AS MaterialType
FROM Polaris.Polaris.BibliographicRecords br
LEFT JOIN Polaris.Polaris.MaterialTypes mt 
    ON br.MaterialTypeID = mt.MaterialTypeID
WHERE br.BibliographicRecordID = (
    SELECT BibliographicRecordID 
    FROM Polaris.Polaris.CircItemRecords 
    WHERE ItemRecordID = @ItemRecordID
);
```

**Returns:**
- Title and author
- Publisher, publication year
- Edition
- ISBN
- Material type

---

### Query Set 3: Hold Request Timeline

#### Q3.1: Get Hold Request Details
```sql
-- Purpose: Get complete hold request information with all dates
-- Source: Polaris.Polaris.SysHoldRequests
-- Used: For hold notification verification

SELECT 
    hr.SysHoldRequestID,
    hr.PatronID,
    hr.BibliographicRecordID,
    hr.ItemRecordID,
    hr.TrappingItemRecordID,
    -- Important dates
    hr.RequestDate AS HoldPlacedDate,
    hr.ActivationDate,
    hr.HoldTillDate,
    hr.PickupOrgID AS PickupBranchID,
    hr.HoldNotificationDate AS NotificationSentDate,
    hr.FilledDate AS HoldFilledDate,
    -- Status and type
    hr.StatusID AS HoldStatusID,
    hr.HoldTypeID,
    hr.DeliveryOptionID,
    -- Notes
    hr.PatronNotes,
    hr.StaffDisplayNotes,
    hr.NonPublicNotes,
    -- Branch info
    org.Name AS PickupBranch,
    org.Abbreviation AS PickupBranchCode
FROM Polaris.Polaris.SysHoldRequests hr
JOIN Polaris.Polaris.Organizations org 
    ON hr.PickupOrgID = org.OrganizationID
WHERE hr.PatronID = @PatronID
  AND hr.SysHoldRequestID = @SysHoldRequestID;
```

**Returns:**
- Hold placed date (**Critical for timeline**)
- Hold filled date (**When item was trapped**)
- Hold notification sent date
- Hold expiration date (HoldTillDate)
- Pickup branch
- Hold status
- Patron and staff notes

---

### Query Set 4: Renewal History

#### Q4.1: Get Renewal Timeline
```sql
-- Purpose: Get complete renewal history for a checkout
-- Source: Polaris.Polaris.TransactionHistory (if available)
-- Used: To see all renewal attempts and dates

-- Note: This query depends on your Polaris configuration
-- Some systems log renewals in TransactionHistory, others don't

SELECT 
    th.TransactionID,
    th.TransactionDate AS RenewalDate,
    th.PatronID,
    th.ItemRecordID,
    th.TransactionTypeID,
    tt.Description AS TransactionType,
    th.OrganizationID AS RenewalBranchID,
    org.Name AS RenewalBranch
FROM Polaris.Polaris.TransactionHistory th
JOIN Polaris.Polaris.TransactionTypes tt 
    ON th.TransactionTypeID = tt.TransactionTypeID
JOIN Polaris.Polaris.Organizations org 
    ON th.OrganizationID = org.OrganizationID
WHERE th.PatronID = @PatronID
  AND th.ItemRecordID = @ItemRecordID
  AND th.TransactionTypeID IN (6001, 6002)  -- Renewal transaction types
ORDER BY th.TransactionDate;
```

**Note:** If TransactionHistory doesn't include renewals, you may only have:
- RenewalCount from ItemCheckouts (total count)
- LastRenewalDate from ItemCheckouts (most recent)

---

### Query Set 5: Notification Verification (Complete Timeline)

#### Q5.1: Complete Hold Notification History
```sql
-- Purpose: Get complete hold notification timeline for a patron
-- Combines: NotificationQueue, NotificationLog, NotificationHistory, HoldNotices, SysHoldRequests
-- Used: Main verification query for hold notifications

SELECT 
    -- Patron Info
    p.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast AS PatronName,
    p.EmailAddress,
    pr.PhoneVoice1,
    p.TxtPhoneNumber,
    p.DeliveryOptionID AS PreferredDeliveryMethod,
    -- Hold Request Info
    hr.SysHoldRequestID,
    hr.RequestDate AS HoldPlacedDate,
    hr.FilledDate AS HoldFilledDate,
    hr.HoldTillDate AS HoldExpirationDate,
    hr.PickupOrgID,
    org.Name AS PickupBranch,
    -- Item Info
    hn.ItemRecordID,
    hn.ItemBarcode,
    hn.BrowseTitle,
    hn.BrowseAuthor,
    hn.ItemCallNumber,
    -- Notification Queue Info
    nq.CreationDate AS QueuedDate,
    nq.Processed AS WasProcessed,
    nq.DeliveryOptionID AS NotificationDeliveryMethod,
    -- Notification Log Info (aggregate)
    nl.NotificationDateTime AS ActualSentDate,
    nl.NotificationStatusID AS DeliveryStatus,
    nst.Description AS DeliveryStatusDescription,
    nl.DeliveryString AS SentToAddress,
    nl.Details AS DeliveryDetails,
    -- Notification History Info (individual)
    nh.NoticeDate AS HistoryNoticeDate,
    nh.NotificationStatusId AS HistoryStatus,
    -- Delivery Method Descriptions
    do1.Description AS PreferredMethod,
    do2.Description AS ActualMethod
FROM Polaris.Polaris.Patrons p
JOIN Polaris.Polaris.PatronRegistration pr 
    ON p.PatronID = pr.PatronID
-- Hold request details
LEFT JOIN Polaris.Polaris.SysHoldRequests hr 
    ON p.PatronID = hr.PatronID
LEFT JOIN Polaris.Polaris.Organizations org 
    ON hr.PickupOrgID = org.OrganizationID
-- Hold notice details
LEFT JOIN Results.Polaris.HoldNotices hn 
    ON hr.PatronID = hn.PatronID 
    AND hr.TrappingItemRecordID = hn.ItemRecordID
-- Notification queue
LEFT JOIN Results.Polaris.NotificationQueue nq 
    ON hr.PatronID = nq.PatronID 
    AND hr.TrappingItemRecordID = nq.ItemRecordID 
    AND nq.NotificationTypeID = 2  -- Hold notifications
-- Notification log (aggregate)
LEFT JOIN PolarisTransactions.Polaris.NotificationLog nl 
    ON hr.PatronID = nl.PatronID 
    AND nl.NotificationTypeID = 2
    AND CAST(nl.NotificationDateTime AS DATE) = CAST(nq.CreationDate AS DATE)
-- Notification history (individual)
LEFT JOIN Results.Polaris.NotificationHistory nh 
    ON hr.PatronID = nh.PatronID 
    AND hr.TrappingItemRecordID = nh.ItemRecordId 
    AND nh.NotificationTypeId = 2
-- Lookup tables
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do1 
    ON p.DeliveryOptionID = do1.DeliveryOptionID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do2 
    ON nq.DeliveryOptionID = do2.DeliveryOptionID
LEFT JOIN Polaris.Polaris.NotificationStatuses nst 
    ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE p.PatronID = @PatronID
  AND hr.RequestDate >= DATEADD(day, -30, GETDATE())  -- Last 30 days
ORDER BY hr.RequestDate DESC, nq.CreationDate DESC;
```

**This monster query returns EVERYTHING:**
- Complete patron info (name, phone, email, preferences)
- Hold timeline (placed, filled, expiration)
- Item details (barcode, title, author, call number)
- Queue information (when queued, processed status)
- Delivery attempt (when sent, to what address, status)
- Lookup descriptions (delivery methods, statuses)

---

#### Q5.2: Complete Overdue Notification History
```sql
-- Purpose: Get complete overdue notification timeline for a patron
-- Combines: NotificationQueue, NotificationLog, ItemCheckouts, CircItemRecords
-- Used: Main verification query for overdue notifications

SELECT 
    -- Patron Info
    p.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast AS PatronName,
    p.EmailAddress,
    pr.PhoneVoice1,
    p.TxtPhoneNumber,
    p.DeliveryOptionID AS PreferredDeliveryMethod,
    -- Checkout Info
    co.ItemRecordID,
    co.CheckOutDate,
    co.DueDate AS OriginalDueDate,
    co.RenewalCount,
    co.LastRenewalDate,
    co.LoanPeriodEndDate AS CurrentDueDate,
    DATEDIFF(day, co.LoanPeriodEndDate, GETDATE()) AS DaysOverdue,
    -- Item Info
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    br.BrowseAuthor,
    cir.CallNumber,
    mt.Description AS MaterialType,
    -- Notification Queue Info
    nq.CreationDate AS QueuedDate,
    nq.Processed AS WasProcessed,
    nq.NotificationTypeID,
    nt.Description AS NotificationType,
    nq.DeliveryOptionID AS NotificationDeliveryMethod,
    -- Notification Log Info (aggregate)
    nl.NotificationDateTime AS ActualSentDate,
    nl.NotificationStatusID AS DeliveryStatus,
    nst.Description AS DeliveryStatusDescription,
    nl.DeliveryString AS SentToAddress,
    nl.Details AS DeliveryDetails,
    nl.OverduesCount,
    -- Notification History Info (individual)
    nh.NoticeDate AS HistoryNoticeDate,
    nh.NotificationStatusId AS HistoryStatus,
    nh.Amount AS FineAmount,
    -- Delivery Method Descriptions
    do1.Description AS PreferredMethod,
    do2.Description AS ActualMethod
FROM Polaris.Polaris.Patrons p
JOIN Polaris.Polaris.PatronRegistration pr 
    ON p.PatronID = pr.PatronID
-- Current checkout
LEFT JOIN Polaris.Polaris.ItemCheckouts co 
    ON p.PatronID = co.PatronID
-- Item details
LEFT JOIN Polaris.Polaris.CircItemRecords cir 
    ON co.ItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br 
    ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.MaterialTypes mt 
    ON cir.MaterialTypeID = mt.MaterialTypeID
-- Notification queue
LEFT JOIN Results.Polaris.NotificationQueue nq 
    ON co.PatronID = nq.PatronID 
    AND co.ItemRecordID = nq.ItemRecordID 
    AND nq.NotificationTypeID IN (1, 7, 12, 13)  -- Overdue types
-- Notification log (aggregate)
LEFT JOIN PolarisTransactions.Polaris.NotificationLog nl 
    ON co.PatronID = nl.PatronID 
    AND nl.NotificationTypeID IN (1, 7, 12, 13)
    AND CAST(nl.NotificationDateTime AS DATE) = CAST(nq.CreationDate AS DATE)
-- Notification history (individual)
LEFT JOIN Results.Polaris.NotificationHistory nh 
    ON co.PatronID = nh.PatronID 
    AND co.ItemRecordID = nh.ItemRecordId 
    AND nh.NotificationTypeId IN (1, 7, 12, 13)
-- Notification type lookup
LEFT JOIN Polaris.Polaris.NotificationTypes nt 
    ON nq.NotificationTypeID = nt.NotificationTypeID
-- Delivery options lookup
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do1 
    ON p.DeliveryOptionID = do1.DeliveryOptionID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do2 
    ON nq.DeliveryOptionID = do2.DeliveryOptionID
-- Status lookup
LEFT JOIN Polaris.Polaris.NotificationStatuses nst 
    ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE p.PatronID = @PatronID
  AND co.LoanPeriodEndDate >= DATEADD(day, -60, GETDATE())  -- Last 60 days
ORDER BY co.CheckOutDate DESC, nq.CreationDate DESC;
```

**This query returns:**
- Complete patron info
- Checkout timeline (checkout date, original due, renewals, current due)
- Days overdue calculation
- Item details (barcode, title, author, call number, material type)
- Queue information
- Delivery attempt details
- Overdue notice type (1st, 2nd, 3rd, almost due)

---

## 📚 RECOMMENDED DATABASE TABLES

### Priority 1: Essential Tables (Must Query)

#### 1. Polaris.Polaris.Patrons
**Why:** Core patron record with contact preferences
**Fields Needed:**
- PatronID *(Primary Key)*
- Barcode
- PatronFullName
- EmailAddress
- DeliveryOptionID *(Preferred notification method)*
- EnableSMS *(SMS opt-in)*
- TxtPhoneNumber *(SMS-specific phone)*
- ExcludeFromOverdue *(Opted out of overdue notices)*
- ExcludeFromBills *(Opted out of bill notices)*

**Query Frequency:** Every verification lookup

---

#### 2. Polaris.Polaris.PatronRegistration
**Why:** Extended patron information including all phone numbers and name components
**Fields Needed:**
- PatronID *(Foreign Key)*
- NameFirst
- NameLast
- NameMiddle
- NameTitle
- PhoneVoice1 *(Primary phone)*
- PhoneVoice2 *(Secondary phone)*
- PhoneVoice3 *(Tertiary phone)*
- PhoneFAX
- LanguageID

**Query Frequency:** Every verification lookup

---

#### 3. Polaris.Polaris.CircItemRecords
**Why:** Complete item information
**Fields Needed:**
- ItemRecordID *(Primary Key)*
- BibliographicRecordID
- Barcode *(Item barcode)*
- CallNumber
- AssignedBranchID
- MaterialTypeID
- ShelvingLocationID
- PriceAmount *(Replacement cost)*
- ItemStatusID

**Query Frequency:** For all item-based notifications

---

#### 4. Polaris.Polaris.SysHoldRequests
**Why:** Hold request details with critical dates
**Fields Needed:**
- SysHoldRequestID *(Primary Key)*
- PatronID
- BibliographicRecordID
- TrappingItemRecordID
- **RequestDate** *(Hold placed date - CRITICAL)*
- **FilledDate** *(Hold filled date - CRITICAL)*
- HoldTillDate *(Expiration)*
- HoldNotificationDate *(When notification sent)*
- PickupOrgID
- DeliveryOptionID
- StatusID
- PatronNotes

**Query Frequency:** For all hold notifications

---

#### 5. Polaris.Polaris.ItemCheckouts
**Why:** Current checkout information with renewal data
**Fields Needed:**
- ItemRecordID *(Foreign Key)*
- PatronID *(Foreign Key)*
- **CheckOutDate** *(CRITICAL for timeline)*
- **DueDate** *(Original due date)*
- **RenewalCount** *(Number of renewals)*
- **LastRenewalDate** *(Most recent renewal)*
- LoanPeriodEndDate *(Current due date)*
- OrganizationID *(Checkout branch)*

**Query Frequency:** For all overdue notifications

---

### Priority 2: Supporting Tables (Optional but Helpful)

#### 6. Polaris.Polaris.BibliographicRecords
**Why:** Complete bibliographic information
**Fields Needed:**
- BibliographicRecordID
- BrowseTitle
- BrowseAuthor
- Publisher
- PublicationYear
- Edition
- ISBN

**Query Frequency:** When item details needed

---

#### 7. Polaris.Polaris.Organizations
**Why:** Branch names and details
**Fields Needed:**
- OrganizationID
- Name
- Abbreviation
- OrganizationCodeID

**Query Frequency:** For human-readable branch names

---

#### 8. Polaris.Polaris.MaterialTypes
**Why:** Material type descriptions (Book, DVD, etc.)
**Fields Needed:**
- MaterialTypeID
- Description

**Query Frequency:** When material type needed

---

#### 9. Polaris.Polaris.NotificationStatuses
**Why:** Status code descriptions
**Fields Needed:**
- NotificationStatusID
- Description

**Query Frequency:** For human-readable status

---

#### 10. Polaris.Polaris.SA_DeliveryOptions
**Why:** Delivery method descriptions
**Fields Needed:**
- DeliveryOptionID
- Description

**Query Frequency:** For human-readable delivery methods

---

### Priority 3: Historical Tables (If Available)

#### 11. Polaris.Polaris.TransactionHistory
**Why:** Detailed renewal history (if logged)
**Fields Needed:**
- TransactionID
- PatronID
- ItemRecordID
- TransactionDate *(Each renewal date)*
- TransactionTypeID *(Filter for renewals)*

**Query Frequency:** When detailed renewal timeline needed
**Note:** Not all Polaris systems log renewals here

---

## 🏗️ DATA INTEGRATION ARCHITECTURE

### Recommended Approach: Hybrid Data Model

```
┌─────────────────────────────────────────────────────────────┐
│                    USER INTERFACE                            │
│  (Web-based Verification Tool - Laravel/PHP or Python/Flask) │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   DATA LAYER (PHP/Python)                    │
│  • Queries Polaris SQL Server (direct connection)           │
│  • Parses Shoutbomb submission/failure files (FTP)          │
│  • Optionally calls Polaris API for real-time data          │
└─────────────────────────────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Polaris   │    │  Shoutbomb  │    │   Polaris   │
│  SQL Server │    │  FTP Files  │    │     API     │
│             │    │             │    │  (optional)  │
│ • Patrons   │    │ • holds.txt │    │ • REST      │
│ • CircItems │    │ • overdue   │    │ • Real-time │
│ • Holds     │    │ • failures  │    │             │
│ • Checkouts │    │             │    │             │
└─────────────┘    └─────────────┘    └─────────────┘
```

---

### Data Flow for Verification Lookup

**User Action:** Staff searches for patron by barcode or phone number

**Step 1: Fetch Patron Information**
```sql
Query: Patrons + PatronRegistration tables
Returns: Complete patron profile with all contact info
```

**Step 2: Fetch Notification History**
```sql
Query: NotificationQueue + NotificationLog + NotificationHistory
Returns: All notification attempts in last 30-60 days
```

**Step 3: Fetch Context for Each Notification**

For Hold Notifications:
```sql
Query: SysHoldRequests + HoldNotices + CircItemRecords
Returns: Hold placed date, filled date, item details
```

For Overdue Notifications:
```sql
Query: ItemCheckouts + CircItemRecords + BibliographicRecords
Returns: Checkout date, renewal dates, item details
```

**Step 4: Cross-reference with Shoutbomb Files**
```
Parse: holds_submitted_YYYY-MM-DD.txt
Check: Was this notification in the submission file?
```

**Step 5: Check Delivery Status**
```
Parse: shoutbomb_invalid_phones_YYYY-MM-DD.txt
Parse: shoutbomb_voice_failures_YYYY-MM-DD.txt
Check: Did Shoutbomb report any failures?
```

**Step 6: Compile Complete Timeline**
```
Display:
- Hold/Checkout Timeline
- Notification Queue Timeline
- Submission to Shoutbomb
- Delivery Status
- Any Gaps or Failures
```

---

## 🛠️ IMPLEMENTATION ROADMAP

### Phase 1: Basic SQL Integration (Week 1-2)

**Goal:** Get core queries working

**Tasks:**
1. ✅ Set up SQL Server connection from web app
2. ✅ Test Query Q1.1 (Patron Contact Details)
3. ✅ Test Query Q3.1 (Hold Request Details)
4. ✅ Test Query Q2.1 (Checkout Details)
5. ✅ Test Query Q5.1 (Complete Hold Notification History)
6. ✅ Test Query Q5.2 (Complete Overdue Notification History)

**Deliverable:** Working SQL queries that return all needed fields

---

### Phase 2: File Parsing Integration (Week 2-3)

**Goal:** Parse Shoutbomb submission and failure files

**Tasks:**
1. ✅ Read holds_submitted.txt files
2. ✅ Read overdue_submitted.txt files
3. ✅ Read patron files (text_patrons, voice_patrons)
4. ✅ Read failure files (invalid_phones, voice_failures)
5. ✅ Create cross-reference index (PatronBarcode → Submission Records)

**Deliverable:** Parser that can check if notification was submitted

---

### Phase 3: Web UI Development (Week 3-4)

**Goal:** Build user-friendly verification interface

**Tasks:**
1. ✅ Patron search form (barcode, phone, name)
2. ✅ Display patron info
3. ✅ Display notification timeline
4. ✅ Display item/hold details
5. ✅ Highlight failures or missing notifications
6. ✅ Export verification report (PDF/Excel)

**Deliverable:** Working web app for staff use

---

### Phase 4: Automated Monitoring (Week 4-5)

**Goal:** Proactive notification tracking

**Tasks:**
1. ✅ Daily check: Notifications in queue vs submitted to Shoutbomb
2. ✅ Daily check: Submitted to Shoutbomb vs delivered successfully
3. ✅ Alert on high failure rates
4. ✅ Alert on notifications stuck in queue
5. ✅ Weekly summary report

**Deliverable:** Automated monitoring system

---

### Phase 5: API Integration (Optional - Week 5-6)

**Goal:** Real-time status updates

**Tasks:**
1. ✅ Implement Polaris API authentication (OAuth)
2. ✅ Use API to fetch notification queue
3. ✅ Use API to update notification status
4. ✅ Automate Shoutbomb delivery status feedback

**Deliverable:** Bi-directional sync with Polaris

---

## 📋 EXAMPLE VERIFICATION REPORT

Here's what a complete verification report should include:

```
═══════════════════════════════════════════════════════════════
PATRON NOTIFICATION VERIFICATION REPORT
Generated: 2025-11-19 15:30:00
═══════════════════════════════════════════════════════════════

PATRON INFORMATION
──────────────────────────────────────────────────────────────
Patron ID:       200001
Barcode:         21234567890001
Name:            Richard Cooper
Email:           rcooper@example.com
Phone (Primary): 270-555-0101
Phone (SMS):     270-555-0101
Preferred:       SMS (Text Message)
Language:        English
SMS Opt-in:      Yes
Exclusions:      None

HOLD NOTIFICATION - ITEM: The Midnight Library
──────────────────────────────────────────────────────────────
Hold Request ID: 500001
Item Barcode:    31234567890001
Title:           The Midnight Library
Author:          Matt Haig
Call Number:     823.92 HAI
Material Type:   Book

TIMELINE:
  [2025-10-28 14:30] Hold Placed at Central Library
  [2025-11-03 09:15] Hold Filled - Item trapped
  [2025-11-03 10:00] Notification Queued (SMS)
  [2025-11-04 08:00] Submitted to Shoutbomb (holds.txt)
  [2025-11-04 08:15] ✓ Delivered successfully (SMS)
  
Pickup Branch:   Central Library (CENTR)
Hold Expires:    2025-11-08 23:59

DELIVERY STATUS: ✓ SUCCESS
Status:          Delivered (SMS) - Status ID 3
Sent to:         270-555-0101
Shoutbomb:       In submission file holds_submitted_2025-11-04_08-00-01.txt
Failures:        None

OVERDUE NOTIFICATION - ITEM: Project Hail Mary
──────────────────────────────────────────────────────────────
Item Barcode:    31234567890005
Title:           Project Hail Mary
Author:          Andy Weir
Call Number:     SF WEI
Material Type:   Book

TIMELINE:
  [2025-10-01 10:00] Checked out at Cloverport
  [2025-10-22 23:59] Original due date
  [2025-10-15 14:00] Renewed (1st time)
  [2025-10-29 23:59] Current due date (after renewal)
  [2025-11-04 08:04] Notification Queued (1st Overdue)
  [2025-11-04 08:15] Submitted to Shoutbomb (overdue.txt)
  [2025-11-04 08:20] ⚠ FAILED - Invalid phone number

Days Overdue:    6 days
Renewals:        1
Fine Amount:     $3.00

DELIVERY STATUS: ⚠ FAILED
Status:          Failed - Invalid Phone (Status ID 4)
Attempted to:    270-555-9999
Shoutbomb:       In submission file overdue_submitted_2025-11-04_08-04-01.txt
Failures:        Listed in shoutbomb_invalid_phones_2025-11-05.txt

ISSUE IDENTIFIED:
  Phone number 270-555-9999 is invalid or disconnected.
  Patron's current phone in system: 270-555-0101
  
RECOMMENDED ACTION:
  ✓ Update patron record with correct phone number
  ✓ Manually notify patron about overdue item
  ✓ Verify patron received notification via alternate method

═══════════════════════════════════════════════════════════════
END OF REPORT
═══════════════════════════════════════════════════════════════
```

---

## ✅ VALIDATION CHECKLIST

Use this checklist when implementing:

### Data Completeness
- [ ] Can retrieve patron's full name
- [ ] Can retrieve all patron phone numbers (Voice1, Voice2, Voice3)
- [ ] Can retrieve patron email address
- [ ] Can retrieve patron's SMS opt-in status
- [ ] Can retrieve patron's delivery preference
- [ ] Can retrieve checkout date for overdue items
- [ ] Can retrieve renewal count and dates
- [ ] Can retrieve hold placed date
- [ ] Can retrieve hold filled date
- [ ] Can retrieve item barcode for all notifications
- [ ] Can retrieve item call number
- [ ] Can retrieve item author

### Data Accuracy
- [ ] PatronID matches across all tables
- [ ] ItemRecordID matches across all tables
- [ ] Phone numbers format is consistent
- [ ] Dates are in correct timezone
- [ ] Status codes match lookup tables

### Timeline Completeness
- [ ] Can show complete hold timeline (placed → filled → notified → delivered)
- [ ] Can show complete checkout timeline (checkout → renewals → overdue → notified)
- [ ] Can identify gaps in notification process
- [ ] Can calculate days overdue
- [ ] Can calculate days until hold expiration

### Cross-Reference Capability
- [ ] Can match NotificationQueue to submission files
- [ ] Can match submission files to Shoutbomb failure reports
- [ ] Can link PatronID to PatronBarcode
- [ ] Can link ItemRecordID to ItemBarcode
- [ ] Can link SysHoldRequestID to NotificationQueue

---

## 📞 SUPPORT & CONTACTS

**Database Access:**
- SQL Server: POLARIS-SQL
- Database: Polaris, PolarisTransactions, Results
- Schema: Polaris

**Shoutbomb FTP:**
- Submission files: /outbound/
- Failure reports: /inbound/reports/

**Documentation:**
- This document: DATA_INTEGRATION_STRATEGY.md
- Table docs: TABLE_*.md files
- Lookup tables: POLARIS_LOOKUP_TABLES.md

**System Owner:**
Brian Lashbrook  
Daviess County Public Library  
blashbrook@dcplibrary.org

---

## 📝 REVISION HISTORY

| Date | Version | Changes |
|------|---------|---------|
| 2025-11-19 | 1.0 | Initial data integration strategy document |

---

**Last Updated:** November 19, 2025
