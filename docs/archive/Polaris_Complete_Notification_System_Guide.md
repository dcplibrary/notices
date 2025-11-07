# Complete Polaris Notification System Guide
## Daviess County Public Library

**Document Version:** 2.0  
**Last Updated:** November 5, 2025  
**Database System:** Polaris ILS

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Database Architecture](#database-architecture)
3. [Reference Data](#reference-data)
4. [Notice Generation Tables](#notice-generation-tables)
5. [Core Data Tables](#core-data-tables)
6. [Transaction & Logging Tables](#transaction--logging-tables)
7. [Views & Integration](#views--integration)
8. [Monitoring Strategies](#monitoring-strategies)
9. [Data Flow Diagrams](#data-flow-diagrams)
10. [Export Processes](#export-processes)
11. [Troubleshooting Guide](#troubleshooting-guide)

---

## System Overview

The Polaris notification system spans **three databases** and manages **nine distinct notice types** through multiple delivery channels. The system processes thousands of notices daily, tracking everything from hold notifications to billing reminders.

### Key Statistics (from your sample data)
- **Active Patrons**: Varies (sample shows PatronIDs in 100,000+ range)
- **Daily Hold Notices**: ~50-100 per day
- **Daily Overdue Notices**: ~50-75 per day
- **Daily Circulation Reminders**: 1,700+ per day
- **Active Delivery Methods**: 8 (Email, SMS, Phone, Mail, etc.)

---

## Database Architecture

### **1. Results.Polaris** - Notice Generation & Staging
**Purpose**: Temporary staging tables for generating and processing notices

| Table | Purpose | Key Fields |
|-------|---------|------------|
| NotificationQueue | Master queue for all pending notices | NotificationQueueID, PatronID, NotificationTypeID, Processed |
| OverdueNotices | Overdue item notices | OverdueNoticeID, PatronID, DueDate, BillingNotice |
| HoldNotices | Hold ready notifications | PatronID, ItemRecordID, HoldTillDate, PickupOrganizationID |
| FineNotices | Fine notice summaries | PatronID, TotalOutstandingCharges, TxnID |
| FineNoticesReport | Detailed fine line items | TxnID, PatronID, Amount, ItemRecordID |
| ManualBillNotices | Staff-generated bills | TxnID, PatronID, Amount, FeeReasonCodeID |
| CircReminders | Courtesy/almost-overdue reminders | CircReminderID, PatronID, DueDate, AutoRenewal |
| NotificationHistory | Historical tracking of sent notices | PatronId, NotificationTypeId, NoticeDate, NotificationStatusId |

### **2. Polaris.Polaris** - Core System Data
**Purpose**: Master data tables containing patron, item, and hold information

**Patron Tables**:
- Patrons - Core patron records
- PatronRegistration - Extended patron information
- PatronAddresses - Patron address linkages
- Addresses - Physical addresses
- AddressTypes, AddressLabels - Address metadata
- PatronFineNotices - Fine notice tracking

**Hold Tables**:
- SysHoldRequests - Active hold requests
- SysHoldHistory - Complete hold history
- SysHoldHistoryDaily - Daily hold history subset
- SysHoldStatuses - Hold status reference

**Item Tables**:
- CircReserveItemRecords_View - Combined circulation/reserve view
- ItemStatuses - Item status reference

**Notification Configuration**:
- NotificationTypes - Notice type reference
- NotificationStatuses - Delivery status reference
- SA_DeliveryOptions - Delivery method configuration
- DeliveryOptions - Active delivery options view

**Views** (Bridge to Results database):
- ViewHoldNotices - Complete hold notice data with patron info
- ViewHoldNoticesData - Item-level hold data
- ViewOverdueNoticesData - Overdue item details
- ViewManualBillNoticesData - Manual bill details
- ML_V1_Holds - Machine-readable hold view
- ML_V1_Patrons_Holds - Patron hold view for API

### **3. PolarisTransactions.Polaris** - Transaction Logging
**Purpose**: Permanent transaction and notification logs

| Table | Purpose | Key Fields |
|-------|---------|------------|
| NotificationLog | Complete log of all sent notifications | NotificationLogID, PatronID, NotificationDateTime, NotificationStatusID |
| TransactionTypes | Transaction type reference | TransactionTypeID, TransactionTypeDescription |
| TransactionSubTypes | Transaction subtype reference | TransactionSubTypeID, TransactionSubTypeDescription |

---

## Reference Data

### Notification Types
Complete mapping of NotificationTypeID values:

| ID | Type | Description | Typical Delivery |
|----|------|-------------|------------------|
| 0 | Combined | Combined notice with multiple types | Email, Mail |
| 1 | 1st Overdue | First overdue notice | Email, SMS, Mail |
| 2 | Hold | Item ready for pickup | Email, SMS, Phone, Mail |
| 3 | Cancel | Hold cancelled | Email, SMS |
| 4 | Recall | Item recalled | Email, Mail |
| 5 | All | All types combined | Varies |
| 6 | Route | Routing notice | Internal |
| 7 | Almost Overdue/Auto-renew | Courtesy reminder | Email, SMS |
| 8 | Fine | Outstanding fines notice | Email, Mail |
| 9 | Inactive Reminder | Inactive account reminder | Email, Mail |
| 10 | Expiration Reminder | Card expiration warning | Email, Mail |
| 11 | Bill | Bill notice | Mail |
| 12 | 2nd Overdue | Second overdue notice | Email, Mail |
| 13 | 3rd Overdue | Third overdue/billing notice | Mail |
| 14 | Serial Claim | Serial claiming notice | Internal |
| 15 | Polaris Fusion | Access request responses | Email |
| 16 | Course Reserves | Course reserve notices | Email |
| 17 | Borrow-By-Mail Failure | BBM failure notification | Email, Mail |
| 18 | 2nd Hold | Second hold notification | Email, SMS |
| 19 | Missing Part | Missing part notice | Internal |
| 20 | Manual Bill | Manually generated bill | Mail, Email |
| 21 | 2nd Fine Notice | Second fine notice | Email, Mail |

### Delivery Options
Complete mapping of DeliveryOptionID values:

| ID | Method | Status | Description |
|----|--------|--------|-------------|
| 1 | Mailing Address | Enabled | Physical mail via USPS |
| 2 | Email Address | Enabled | Email notification |
| 3 | Phone 1 | Enabled | Voice call to primary phone |
| 4 | Phone 2 | Enabled | Voice call to secondary phone |
| 5 | Phone 3 | Enabled | Voice call to tertiary phone |
| 6 | FAX | Enabled | Fax transmission |
| 7 | EDI | Enabled | Electronic Data Interchange |
| 8 | TXT Messaging | Enabled | SMS text message |
| 9 | Mobile App | Disabled | Push notification (not active) |

### Notification Statuses
Complete mapping of NotificationStatusID values:

| ID | Status | Type | Description |
|----|--------|------|-------------|
| 1 | Call completed - Voice | Success | Person answered |
| 2 | Call completed - Answering machine | Success | Message left |
| 3 | Call not completed - Hang up | Failure | Recipient hung up |
| 4 | Call not completed - Busy | Failure | Line was busy |
| 5 | Call not completed - No answer | Failure | No one answered |
| 6 | Call not completed - No ring | Failure | No ring detected |
| 7 | Call failed - No dial tone | Failure | System issue |
| 8 | Call failed - Intercept tones heard | Failure | Invalid number |
| 9 | Call failed - Probable bad phone number | Failure | Bad number |
| 10 | Call failed - Maximum retries exceeded | Failure | Too many attempts |
| 11 | Call failed - Undetermined error | Failure | Unknown error |
| 12 | Email Completed | Success | Email sent successfully |
| 13 | Email Failed - Invalid address | Failure | Bad email address |
| 14 | Email Failed | Failure | General email failure |
| 15 | Mail Printed | Success | Physical mail prepared |
| 16 | Sent | Success | Generic success status |

### Item Statuses (Sample)
From Polaris.ItemStatuses table:

| StatusID | Description | Availability |
|----------|-------------|--------------|
| 1 | In | Available for checkout |
| 2 | Out | Checked out |
| 3 | On Hold Shelf | Awaiting pickup |
| 4 | In Transit | Moving between locations |
| 5 | Missing | Cannot locate |
| 6 | Lost | Declared lost |
| ... | ... | ... |

### Hold Statuses (Sample)
From Polaris.SysHoldStatuses table - typical values:

| StatusID | Description | Active |
|----------|-------------|--------|
| 1 | Active | Yes |
| 2 | Pending | Yes |
| 3 | Inactive/Suspended | No |
| 4 | Expired | No |
| 5 | Shipped | Yes |
| 6 | Held | Yes (on shelf) |
| ... | ... | ... |

---

## Notice Generation Tables (Results.Polaris)

### 1. NotificationQueue
**Purpose**: Central queue tracking all pending notifications across all types

**Schema**:
```sql
CREATE TABLE [Polaris].[NotificationQueue](
    [NotificationQueueID] int IDENTITY(1,1) PRIMARY KEY,
    [ItemRecordID] int NULL,
    [NotificationTypeID] int NOT NULL,
    [PatronID] int NOT NULL,
    [DeliveryOptionID] int NOT NULL,
    [Processed] bit NOT NULL DEFAULT (0),
    [MinorPatronID] int NULL,
    [ReportingOrgID] int NULL,
    [Amount] money NULL,
    [CreationDate] datetime NULL DEFAULT (getdate()),
    [IsAdditionalTxt] bit NULL DEFAULT (0)
)
```

**Key Insights from Sample Data**:
- Note: Your sample CSV appears to contain OverdueNotices data rather than NotificationQueue data
- Verify correct export query for this table

**Usage**:
- Acts as the master queue for notice processing
- Links to specific notice types through NotificationTypeID
- `Processed = 0` indicates pending notices
- `Processed = 1` indicates completed notices

---

### 2. OverdueNotices
**Purpose**: Tracks overdue items and generates overdoverdue notices

**Schema**:
```sql
CREATE TABLE [Polaris].[OverdueNotices](
    [OverdueNoticeID] int IDENTITY(1,1) PRIMARY KEY,
    [ItemRecordID] int NOT NULL,
    [PatronID] int NOT NULL,
    [ItemBarcode] nvarchar(20) NULL,
    [DueDate] datetime NULL,
    [BrowseTitle] nvarchar(255) NULL,
    [BrowseAuthor] nvarchar(255) NULL,
    [ItemCallNumber] nvarchar(370) NULL,
    [Price] money NULL,
    [Abbreviation] nvarchar(15) NULL,
    [Name] nvarchar(50) NULL,
    [PhoneNumberOne] nvarchar(20) NULL,
    [LoaningOrganizationID] int NULL,
    [FineCodeID] int NULL,
    [LoanUnits] int NULL,
    [BillingNotice] tinyint NULL,
    [ReplacementCost] money NULL,
    [OverdueCharge] money NULL,
    [ReportingOrgID] int NULL,
    [DeliveryOptionID] int NULL,
    [ReturnAddressOrgID] int NULL,
    [NotificationTypeID] int NULL,
    [IncludeClaimedItems] bit NOT NULL DEFAULT (0),
    [ProcessingCharge] money NULL,
    [AdminLanguageID] int NULL,
    [BaseProcessingCharge] money NULL,
    [BaseReplacementCost] money NULL
)
```

**Key Insights from Sample Data**:
- 12 sample records showing current overdues
- All records show $0.00 charges (early notices)
- Overdue ranging from 1-29 days past due (as of 11/5/2025)
- All using DeliveryOptionID=8 (SMS)
- PatronID 41613 has 5 overdue items

**BillingNotice Escalation**:
- 0 = First notice (courtesy)
- 1 = Second notice
- 2 = Third notice (billing/final)

---

### 3. HoldNotices
**Purpose**: Notifies patrons when requested items are ready for pickup

**Schema**:
```sql
CREATE TABLE [Polaris].[HoldNotices](
    [ItemRecordID] int NOT NULL,
    [AssignedBranchID] int NOT NULL,
    [PickupOrganizationID] int NOT NULL,
    [PatronID] int NOT NULL,
    [ItemBarcode] nvarchar(20) NOT NULL,
    [BrowseTitle] nvarchar(255) NULL,
    [BrowseAuthor] nvarchar(255) NULL,
    [ItemCallNumber] nvarchar(370) NULL,
    [Price] money NULL,
    [Abbreviation] nvarchar(15) NULL,
    [Name] nvarchar(50) NULL,
    [PhoneNumberOne] nvarchar(20) NULL,
    [DeliveryOptionID] int NULL DEFAULT (1),
    [HoldTillDate] datetime NULL,
    [ItemFormatID] int NULL,
    [AdminLanguageID] int NULL,
    [NotificationTypeID] int NULL,
    [HoldPickupAreaID] int NULL DEFAULT (0)
)
```

**Key Insights from Sample Data**:
- 11 holds ready for pickup at DCPL (OrgID=3)
- Expiration dates: mostly 11/6 and 11/8/2025
- Mix of delivery methods:
  - DeliveryOptionID=2 (Email)
  - DeliveryOptionID=8 (SMS)
- Items span various formats (books, music, etc.)

**Hold Shelf Management**:
- `HoldTillDate` is when the hold expires
- Default hold period visible in sample (typically 3-5 days)
- All NotificationTypeID=2 (Hold Ready notices)

---

### 4. FineNotices
**Purpose**: Summary-level fine notices for patrons with outstanding charges

**Schema**:
```sql
CREATE TABLE [Polaris].[FineNotices](
    [PatronID] int NOT NULL,
    [ItemRelatedCharges] money NULL,
    [NonItemRelatedCharges] money NULL,
    [Credit] money NULL,
    [TotalOutstandingCharges] money NULL,
    [PrimaryPatronID] int NULL,
    [DeliveryOptionID] int NULL,
    [ReportingOrgID] int NULL,
    [TxnID] int NULL,
    [IsAdditionalTxt] bit NULL DEFAULT (0),
    [NotificationTypeID] int NULL DEFAULT (8)
)
```

**Key Insights from Sample Data**:
- Multiple transactions per patron (same PatronID appears multiple times)
- Each TxnID represents a separate notice generation event
- Sample balances range from $25.69 to $43.72
- All using DeliveryOptionID=8 (SMS)
- TxnIDs link to FineNoticesReport for line-item detail

**Charge Breakdown**:
- `ItemRelatedCharges`: Replacement costs, overdue fines for specific items
- `NonItemRelatedCharges`: Processing fees, postage, admin charges
- `Credit`: Any credits on account
- `TotalOutstandingCharges`: Net amount owed

---

### 5. FineNoticesReport
**Purpose**: Detailed, line-item breakdown of all charges in fine notices

**Schema**:
```sql
CREATE TABLE [Polaris].[FineNoticesReport](
    [PatronID] int NOT NULL,
    [PatronName] nvarchar(165) NULL,
    [ReportingOrgID] int NOT NULL,
    [ReturnAddrOrgID] int NULL,
    [ReportingOrgName] nvarchar(80) NULL,
    [ReportingOrgAbbreviation] nvarchar(15) NULL,
    [ReportingOrgPhoneVoice] nvarchar(32) NULL,
    [TotalAmountOwed] money NULL,
    [MinorPatronID] int NULL,
    [MinorPatronBarcode] nvarchar(20) NULL,
    [MinorPatronName] nvarchar(165) NULL,
    [ItemRecordID] int NULL,
    [ItemBarcode] nvarchar(20) NULL,
    [CallNumber] nvarchar(370) NULL,
    [Author] nvarchar(400) NULL,
    [Title] nvarchar(600) NULL,
    [TxnDate] datetime NULL,
    [Amount] money NULL,
    [OwedOrganizationID] int NULL,
    [OwedOrganizationAbbr] nvarchar(15) NULL,
    [DeliveryOptionID] int NULL,
    [PatronAddressID] int NULL,
    [PatronAddress] nvarchar(400) NULL,
    [ReportingOrgAddress] nvarchar(400) NULL,
    [PatronCity] nvarchar(32) NULL,
    [PatronZip] nvarchar(17) NULL,
    [AdminLanguageID] int NULL,
    [TxnID] int NULL
)
```

**Key Insights from Sample Data** (1,523 line items):
- Each patron's charges broken down by item
- Typical charge pattern:
  - Item replacement charge (varies by item price)
  - $3.00 processing fee per item
  - $0.64-$0.74 postage fee
- Historical charges from Feb 2024 through Nov 2025
- Some items show "[DELETED]" indicating removed catalog records

**Charge Assessment Timeline**:
- Charges assessed when items declared lost or damaged
- Processing fees added at time of billing
- Postage fees added when physical notice is mailed

---

### 6. ManualBillNotices
**Purpose**: Manually-generated billing notices for lost/damaged items or other charges

**Schema**:
```sql
CREATE TABLE [Polaris].[ManualBillNotices](
    [TxnID] int NULL,
    [ItemRecordID] int NULL,
    [PatronID] int NULL,
    [ItemBarcode] nvarchar](20) NULL,
    [BrowseTitle] nvarchar(255) NULL,
    [BrowseAuthor] nvarchar(255) NULL,
    [ItemCallNumber] nvarchar(370) NULL,
    [Abbreviation] nvarchar(15) NULL,
    [Name] nvarchar(50) NULL,
    [PhoneNumber] nvarchar(20) NULL,
    [ReportingOrgID] int NULL,
    [DeliveryOptionID] int NULL,
    [ReturnAddressOrgID] int NULL,
    [NotificationTypeID] int NULL,
    [AdminLanguageID] int NULL,
    [MaterialTypeID] int NULL,
    [FeeReasonCodeID] int NULL,
    [AddedMessage] nvarchar(255) NULL,
    [ChargingLibraryID] int NULL,
    [TxnDate] datetime NULL,
    [Amount] money NULL
)
```

**Key Insights from Sample Data** (10 records, all from 11/5/2025):
- Each patron bill includes:
  - Item replacement cost (FeeReasonCodeID=-1)
  - $3.00 processing fee (FeeReasonCodeID=-6)
- Standard message: "Your library account has been billed for this charge."
- All using DeliveryOptionID=1 (Paper mail)
- Charges range from $9.99 to $17.99 per item

**Fee Reason Codes**:
- -1 = Lost/Replacement charge
- -6 = Processing fee
- Other codes for various charge types

---

### 7. CircReminders
**Purpose**: Courtesy reminders for items due soon and auto-renewal notifications

**Schema** (inferred from SQL query):
```sql
-- CircReminders structure
CircReminderID int PRIMARY KEY,
NotificationTypeID int,  -- Always 7 for almost overdue
ReportingOrgID int,
ReportingOrgName nvarchar(80),
ReportingOrgAddress nvarchar(400),
PatronID int,
PatronName nvarchar(165),
EmailAddress nvarchar(64),
PatronAddress nvarchar(400),
PhoneVoice nvarchar(20),
EmailFormatID int,
ItemRecordID int,
DueDate datetime,
BrowseTitle nvarchar(255),
ItemFormat nvarchar(50),
ItemAssignedBranch nvarchar(80),
Renewals int,
ReportingOrgPhone nvarchar(32),
AdminLanguageID int,
DeliveryOptionID int,
IsAdditionalTxt bit,
AutoRenewal bit,
CreationDate datetime
```

**Key Insights from Sample Data** (1,700+ records from 11/4/2025):
- Massive volume: ~1,700 reminders generated daily
- NotificationTypeID=7 (Almost overdue/Auto-renew reminder)
- Most delivered via SMS (DeliveryOptionID=8)
- Some via Email (DeliveryOptionID=2)
- Items typically due 3 days out (11/7/2025 in sample)
- AutoRenewal flag indicates if item was auto-renewed
- Shows renewal count per item

**Email Format**:
- Many patrons have special SMS addresses like: `2703149477@unspecified.carrier`
- Some have standard email addresses
- SMS addresses use phone number @ carrier domain

---

### 8. NotificationHistory
**Purpose**: Historical tracking of all sent notices for audit and reporting

**Schema** (inferred from SQL query):
```sql
PatronId int,
ItemRecordId int,
TxnId int,
NotificationTypeId int,
ReportingOrgId int,
DeliveryOptionId int,
NoticeDate datetime,
Amount money,
NotificationStatusId int,
Title nvarchar(600)
```

**Key Insights from Sample Data** (4,734 records):
- Comprehensive log of sent notices
- Includes notification status for delivery tracking
- Tracks amount for billing-related notices
- Title included for item-specific notices
- Sample shows mostly Hold notices (NotificationTypeID=2)
- All successful deliveries (NotificationStatusID=12, Email Completed)
- Time-stamped throughout the day (multiple batches)

**Retention**:
- Historical data retained for audit purposes
- Queryable for patron notification history
- Useful for delivery success/failure analysis

---

## Core Data Tables (Polaris.Polaris)

### Patron Structure

#### 1. Patrons (Core Record)
**Schema**:
```sql
CREATE TABLE [Polaris].[Patrons] (
    [PatronID] int IDENTITY PRIMARY KEY,
    [PatronCodeID] int,
    [OrganizationID] int,
    [CreatorID] int,
    [ModifierID] int,
    [Barcode] nvarchar(20),
    [SystemBlocks] int DEFAULT (0),
    [YTDCircCount] int DEFAULT (0),
    [LifetimeCircCount] int DEFAULT (0),
    [LastActivityDate] datetime,
    [ClaimCount] int DEFAULT (0),
    [LostItemCount] int DEFAULT (0),
    [ChargesAmount] money DEFAULT (0),
    [CreditsAmount] money DEFAULT (0),
    [RecordStatusID] int DEFAULT (1),
    [RecordStatusDate] datetime DEFAULT (getdate()),
    [YTDYouSavedAmount] money DEFAULT (0),
    [LifetimeYouSavedAmount] money DEFAULT (0)
)
```

**Key Fields for Notifications**:
- `PatronID`: Primary identifier used across all notice tables
- `Barcode`: Patron barcode (often starts with "23307" for DCPL)
- `OrganizationID`: Home branch
- `ChargesAmount`: Current outstanding charges
- `RecordStatusID`: Account status (1=Active)

#### 2. PatronRegistration (Extended Information)
**Schema**: (see document 38 for full schema)

**Key Fields for Notifications**:
- `EmailAddress`: Primary email for notifications
- `PhoneVoice1`, `PhoneVoice2`, `PhoneVoice3`: Phone numbers
- `DeliveryOptionID`: Patron's preferred delivery method
- `Phone1CarrierID`, `Phone2CarrierID`, `Phone3CarrierID`: SMS carrier info
- `EnableSMS`: SMS opt-in flag
- `EnablePush`: Push notification opt-in flag
- `ExcludeFromOverdues`, `ExcludeFromHolds`, `ExcludeFromBills`: Notice exclusion flags
- `AdminLanguageID`: Language preference (1033=English)
- `EmailFormatID`: Email format preference (HTML vs plain text)

#### 3. PatronAddresses
**Schema**:
```sql
CREATE TABLE [Polaris].[PatronAddresses] (
    [PatronID] int,
    [AddressID] int,
    [AddressTypeID] int,
    [Verified] bit DEFAULT (0),
    [VerificationDate] datetime,
    [PolarisUserID] int,
    [AddressLabelID] int DEFAULT (1)
)
```

**Address Types**:
- 1 = Primary/Mailing Address
- 2 = Alternate Address  
- 3 = Seasonal Address

#### 4. Addresses
**Schema**:
```sql
CREATE TABLE [Polaris].[Addresses] (
    [AddressID] int IDENTITY PRIMARY KEY,
    [PostalCodeID] int,
    [StreetOne] nvarchar(64),
    [StreetTwo] nvarchar(64),
    [ZipPlusFour] nvarchar(4),
    [MunicipalityName] nvarchar(64),
    [StreetThree] nvarchar(64)
)
```

### Hold Structure

#### 1. SysHoldRequests (Active Holds)
**Schema**: (see document 29 for full schema - extensive table)

**Key Fields**:
- `SysHoldRequestID`: Primary hold identifier
- `PatronID`: Requesting patron
- `PickupBranchID`: Pickup location
- `SysHoldStatusID`: Current hold status
- `BibliographicRecordID`: Requested title
- `TrappingItemRecordID`: Specific item (if trapped)
- `HoldTillDate`: Hold expiration date
- `HoldNotificationDate`: When notice was sent
- `DeliveryOptionID`: Notification delivery method
- `Suspended`: Hold suspension flag
- `CreationDate`, `ActivationDate`: Timeline tracking

#### 2. SysHoldHistory & SysHoldHistoryDaily
Tracks all hold status changes over time for reporting and analysis.

### Item Structure

#### CircReserveItemRecords_View
**Purpose**: Combined view of circulation and reserve items

**Key Fields**:
- `ItemRecordID`: Item identifier
- `Barcode`: Item barcode
- `ItemStatusID`: Current status
- `AssignedBranchID`: Owning branch
- `AssignedCollectionID`: Collection assignment
- `MaterialTypeID`: Material type
- `Holdable`: Can be placed on hold
- `Price`: Replacement cost

---

## Transaction & Logging Tables (PolarisTransactions.Polaris)

### NotificationLog
**Purpose**: Comprehensive permanent log of all sent notifications

**Schema**:
```sql
CREATE TABLE [Polaris].[NotificationLog] (
    [NotificationLogID] int IDENTITY PRIMARY KEY,
    [PatronID] int,
    [NotificationDateTime] datetime,
    [NotificationTypeID] int,
    [DeliveryOptionID] int,
    [DeliveryString] nvarchar(255),
    [OverduesCount] int,
    [HoldsCount] int,
    [CancelsCount] int,
    [RecallsCount] int,
    [NotificationStatusID] int,
    [Details] nvarchar(255),
    [RoutingsCount] int DEFAULT (0),
    [ReportingOrgID] int,
    [PatronBarcode] nvarchar(20),
    [Reported] bit DEFAULT (0),
    [Overdues2ndCount] int,
    [Overdues3rdCount] int,
    [BillsCount] int,
    [LanguageID] int,
    [CarrierName] nvarchar(255),
    [ManualBillCount] int
)
```

**Key Features**:
- Permanent record (doesn't get cleared)
- Includes delivery status
- Counts multiple notice types in single delivery
- Tracks carrier information for SMS
- Records exact delivery address/phone/email in `DeliveryString`

**Use Cases**:
- Audit trail for patron communications
- Delivery success/failure analysis
- Volume reporting and trending
- Patron communication history

---

## Views & Integration

### Notice Data Views
These views combine data from Results.Polaris notice tables with patron/item data from Polaris.Polaris:

#### 1. ViewHoldNotices
**Purpose**: Complete hold notice data with full patron and organization details

**Key Joins**:
- Patrons + PatronRegistration → Patron details
- ViewPatronAddresses → Mailing address
- Organizations → Branch details
- Results.Polaris.HoldNotices → Notice data

**Usage**: Generate formatted hold notices with complete address information

#### 2. ViewHoldNoticesData
**Purpose**: Item-level details for hold notices

**Key Joins**:
- CircReserveItemRecords_View → Item data
- BibliographicRecords → Title/author
- ItemRecordDetails → Call number, price
- Organizations → Branch information

**Usage**: Populate item information in hold notices

#### 3. ViewOverdueNoticesData
**Purpose**: Complete overdue notice data

**Key Joins**:
- CircReserveItemRecords_View → Item data
- ItemCheckouts → Due date, patron
- BibliographicRecords → Title/author
- ViewPatronRegistration → Patron details including language

**Usage**: Generate overdue notices with complete context

#### 4. ViewManualBillNoticesData
**Purpose**: Manual bill notice data including deleted items

**Key Joins**:
- PatronAccount → Charge details
- CircReserveItemRecords_View → Current item data
- PatronAcctDeletedItemRecords → Deleted item info
- ViewPatronRegistration → Patron details

**Usage**: Generate manual billing notices handling deleted catalog records

### Machine-Readable Views (ML_V1_*)

#### ML_V1_Holds & ML_V1_Patrons_Holds
**Purpose**: API-friendly hold views with normalized data structure

**Key Features**:
- Flattened data structure
- Boolean flags for patron actions (canCancel, canChangePickupLocation, etc.)
- Includes ILL (Interlibrary Loan) holds
- Activation date tracking
- Priority queue information

**Usage**: Powers patron hold displays in public catalog and mobile apps

---

## Data Flow Diagrams

### Notice Generation Flow

```
┌─────────────────────────────────────────────────────────────┐
│                   NOTICE GENERATION PROCESS                  │
└─────────────────────────────────────────────────────────────┘

1. TRIGGER EVENT OCCURS
   ├── Item checked in → Hold ready
   ├── Due date approaching → Courtesy reminder
   ├── Due date passed → Overdue notice
   ├── Balance threshold → Fine notice
   └── Staff action → Manual bill

2. POLARIS.POLARIS QUERIES
   ├── ViewHoldNoticesData      → Pull item details
   ├── ViewOverdueNoticesData   → Pull overdue details  
   ├── PatronRegistration       → Get patron preferences
   └── SysHoldRequests          → Get hold details

3. RESULTS.POLARIS STAGING
   ├── Data inserted into notice tables:
   │   ├── HoldNotices
   │   ├── OverdueNotices
   │   ├── FineNotices / FineNoticesReport
   │   ├── ManualBillNotices
   │   └── CircReminders
   │
   └── NotificationQueue updated:
       ├── NotificationQueueID created
       ├── Processed = 0 (pending)
       └── NotificationTypeID assigned

4. NOTICE PROCESSING
   ├── Query NotificationQueue WHERE Processed=0
   ├── Read associated notice table data
   ├── Apply delivery preferences
   └── Generate notice content

5. DELIVERY EXECUTION
   ├── Email → SMTP delivery
   ├── SMS → SMS gateway (→ PhoneNotices.csv export)
   ├── Voice → Phone system (→ PhoneNotices.csv export)
   └── Mail → Print queue

6. LOGGING & CLEANUP
   ├── PolarisTransactions.NotificationLog
   │   ├── Record delivery attempt
   │   ├── Record status (success/failure)
   │   └── Store delivery details
   │
   ├── Results.NotificationHistory
   │   └── Archive notice metadata
   │
   └── Results.NotificationQueue
       └── Update Processed = 1
```

### Hold Notice Flow (Detailed)

```
HOLD BECOMES AVAILABLE
        ↓
Check SysHoldRequests
├── Status changes to "Held" (StatusID=6)
├── HoldNotificationDate set
└── Trigger notice generation
        ↓
Query ViewHoldNoticesData
├── Get item details (barcode, title, call number)
├── Get branch details (pickup location, phone)
└── Calculate HoldTillDate (pickup deadline)
        ↓
Query PatronRegistration  
├── Get DeliveryOptionID preference
├── Get EmailAddress or Phone
├── Get AdminLanguageID
└── Check exclusion flags
        ↓
Insert into Results.HoldNotices
├── ItemRecordID, PatronID
├── HoldTillDate (e.g., 3-5 days)
├── DeliveryOptionID
└── NotificationTypeID=2
        ↓
Insert into Results.NotificationQueue
├── Links to HoldNotices record
├── Processed=0
└── CreationDate=now()
        ↓
Notice Processing Job Runs
├── Read pending queue items
├── Format notice template
├── DeliveryOptionID=2 → Email
├── DeliveryOptionID=8 → SMS
└── DeliveryOptionID=3 → Voice
        ↓
Send Notice & Log
├── Attempt delivery
├── Update NotificationQueue.Processed=1
├── Insert PolarisTransactions.NotificationLog
│   ├── NotificationStatusID (success/failure)
│   ├── DeliveryString (where it was sent)
│   └── HoldsCount=1
└── Insert Results.NotificationHistory
```

### Overdue Escalation Flow

```
ITEM BECOMES OVERDUE
        ↓
Day 1-7: First Notice
├── Query ViewOverdueNoticesData
├── Insert Results.OverdueNotices
│   ├── BillingNotice=0
│   ├── OverdueCharge=$0.00
│   ├── ReplacementCost=$0.00
│   └── NotificationTypeID=1
├── NotificationQueue entry created
└── Deliver via patron preference
        ↓
Day 8-14: Second Notice (if not returned)
├── Query existing OverdueNotices
├── Update or create new record
│   ├── BillingNotice=1
│   ├── OverdueCharge=calculated
│   ├── NotificationTypeID=12
│   └── May include replacement warning
└── Deliver via patron preference
        ↓
Day 15+: Final Notice/Billing
├── Query existing OverdueNotices
├── Update or create new record
│   ├── BillingNotice=2
│   ├── ReplacementCost=item price
│   ├── ProcessingCharge=$3.00
│   ├── NotificationTypeID=13
│   └── Item declared lost
├── Create ManualBillNotices
│   ├── FeeReasonCodeID=-1 (replacement)
│   └── FeeReasonCodeID=-6 (processing)
├── Update PatronAccount
│   └── Add charges
└── Deliver via mail (typically)
        ↓
Follow-up Fine Notices
├── Query FineNotices summary
├── Generate FineNoticesReport details
├── NotificationTypeID=8 or 21
└── Periodic reminders of balance
```

---

## Export Processes

### PhoneNotices.csv Daily Export
**Purpose**: Export voice and SMS notices for third-party delivery (e.g., Shoutbomb)

**File Format** (comma-delimited, no headers in production export):
```
"V/T", "lang", "NoticeType", "OrgID", "PatronBarcode", " ", "FirstName", "LastName", 
"Phone", "Email", "OrgAbbr", "OrgName", "ItemBarcode", "DueDate/HoldTillDate", 
"Title", "ReportingOrgID", "LanguageID", "NotificationTypeID", "DeliveryOptionID", 
"PatronID", "ItemRecordID", "BibRecordID", " ", " ", " ",
```

**Field Breakdown**:
- Field 1: `"V"` = Voice call, `"T"` = Text message
- Field 2: Language code ("eng")
- Field 3: Notice type ID (as string)
- Field 4: Organization ID
- Field 5: Patron barcode
- Field 7-8: Patron name
- Field 9: Phone number
- Field 10: Email address (may be SMS gateway address like `phone@carrier`)
- Field 13: Item barcode
- Field 14: Due date or hold expiration
- Field 15: Item title
- Field 19: Delivery option ID
- Field 20: Patron ID
- Field 21: Item record ID

**Sample Breakdown**:
```
"V","eng","2","1","23307015084918"," ","ROSEMARY","OBRIEN","2703139600"," ","DCPL",
"Daviess County Public Library","33307008042350","10/8/2025","We are all guilty here : a novel",
"3","1033","2","3","8130","895311","873091"," "," "," ",
```

This is a **Voice (V)** hold notice **(2)** for patron ROSEMARY OBRIEN, phone 270-313-9600, letting them know "We are all guilty here" is ready to pick up, with expiration 10/8/2025.

**Generation Process**:
1. Run daily export query (likely in rpt_exportnotices.sql)
2. Query Results.HoldNotices + ViewHoldNoticesData for voice/SMS notices
3. Query Results.OverdueNotices for voice/SMS overdue notices
4. Format as CSV
5. Save to PhoneNotices.csv
6. Process with Shoutbomb or other system

**Your Alternative Method**:
You mentioned using a different method to export to Shoutbomb. Document your current process for reference.

---

## Monitoring Strategies

### Daily Monitoring Dashboard

**Core Metrics to Track**:

```sql
-- Daily Notice Volume Summary
SELECT 
    CAST(GETDATE() AS DATE) as ReportDate,
    
    -- Queue Status
    (SELECT COUNT(*) FROM Results.Polaris.NotificationQueue 
     WHERE Processed=0) as PendingNotices,
    
    (SELECT COUNT(*) FROM Results.Polaris.NotificationQueue 
     WHERE Processed=1 AND CreationDate >= CAST(GETDATE() AS DATE)) as ProcessedToday,
    
    -- Holds
    (SELECT COUNT(*) FROM Results.Polaris.HoldNotices) as HoldsOnShelf,
    (SELECT COUNT(*) FROM Results.Polaris.HoldNotices 
     WHERE HoldTillDate < DATEADD(day, 2, GETDATE())) as HoldsExpiringSoon,
    
    -- Overdues
    (SELECT COUNT(*) FROM Results.Polaris.OverdueNotices) as OverdueItems,
    (SELECT COUNT(DISTINCT PatronID) FROM Results.Polaris.OverdueNotices) as PatronsWithOverdues,
    
    -- Fines
    (SELECT COUNT(DISTINCT PatronID) FROM Results.Polaris.FineNotices) as PatronsWithFines,
    (SELECT SUM(TotalOutstandingCharges) FROM Results.Polaris.FineNotices) as TotalOutstanding,
    
    -- Reminders
    (SELECT COUNT(*) FROM Results.Polaris.CircReminders 
     WHERE CreationDate >= CAST(GETDATE() AS DATE)) as RemindersToday;
```

### Critical Alerts

#### Alert 1: Stuck Notices
**Trigger**: Notices in queue > 2 hours unprocessed

```sql
SELECT 
    NotificationQueueID,
    PatronID,
    NotificationTypeID,
    CreationDate,
    DATEDIFF(minute, CreationDate, GETDATE()) as MinutesOld
FROM Results.Polaris.NotificationQueue
WHERE Processed = 0
    AND CreationDate < DATEADD(hour, -2, GETDATE())
ORDER BY CreationDate;
```

**Action**: Check notice processing service, review error logs

#### Alert 2: Holds Expiring Today
**Trigger**: Holds expiring in < 24 hours

```sql
SELECT 
    h.PatronID,
    p.Barcode as PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast as PatronName,
    pr.EmailAddress,
    h.ItemBarcode,
    h.BrowseTitle,
    h.HoldTillDate,
    DATEDIFF(hour, GETDATE(), h.HoldTillDate) as HoursRemaining
FROM Results.Polaris.HoldNotices h
INNER JOIN Polaris.Polaris.Patrons p ON h.PatronID = p.PatronID
INNER JOIN Polaris.Polaris.PatronRegistration pr ON h.PatronID = pr.PatronID
WHERE h.HoldTillDate < DATEADD(day, 1, GETDATE())
ORDER BY h.HoldTillDate;
```

**Action**: Consider making reminder calls, extend holds if appropriate

#### Alert 3: High-Value Overdues
**Trigger**: Overdue items > $50 value

```sql
SELECT 
    o.PatronID,
    p.Barcode as PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast as PatronName,
    o.ItemBarcode,
    o.BrowseTitle,
    o.Price as ItemValue,
    o.DueDate,
    DATEDIFF(day, o.DueDate, GETDATE()) as DaysOverdue,
    o.BillingNotice as NoticeLevel
FROM Results.Polaris.OverdueNotices o
INNER JOIN Polaris.Polaris.Patrons p ON o.PatronID = p.PatronID
INNER JOIN Polaris.Polaris.PatronRegistration pr ON o.PatronID = pr.PatronID
WHERE o.Price > 50.00
ORDER BY o.Price DESC, o.DueDate;
```

**Action**: Priority follow-up, consider phone calls

#### Alert 4: Failed Deliveries
**Trigger**: Notices with failure status

```sql
SELECT 
    nl.NotificationDateTime,
    nl.PatronID,
    nl.PatronBarcode,
    nt.Description as NoticeType,
    do.DeliveryOption,
    ns.Description as DeliveryStatus,
    nl.DeliveryString,
    nl.Details
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.NotificationTypes nt ON nl.NotificationTypeID = nt.NotificationTypeID
INNER JOIN Polaris.Polaris.SA_DeliveryOptions do ON nl.DeliveryOptionID = do.DeliveryOptionID
INNER JOIN Polaris.Polaris.NotificationStatuses ns ON nl.NotificationStatusID = ns.NotificationStatusID
WHERE nl.NotificationDateTime >= DATEADD(day, -1, GETDATE())
    AND nl.NotificationStatusID IN (3,4,5,6,7,8,9,10,11,13,14) -- All failure statuses
ORDER BY nl.NotificationDateTime DESC;
```

**Action**: Update patron contact information, resend notices

### Weekly Reports

#### Report 1: Notice Volume by Type
```sql
SELECT 
    nt.Description as NoticeType,
    do.DeliveryOption,
    COUNT(*) as NoticeCount,
    COUNT(DISTINCT nl.PatronID) as UniquePatrons
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.NotificationTypes nt ON nl.NotificationTypeID = nt.NotificationTypeID
INNER JOIN Polaris.Polaris.SA_DeliveryOptions do ON nl.DeliveryOptionID = do.DeliveryOptionID
WHERE nl.NotificationDateTime >= DATEADD(day, -7, GETDATE())
GROUP BY nt.Description, do.DeliveryOption
ORDER BY NoticeCount DESC;
```

#### Report 2: Delivery Success Rates
```sql
SELECT 
    do.DeliveryOption,
    COUNT(*) as TotalAttempts,
    SUM(CASE WHEN nl.NotificationStatusID IN (1,2,12,15,16) THEN 1 ELSE 0 END) as Successful,
    SUM(CASE WHEN nl.NotificationStatusID NOT IN (1,2,12,15,16) THEN 1 ELSE 0 END) as Failed,
    CAST(SUM(CASE WHEN nl.NotificationStatusID IN (1,2,12,15,16) THEN 1 ELSE 0 END) * 100.0 / COUNT(*) AS DECIMAL(5,2)) as SuccessRate
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.SA_DeliveryOptions do ON nl.DeliveryOptionID = do.DeliveryOptionID
WHERE nl.NotificationDateTime >= DATEADD(day, -7, GETDATE())
GROUP BY do.DeliveryOption
ORDER BY TotalAttempts DESC;
```

#### Report 3: Top Patrons by Notice Volume
```sql
SELECT TOP 20
    nl.PatronID,
    nl.PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast as PatronName,
    COUNT(*) as NoticeCount,
    COUNT(DISTINCT nl.NotificationTypeID) as NoticeTypes,
    SUM(nl.OverduesCount) as TotalOverdues,
    SUM(nl.HoldsCount) as TotalHolds
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.PatronRegistration pr ON nl.PatronID = pr.PatronID
WHERE nl.NotificationDateTime >= DATEADD(day, -30, GETDATE())
GROUP BY nl.PatronID, nl.PatronBarcode, pr.NameFirst, pr.NameLast
ORDER BY NoticeCount DESC;
```

### Monthly Analysis

#### Analysis 1: Overdue Progression
**Purpose**: Track how many first notices become second/third notices

```sql
WITH OverdueProgression AS (
    SELECT 
        PatronID,
        ItemRecordID,
        MIN(CASE WHEN BillingNotice=0 THEN OverdueNoticeID END) as FirstNoticeID,
        MIN(CASE WHEN BillingNotice=1 THEN OverdueNoticeID END) as SecondNoticeID,
        MIN(CASE WHEN BillingNotice=2 THEN OverdueNoticeID END) as ThirdNoticeID
    FROM Results.Polaris.OverdueNotices
    WHERE DueDate >= DATEADD(day, -60, GETDATE())
    GROUP BY PatronID, ItemRecordID
)
SELECT 
    COUNT(*) as TotalOverdues,
    SUM(CASE WHEN FirstNoticeID IS NOT NULL THEN 1 ELSE 0 END) as FirstNotices,
    SUM(CASE WHEN SecondNoticeID IS NOT NULL THEN 1 ELSE 0 END) as SecondNotices,
    SUM(CASE WHEN ThirdNoticeID IS NOT NULL THEN 1 ELSE 0 END) as ThirdNotices,
    CAST(SUM(CASE WHEN SecondNoticeID IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / 
         SUM(CASE WHEN FirstNoticeID IS NOT NULL THEN 1 ELSE 0 END) AS DECIMAL(5,2)) as EscalationRate
FROM OverdueProgression;
```

#### Analysis 2: Hold Pickup Success Rate
**Purpose**: Track how many holds are picked up vs. expired

```sql
SELECT 
    COUNT(DISTINCT shr.SysHoldRequestID) as TotalHolds,
    SUM(CASE WHEN shh.SysHoldStatusID = 8 THEN 1 ELSE 0 END) as PickedUp,
    SUM(CASE WHEN shh.SysHoldStatusID IN (10,11) THEN 1 ELSE 0 END) as Expired,
    CAST(SUM(CASE WHEN shh.SysHoldStatusID = 8 THEN 1 ELSE 0 END) * 100.0 / 
         COUNT(DISTINCT shr.SysHoldRequestID) AS DECIMAL(5,2)) as PickupRate
FROM Polaris.Polaris.SysHoldRequests shr
INNER JOIN Polaris.Polaris.SysHoldHistory shh 
    ON shr.SysHoldRequestID = shh.SysHoldRequestID
WHERE shr.CreationDate >= DATEADD(day, -30, GETDATE())
    AND shh.SysHoldStatusID IN (6,8,10,11); -- Held, Picked Up, Expired, Cancelled
```

---

## Troubleshooting Guide

### Issue 1: Notices Not Sending

**Symptoms**:
- NotificationQueue has Processed=0 records older than 2 hours
- Patrons report not receiving notices

**Diagnosis Steps**:
1. Check NotificationQueue for stuck records:
   ```sql
   SELECT * FROM Results.Polaris.NotificationQueue
   WHERE Processed=0 AND CreationDate < DATEADD(hour, -2, GETDATE());
   ```

2. Check notice processing service status (depends on your setup)

3. Review NotificationLog for recent failures:
   ```sql
   SELECT TOP 100 *
   FROM PolarisTransactions.Polaris.NotificationLog
   WHERE NotificationStatusID NOT IN (1,2,12,15,16)
   ORDER BY NotificationDateTime DESC;
   ```

**Solutions**:
- Restart notice processing service
- Clear stuck queue items (after investigation)
- Check email/SMS gateway connectivity
- Verify patron contact information

### Issue 2: Incorrect Patron Preferences

**Symptoms**:
- Patron receives wrong delivery method
- Patron excluded from notices they should receive

**Diagnosis Steps**:
1. Check patron delivery preferences:
   ```sql
   SELECT 
       pr.PatronID,
       p.Barcode,
       pr.NameFirst + ' ' + pr.NameLast as Name,
       pr.DeliveryOptionID,
       do.DeliveryOption,
       pr.EmailAddress,
       pr.PhoneVoice1,
       pr.EnableSMS,
       pr.ExcludeFromOverdues,
       pr.ExcludeFromHolds,
       pr.ExcludeFromBills
   FROM Polaris.Polaris.PatronRegistration pr
   INNER JOIN Polaris.Polaris.Patrons p ON pr.PatronID = p.PatronID
   INNER JOIN Polaris.Polaris.SA_DeliveryOptions do ON pr.DeliveryOptionID = do.DeliveryOptionID
   WHERE p.Barcode = '23307XXXXXXXXX';  -- Patron's barcode
   ```

2. Check if contact info is valid:
   ```sql
   SELECT 
       PatronID,
       EmailAddress,
       PhoneVoice1,
       Phone1CarrierID,
       CASE 
           WHEN EmailAddress IS NULL OR EmailAddress = '' THEN 'Missing Email'
           WHEN PhoneVoice1 IS NULL OR PhoneVoice1 = '' THEN 'Missing Phone'
           WHEN DeliveryOptionID = 2 AND (EmailAddress IS NULL OR EmailAddress = '') THEN 'Email preferred but missing'
           WHEN DeliveryOptionID IN (3,4,5) AND (PhoneVoice1 IS NULL OR PhoneVoice1 = '') THEN 'Phone preferred but missing'
           ELSE 'OK'
       END as ContactStatus
   FROM Polaris.Polaris.PatronRegistration
   WHERE PatronID = XXXXX;  -- Patron ID
   ```

**Solutions**:
- Update patron delivery preferences
- Verify/update email address
- Verify/update phone number and carrier
- Check exclusion flags and update if needed

### Issue 3: Duplicate Notices

**Symptoms**:
- Patron receives multiple copies of same notice
- NotificationQueue has duplicate entries

**Diagnosis Steps**:
1. Check for duplicate queue entries:
   ```sql
   SELECT 
       PatronID,
       ItemRecordID,
       NotificationTypeID,
       COUNT(*) as DuplicateCount
   FROM Results.Polaris.NotificationQueue
   WHERE CreationDate >= CAST(GETDATE() AS DATE)
   GROUP BY PatronID, ItemRecordID, NotificationTypeID
   HAVING COUNT(*) > 1;
   ```

2. Check NotificationLog for multiple sends:
   ```sql
   SELECT *
   FROM PolarisTransactions.Polaris.NotificationLog
   WHERE PatronID = XXXXX
       AND NotificationDateTime >= CAST(GETDATE() AS DATE)
   ORDER BY NotificationDateTime;
   ```

**Solutions**:
- Review notice generation logic
- Check for trigger/scheduled job conflicts
- Implement duplicate detection in processing logic

### Issue 4: Missing Hold Notices

**Symptoms**:
- Item shows as "Held" in Polaris
- Patron didn't receive hold notice

**Diagnosis Steps**:
1. Check if hold record exists in Results.HoldNotices:
   ```sql
   SELECT *
   FROM Results.Polaris.HoldNotices
   WHERE PatronID = XXXXX
       AND ItemRecordID = YYYYY;
   ```

2. Check SysHoldRequests notification date:
   ```sql
   SELECT 
       SysHoldRequestID,
       PatronID,
       SysHoldStatusID,
       HoldNotificationDate,
       HoldNotification2ndDate,
       DeliveryOptionID
   FROM Polaris.Polaris.SysHoldRequests
   WHERE PatronID = XXXXX
       AND TrappingItemRecordID = YYYYY;
   ```

3. Check NotificationHistory for delivery:
   ```sql
   SELECT *
   FROM Results.Polaris.NotificationHistory
   WHERE PatronId = XXXXX
       AND ItemRecordId = YYYYY
       AND NotificationTypeId = 2;
   ```

**Solutions**:
- Manually generate notice if missing
- Check hold notification generation trigger
- Verify patron isn't excluded from holds (ExcludeFromHolds flag)
- Update HoldNotificationDate if needed

### Issue 5: PhoneNotices.csv Export Issues

**Symptoms**:
- PhoneNotices.csv not generated daily
- File has incorrect format
- Shoutbomb not processing file

**Diagnosis Steps**:
1. Check if export query is running
2. Verify file location and permissions
3. Check file format matches expected structure
4. Review rpt_exportnotices.sql for errors

**Solutions**:
- Verify scheduled task/cron job is active
- Check database connectivity
- Validate CSV formatting (quotes, delimiters)
- Review file system permissions
- Check Shoutbomb integration logs

### Issue 6: High Fine Balances Not Notifying

**Symptoms**:
- Patrons have high balances but no recent fine notices

**Diagnosis Steps**:
1. Check fine notice generation:
   ```sql
   SELECT 
       f.PatronID,
       f.TotalOutstandingCharges,
       f.TxnID,
       nh.NoticeDate as LastFineNotice
   FROM Results.Polaris.FineNotices f
   LEFT JOIN Results.Polaris.NotificationHistory nh 
       ON f.PatronID = nh.PatronId 
       AND nh.NotificationTypeId IN (8,21)
   WHERE f.TotalOutstandingCharges > 25.00
   ORDER BY f.TotalOutstandingCharges DESC;
   ```

2. Check if patrons excluded from bills:
   ```sql
   SELECT 
       p.PatronID,
       p.ChargesAmount,
       pr.ExcludeFromBills
   FROM Polaris.Polaris.Patrons p
   INNER JOIN Polaris.Polaris.PatronRegistration pr ON p.PatronID = pr.PatronID
   WHERE p.ChargesAmount > 25.00
       AND pr.ExcludeFromBills = 1;
   ```

**Solutions**:
- Review fine notice generation schedule
- Check balance thresholds for notice triggers
- Update ExcludeFromBills flag if incorrect
- Manually generate notices if needed

---

## Best Practices

### Patron Communication
1. **Set Clear Expectations**: Inform patrons about notification types and delivery methods
2. **Verify Contact Info**: Regularly prompt patrons to update email/phone
3. **Respect Preferences**: Honor patron delivery method choices
4. **Privacy First**: Never log or expose sensitive patron information inappropriately

### System Maintenance
1. **Archive Old Data**: Move processed notices to archive tables monthly
2. **Monitor Queue Health**: Check for stuck notices hourly during business hours
3. **Test Regularly**: Send test notices to staff accounts to verify delivery
4. **Document Changes**: Keep changelog of notification configuration changes

### Data Quality
1. **Validate Contacts**: Implement email validation on patron registration
2. **Clean Phone Numbers**: Standardize phone number format (###-###-####)
3. **Carrier Accuracy**: Keep SMS carrier mapping up-to-date
4. **Address Verification**: Use USPS address validation for physical mail

### Performance Optimization
1. **Index Key Fields**: Ensure proper indexes on PatronID, ItemRecordID, NotificationTypeID
2. **Batch Processing**: Process notices in batches during off-peak hours
3. **Archive Strategy**: Move old NotificationLog records to archive tables
4. **Query Optimization**: Review and optimize slow-running monitoring queries

### Reporting
1. **Daily Summary**: Generate and email daily notice statistics
2. **Weekly Trends**: Track notice volume trends week-over-week
3. **Monthly Reports**: Comprehensive monthly notice and delivery analysis
4. **Annual Review**: Year-end analysis of notification effectiveness

---

## Quick Reference

### Key Patron Queries

**Get Patron Notification Preferences**:
```sql
SELECT p.PatronID, p.Barcode, pr.NameFirst, pr.NameLast,
       pr.DeliveryOptionID, do.DeliveryOption,
       pr.EmailAddress, pr.PhoneVoice1, pr.EnableSMS,
       pr.ExcludeFromOverdues, pr.ExcludeFromHolds, pr.ExcludeFromBills
FROM Polaris.Polaris.Patrons p
INNER JOIN Polaris.Polaris.PatronRegistration pr ON p.PatronID = pr.PatronID
INNER JOIN Polaris.Polaris.SA_DeliveryOptions do ON pr.DeliveryOptionID = do.DeliveryOptionID
WHERE p.Barcode = 'PATRON_BARCODE';
```

**Get Patron Notice History**:
```sql
SELECT nl.NotificationDateTime, nt.Description as NoticeType,
       do.DeliveryOption, ns.Description as Status,
       nl.OverduesCount, nl.HoldsCount
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.NotificationTypes nt ON nl.NotificationTypeID = nt.NotificationTypeID
INNER JOIN Polaris.Polaris.SA_DeliveryOptions do ON nl.DeliveryOptionID = do.DeliveryOptionID
INNER JOIN Polaris.Polaris.NotificationStatuses ns ON nl.NotificationStatusID = ns.NotificationStatusID
WHERE nl.PatronID = PATRON_ID
ORDER BY nl.NotificationDateTime DESC;
```

### Critical Tables Summary

| Table | Database | Purpose | Key Field |
|-------|----------|---------|-----------|
| NotificationQueue | Results.Polaris | Master notice queue | NotificationQueueID |
| NotificationLog | PolarisTransactions.Polaris | Permanent delivery log | NotificationLogID |
| Patrons | Polaris.Polaris | Core patron records | PatronID |
| PatronRegistration | Polaris.Polaris | Patron details & preferences | PatronID |
| SysHoldRequests | Polaris.Polaris | Active holds | SysHoldRequestID |
| HoldNotices | Results.Polaris | Hold ready notices | PatronID, ItemRecordID |
| OverdueNotices | Results.Polaris | Overdue item notices | OverdueNoticeID |
| FineNotices | Results.Polaris | Fine notice summaries | PatronID, TxnID |

---

## Contact & Support

**Library**: Daviess County Public Library (DCPL)  
**Location**: 2020 Frederica Street, Owensboro, KY 42301  
**Phone**: 270-684-0211 (Main) | 270-684-0211 x262 (Circulation)  

**Polaris ILS Support**: Contact your Polaris vendor for system-level support

**Document Information**:
- Version: 2.0
- Last Updated: November 5, 2025
- Created by: AI Assistant for DCPL
- Sample Data Date: October-November 2025

---

## Appendix A: Complete Table List

### Results.Polaris Tables (8)
1. NotificationQueue
2. OverdueNotices
3. HoldNotices
4. FineNotices
5. FineNoticesReport
6. ManualBillNotices
7. CircReminders
8. NotificationHistory

### Polaris.Polaris Tables (17+)
**Patron Tables**:
- Patrons
- PatronRegistration
- PatronAddresses
- PatronFineNotices
- Addresses
- AddressTypes
- AddressLabels

**Hold Tables**:
- SysHoldRequests
- SysHoldHistory
- SysHoldHistoryDaily
- SysHoldStatuses

**Item Tables**:
- CircReserveItemRecords_View
- ItemStatuses

**Reference Tables**:
- NotificationTypes
- NotificationStatuses
- SA_DeliveryOptions
- DeliveryOptions (view)

### Polaris.Polaris Views (6)
- ViewHoldNotices
- ViewHoldNoticesData
- ViewOverdueNoticesData
- ViewManualBillNoticesData
- ML_V1_Holds
- ML_V1_Patrons_Holds

### PolarisTransactions.Polaris Tables (3)
- NotificationLog
- TransactionTypes
- TransactionSubTypes

---

**Total System Tables/Views**: 34+

---

## Document Change Log

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-11-05 | Initial documentation based on Results.Polaris tables |
| 2.0 | 2025-11-05 | Complete system documentation including all three databases, views, reference data, and export processes |

