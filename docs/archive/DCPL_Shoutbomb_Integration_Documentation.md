# DCPL Shoutbomb-Polaris Integration System
**Complete Technical Documentation**

---

## System Overview

The Daviess County Public Library uses a custom integration system to send voice and SMS notifications through Shoutbomb. This system is **superior to the standard Polaris PhoneNotices.csv export** because it:

1. **Queries directly from NotificationQueue** - Uses the actual notification queue rather than duplicate exports
2. **Simpler data format** - Pipe-delimited with only essential fields
3. **Better conflict resolution** - Automatically syncs notification preferences for shared phone numbers
4. **Flexible scheduling** - Separate control over each notice type
5. **Comprehensive logging** - Full audit trail of all uploads and changes

---

## Architecture

### Directory Structure

```
C:\shoutbomb\
├── sql\                          # SQL query scripts
│   ├── holds.sql                 # Hold ready notices
│   ├── overdue.sql               # Overdue notices
│   ├── renew.sql                 # Courtesy reminders (3 days)
│   ├── renew_thursday.sql        # Thursday reminders (4 days)
│   ├── text_patrons.sql          # SMS patron registration
│   ├── voice_patrons.sql         # Voice patron registration
│   └── conflicts\                # Conflict resolution
│       ├── log_text_conflicts.sql
│       ├── log_voice_conflicts.sql
│       ├── resolve_text.sql
│       └── resolve_voice.sql
├── scripts\                      # Batch execution scripts
│   ├── shoutbomb.bat             # Main upload script
│   ├── shoutbomb_conflicts.bat   # Conflict resolver
│   └── shoutbomb_renew_thursday.bat  # Thursday special
├── ftp\                          # Output staging folders
│   ├── holds\
│   ├── overdue\
│   ├── renew\
│   ├── text_patrons\
│   └── voice_patrons\
├── logs\                         # Activity logs & backups
└── scheduled_tasks\              # Windows Task Scheduler XML
    ├── Upload Holds.xml
    ├── Upload Courtesy.xml
    ├── Upload Overdue.xml
    ├── Upload Renew.xml
    ├── Upload Text Patrons.xml
    ├── Upload Voice Patrons.xml
    ├── Resolve Conflicts to Text.xml
    └── Resolve Conflicts to Voice.xml
```

---

## Core Components

### 1. Main Upload Script (`shoutbomb.bat`)

**Purpose**: Universal script that handles all notification uploads

**Parameters**: Accepts one parameter indicating the type of data to process:
- `holds` - Hold ready notifications
- `overdue` - Overdue item notifications  
- `renew` - Courtesy reminders (items due in 3 days)
- `voice_patrons` - Voice notification patron list
- `text_patrons` - SMS notification patron list

**Process Flow**:
```
1. Set variables based on parameter
2. Execute SQL query: C:\shoutbomb\sql\{parameter}.sql
3. Write results to: C:\shoutbomb\ftp\{parameter}\{parameter}.txt
4. Upload file via WinSCP to Shoutbomb FTP /{parameter}/
5. Log results to: C:\shoutbomb\logs\{parameter}.log
6. Move uploaded file to logs with timestamp
```

**Example Usage**:
```batch
shoutbomb.bat holds
shoutbomb.bat overdue
shoutbomb.bat text_patrons
```

**Key Features**:
- Uses environment variable `%shoutbomb%` for FTP credentials (secure)
- Timestamped backups of all uploaded files
- Comprehensive WinSCP logging
- Error handling with exit codes

**Full Script**:
```batch
@echo off
set info=%1
set backup=%info%_submitted_%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%-%time:~6,2%
set backup=%backup: =0%

:: Execute SQL query
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\%info%.sql ^
  -o C:\shoutbomb\ftp\%info%\%info%.txt -h-1 -W -s "|"

:: Upload via WinSCP
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="C:\shoutbomb\logs\%info%.log" /ini=nul ^
  /command ^
    "open %shoutbomb% -rawsettings ProxyPort=0" ^
    "lcd C:\shoutbomb\ftp\%info%" ^
    "cd /%info%" ^
    "put *" ^
    "exit"

:: Handle results
set WINSCP_RESULT=%ERRORLEVEL%
if %WINSCP_RESULT% equ 0 (
  echo Success
  move C:\shoutbomb\ftp\%info%\%info%.txt C:\shoutbomb\logs\%backup%.txt
) else (
  echo Error
)

exit /b %WINSCP_RESULT%
```

---

### 2. Conflict Resolution Script (`shoutbomb_conflicts.bat`)

**Purpose**: Resolves notification method conflicts when multiple patrons share a phone number

**Background**: Shoutbomb requires that each phone number be registered as EITHER voice OR text. If multiple patrons share a phone number, they must all use the same notification method.

**Parameters**:
- Parameter 1: `text` or `voice` - Which method to standardize to
- Parameter 2 (optional): Mobile carrier keyword (e.g., `att`, `verizon`) - currently unused

**Process Flow**:
```
1. Log conflicts: Find accounts updated in past week with method mismatch
2. Resolve conflicts: Update all related accounts to same method
3. Save log with timestamp
```

**Example Usage**:
```batch
shoutbomb_conflicts.bat text
shoutbomb_conflicts.bat voice
```

**Key Logic**:
- Only processes accounts updated in the past week
- Only affects voice (ID=3) or text (ID=8) delivery options
- Does NOT change email or mail delivery preferences
- Creates timestamped log of all changes

---

### 3. Thursday Renew Script (`shoutbomb_renew_thursday.bat`)

**Purpose**: Special courtesy reminder for Thursday (4 days out instead of 3)

**Why It Exists**: DCPL doesn't count Sundays against the loan period, so Thursday reminders need to look 4 days ahead to account for the skipped Sunday.

**Difference from regular renew**:
- `renew.sql`: Due date = today + 3 days
- `renew_thursday.sql`: Due date = today + 4 days

**Usage**: Schedule to run on Thursdays only

---

## SQL Queries In Detail

### Hold Notices (`holds.sql`)

**Purpose**: Query hold ready notifications from NotificationQueue

**Data Source**: 
- Results.Polaris.NotificationQueue (queue status)
- Results.Polaris.HoldNotices (notice details)
- Polaris.Polaris.Patrons (patron info)
- Polaris.Polaris.SysHoldRequests (hold request details)

**Output Format** (Pipe-delimited):
```
BrowseTitle|CreationDate|SysHoldRequestID|PatronID|PickupOrgID|HoldTillDate|PatronBarcode
```

**Key Filters**:
- DeliveryOptionID = 3 OR 8 (Voice or SMS only)
- HoldTillDate > GETDATE() (Not expired)
- Sorted by patron barcode

**Query**:
```sql
SET NOCOUNT ON
Select convert(varchar (255), hn.BrowseTitle) as BTitle
    , convert(varchar(10), q.CreationDate, 120) as CreationDate
    , hr.SysHoldRequestID
    , q.PatronID
    , hn.PickupOrganizationID
    , convert(varchar(10), hn.HoldTillDate, 120) as HoldTillDate
    , convert(varchar(20), p.Barcode) as PBarcode
From
    Results.polaris.NotificationQueue q (nolock)
    join Results.polaris.HoldNotices hn (nolock) 
      on q.ItemRecordID=hn.ItemRecordID 
      and q.PatronID=hn.PatronID 
      and q.NotificationTypeID=hn.NotificationTypeID
    join Polaris.polaris.Patrons p (nolock) 
      on q.PatronID=p.PatronID
    left join Polaris.polaris.SysHoldRequests hr 
      on q.PatronID=hr.PatronID 
      and q.ItemRecordID=hr.TrappingItemRecordID
Where
    (q.DeliveryOptionID=3 OR q.DeliveryOptionID=8)
    and hn.HoldTillDate>GETDATE()
Order By p.Barcode
```

---

### Overdue Notices (`overdue.sql`)

**Purpose**: Query overdue item notifications

**Data Source**:
- Results.Polaris.NotificationQueue (queue)
- Polaris.Polaris.ItemCheckouts (checkout details)
- Polaris.Polaris.CircItemRecords (item info)
- Polaris.Polaris.BibliographicRecords (title info)

**Output Format** (Pipe-delimited):
```
PatronID|ItemBarcode|Title|DueDate|ItemRecordID||||Renewals|BibRecordID|RenewalLimit|PatronBarcode
```

**Note**: Four empty fields (Dummy1-4) maintain compatibility with Shoutbomb's expected format

**Key Filters**:
- DeliveryOptionID = 3 OR 8
- NotificationTypeID in (1,7,8,11,12,13) - Various overdue types
- CreationDate > GETDATE()-1 (Last 24 hours)

**Query**:
```sql
SET NOCOUNT ON
Select nq.PatronID
    , convert(varchar(20), cir.Barcode) as ItemBarcode
    , convert(varchar(255), br.BrowseTitle) as Title
    , convert(varchar(10), ic.DueDate, 120) as DueDate
    , cir.ItemRecordID
    , '' as Dummy1
    , '' as Dummy2
    , '' as Dummy3
    , '' as Dummy4
    , ic.Renewals
    , br.BibliographicRecordID
    , cir.RenewalLimit
    , convert(varchar(20), p.Barcode) as PatronBarcode
From
    Results.Polaris.NotificationQueue nq (nolock)
    join Polaris.Polaris.Patrons p (nolock) on nq.PatronID=p.PatronID
    join Polaris.Polaris.ItemCheckouts ic (nolock) 
      on nq.PatronId=ic.PatronID and nq.ItemRecordId=ic.ItemRecordID
    join Polaris.Polaris.CircItemRecords cir (nolock) 
      on ic.ItemRecordID=cir.ItemRecordID
    join Polaris.Polaris.BibliographicRecords br (nolock) 
      on cir.AssociatedBibRecordID=br.BibliographicRecordID
Where  
    (nq.DeliveryOptionId=3 OR nq.DeliveryOptionId=8)
    and nq.NotificationTypeId in (1,7,8,11,12,13)
    and nq.CreationDate>GETDATE()-1
Order By nq.PatronID
```

---

### Courtesy Reminders (`renew.sql`)

**Purpose**: Query items due in 3 days (courtesy reminder)

**Data Source**:
- Polaris.Polaris.ItemCheckouts (checkout details)
- Polaris.Polaris.PatronRegistration (delivery preferences)
- Polaris.Polaris.CircItemRecords (item info)
- Polaris.Polaris.BibliographicRecords (title info)

**Output Format**: Same as overdue.sql

**Key Filters**:
- DeliveryOptionID = 3 OR 8
- DueDate = today + 3 days
- MaterialTypeID != 12 (Excludes certain material types)

**Query**:
```sql
SET NOCOUNT ON
Select pr.PatronID
    , convert(varchar(20), cir.Barcode) as ItemBarcode
    , convert(varchar(255), br.BrowseTitle) as Title
    , convert(varchar(10), ic.DueDate, 120) as DueDate
    , cir.ItemRecordID
    , '' as Dummy1
    , '' as Dummy2
    , '' as Dummy3
    , '' as Dummy4
    , ic.Renewals
    , br.BibliographicRecordID
    , cir.RenewalLimit
    , convert(varchar(20), p.Barcode) as PatronBarcode
From
    Polaris.ItemCheckouts ic (nolock)
    join Polaris.Polaris.PatronRegistration pr (nolock) on ic.PatronID=pr.PatronID
    join Polaris.Polaris.Patrons p (nolock) on pr.PatronID=p.PatronID
    join Polaris.Polaris.CircItemRecords cir (nolock) on ic.ItemRecordID=cir.ItemRecordID
    join Polaris.Polaris.BibliographicRecords br (nolock) 
      on cir.AssociatedBibRecordID=br.BibliographicRecordID
Where
    (pr.DeliveryOptionID=3 or pr.DeliveryOptionID=8) and
    convert(varchar (11),ic.DueDate, 101)=convert(varchar (11), getdate()+3, 101) and
    cir.MaterialTypeID!=12
Order By ic.PatronID
```

---

### Thursday Reminders (`renew_thursday.sql`)

**Purpose**: Courtesy reminders for items due in 4 days (Thursday special)

**Only Difference**: Due date comparison uses `getdate()+4` instead of `getdate()+3`

---

### Text Patron Registration (`text_patrons.sql`)

**Purpose**: Export all patrons registered for SMS notifications

**Data Source**:
- Polaris.Polaris.PatronRegistration (delivery preferences)
- Polaris.Polaris.Patrons (patron info)

**Output Format** (Pipe-delimited):
```
PhoneNumber|PatronBarcode
```

**Key Features**:
- Strips hyphens from phone numbers
- Only active patrons (not expired in last 3 months)
- Valid phone numbers (length > 9)
- DeliveryOptionID = 8 (SMS)

**Query**:
```sql
SET NOCOUNT ON
SELECT REPLACE(pr.Phonevoice1,'-',''), p.Barcode
FROM polaris.Polaris.PatronRegistration pr
    inner join Polaris.polaris.patrons p ON pr.PatronID = p.PatronID
WHERE pr.DeliveryOptionID = 8
    AND pr.PhoneVoice1 IS NOT NULL
    AND p.Barcode IS NOT NULL
    AND pr.Expirationdate > DATEADD(MONTH,-3,GETDATE())
    AND LEN(pr.PhoneVoice1) > 9
ORDER BY p.Barcode
```

---

### Voice Patron Registration (`voice_patrons.sql`)

**Purpose**: Export all patrons registered for voice notifications

**Output Format**: Same as text_patrons.sql

**Only Difference**: DeliveryOptionID = 3 (Voice) instead of 8 (SMS)

---

## Conflict Resolution System

### The Problem

**Shoutbomb Constraint**: Each phone number must be registered as EITHER voice OR text, not both.

**Real-World Scenario**:
1. Parent (PatronID 12345) uses phone 270-555-1234 for **Voice** notifications
2. Child (PatronID 67890) uses same phone 270-555-1234 for **Text** notifications
3. Shoutbomb cannot handle this conflict

**Solution**: When one account is updated, automatically sync all accounts with the same phone number to use the same notification method.

---

### Log Text Conflicts (`log_text_conflicts.sql`)

**Purpose**: Find accounts that were recently changed to text but conflict with voice accounts

**Logic**:
1. Find accounts updated in past week with DeliveryOptionID = 8 (Text)
2. Search for other accounts with same phone number but DeliveryOptionID = 3 (Voice)
3. Output those conflicting voice accounts to log

**Query**:
```sql
SET NOCOUNT ON
DECLARE @TheDate date;
SET @TheDate = DATEADD(d, -1, GETDATE());

SELECT PatronID, PhoneVoice1, UpdateDate, DeliveryOptionID, 
       Phone1CarrierID, TxtPhoneNumber
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID = 3  -- Voice accounts
  AND PhoneVoice1 IN (
      -- Find phone numbers recently changed to text
      SELECT PhoneVoice1
      FROM Polaris.Polaris.PatronRegistration
      WHERE UpdateDate > @TheDate
        AND DeliveryOptionID = 8  -- Text
        AND TxtPhoneNumber = 1
        AND ExpirationDate > DATEADD(d, -1, GETDATE())
  )
ORDER BY UpdateDate DESC;
```

**Output**: Log file with list of accounts that need to be changed from voice to text

---

### Log Voice Conflicts (`log_voice_conflicts.sql`)

**Purpose**: Find accounts that conflict with recently-changed voice accounts

**Logic**: Same as text conflicts but reversed (find text accounts conflicting with recently-changed voice accounts)

---

### Resolve to Text (`resolve_text.sql`)

**Purpose**: Update all conflicting voice accounts to use text

**Process**:
1. Find voice accounts (DeliveryOptionID=3) sharing phone numbers with recently-updated text accounts
2. For each conflicting account:
   - Change DeliveryOptionID to 8 (Text)
   - Set TxtPhoneNumber to 1 (Use PhoneVoice1)
   - Copy Phone1CarrierID from the updated account
3. Optionally create note in patron custom data field (DCPL specific, commented out)

**Key Code Sections**:
```sql
-- Find conflicts
DECLARE pCursor CURSOR FOR
SELECT PatronID, PhoneVoice1, UpdateDate, DeliveryOptionID, 
       Phone1CarrierID, TxtPhoneNumber
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID = 3  -- Currently voice
  AND PhoneVoice1 IN (
      -- Phone numbers recently changed to text
      SELECT PhoneVoice1
      FROM Polaris.Polaris.PatronRegistration
      WHERE UpdateDate > @TheDate
        AND DeliveryOptionID = 8
        AND TxtPhoneNumber = 1
        AND ExpirationDate > DATEADD(d, -1, GETDATE())
  );

-- Update each conflict
WHILE @@FETCH_STATUS = 0
BEGIN
    UPDATE Polaris.Polaris.PatronRegistration
    SET DeliveryOptionID = 8,
        TxtPhoneNumber = 1,
        Phone1CarrierID = @Phone1CarrierID
    WHERE PatronID = @PatronID;
    
    -- Optional: Log change in custom field (DCPL only)
    -- [Commented out code for creating notes]
    
    FETCH NEXT FROM pCursor INTO ...;
END;
```

---

### Resolve to Voice (`resolve_voice.sql`)

**Purpose**: Update all conflicting text accounts to use voice

**Process**: Same as resolve_text.sql but:
- Changes DeliveryOptionID to 3 (Voice)
- Sets TxtPhoneNumber to NULL
- No carrier needed for voice

---

## Scheduled Tasks

### Task Schedule Overview

| Task | Runs | Time | Purpose |
|------|------|------|---------|
| Upload Holds | Daily | 12:00 PM | Hold ready notices |
| Upload Courtesy | Daily | 6:00 AM | Items due in 3 days |
| Upload Overdue | Daily | 6:30 AM | Overdue item notices |
| Upload Renew (Thu) | Thursday | 6:00 AM | Items due in 4 days (Sunday skip) |
| Upload Text Patrons | Daily | 4:00 AM | SMS patron registration |
| Upload Voice Patrons | Daily | 4:00 AM | Voice patron registration |
| Resolve Conflicts (Text) | Daily | 3:00 AM | Sync shared numbers to text |
| Resolve Conflicts (Voice) | Daily | 3:00 AM | Sync shared numbers to voice |

### Execution Order (Recommended)

**3:00 AM - Conflict Resolution**:
1. Resolve Conflicts to Text
2. Resolve Conflicts to Voice

**4:00 AM - Patron Registration**:
3. Upload Text Patrons
4. Upload Voice Patrons

**6:00 AM - Courtesy Notices**:
5. Upload Courtesy (renew.sql)
6. Upload Renew Thursday (Thursday only)

**6:30 AM - Overdue Notices**:
7. Upload Overdue

**12:00 PM - Hold Notices**:
8. Upload Holds (midday to catch morning check-ins)

### Task Configuration

All tasks use the same basic settings:

```xml
<Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <StartWhenAvailable>true</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <AllowStartOnDemand>true</AllowStartOnDemand>
    <Enabled>true</Enabled>
    <ExecutionTimeLimit>PT4H</ExecutionTimeLimit>
    <Priority>7</Priority>
</Settings>
```

**Key Settings**:
- **IgnoreNew**: If task is still running, don't start new instance
- **StartWhenAvailable**: Run ASAP if missed scheduled time
- **ExecutionTimeLimit**: 4 hours max (PT4H)
- **Priority**: 7 (below normal, won't impact system performance)

### Example Task: Upload Holds

```xml
<Task version="1.4">
  <RegistrationInfo>
    <Description>Upload patrons with phone notification preference to Shoutbomb</Description>
    <URI>\Shoutbomb\Upload Holds</URI>
  </RegistrationInfo>
  
  <Triggers>
    <CalendarTrigger>
      <StartBoundary>2020-05-02T12:00:00</StartBoundary>
      <Enabled>true</Enabled>
      <ScheduleByDay>
        <DaysInterval>1</DaysInterval>
      </ScheduleByDay>
    </CalendarTrigger>
  </Triggers>
  
  <Actions>
    <Exec>
      <Command>C:\shoutbomb\scripts\shoutbomb.bat</Command>
      <Arguments>holds</Arguments>
    </Exec>
  </Actions>
</Task>
```

---

## WinSCP Configuration

### Setup Process

1. **Install WinSCP**: Download from https://winscp.net/eng/download.php

2. **Create Shoutbomb Site**:
   - Open WinSCP
   - Click "New Site"
   - Enter FTP credentials provided by Shoutbomb
   - If SSL certificate provided, add under Advanced > TLS/SSL
   - Save as "Shoutbomb"

3. **Generate Session URL**:
   - Right-click Shoutbomb tab
   - Select "Generate Session URL/Code"
   - Copy URL between "open" and "-rawsettings"

4. **Store in Environment Variable** (Recommended):
   - Open "Edit system environment variables"
   - Click "Environment Variables"
   - Under System variables, click "New"
   - Variable name: `shoutbomb`
   - Variable value: [paste session URL]
   - Click OK

5. **Alternative: Hardcode in Scripts**:
   - Edit each .bat file
   - Replace `%shoutbomb%` with the actual URL
   - Less secure, but works without environment variable

### Session URL Format

```
ftps://username:password@ftp.shoutbomb.com/ -certificate="xx:xx:xx:xx:..."
```

**Security Note**: Using environment variable prevents credentials from appearing in scripts and logs

---

## Data Flow Diagrams

### Hold Notice Flow

```
ITEM CHECKED IN & TRAPPED FOR HOLD
        ↓
Polaris updates SysHoldRequests
├── SysHoldStatusID = 6 (Held)
├── HoldNotificationDate = now
└── TrappingItemRecordID set
        ↓
Results.Polaris.HoldNotices populated
├── ItemRecordID, PatronID
├── HoldTillDate calculated
└── DeliveryOptionID from patron
        ↓
Results.Polaris.NotificationQueue created
├── NotificationTypeID = 2
├── Processed = 0
└── Links to HoldNotices
        ↓
SCHEDULED TASK: Upload Holds (12:00 PM)
        ↓
shoutbomb.bat holds
        ↓
holds.sql queries:
├── NotificationQueue (queue status)
├── HoldNotices (notice details)
├── Patrons (patron info)
└── SysHoldRequests (hold ID)
        ↓
Filter: DeliveryOptionID = 3 OR 8
        ↓
Output to C:\shoutbomb\ftp\holds\holds.txt
Format: Title|Created|HoldID|PatronID|PickupOrg|Expiration|Barcode
        ↓
WinSCP uploads to Shoutbomb FTP: /holds/holds.txt
        ↓
Shoutbomb processes and delivers
├── Voice calls (DeliveryOptionID=3)
└── SMS messages (DeliveryOptionID=8)
        ↓
File backed up to logs with timestamp
```

---

### Patron Registration Flow

```
PATRON CHANGES NOTIFICATION PREFERENCE
        ↓
Staff updates in Polaris Client
├── DeliveryOptionID changed
├── UpdateDate = now
└── Phone1CarrierID set (if SMS)
        ↓
SCHEDULED TASK: Resolve Conflicts (3:00 AM)
        ↓
shoutbomb_conflicts.bat text|voice
        ↓
log_text_conflicts.sql or log_voice_conflicts.sql
├── Find accounts updated in past week
├── Find other accounts with same phone
└── Log conflicts
        ↓
resolve_text.sql or resolve_voice.sql
├── Update conflicting accounts
├── Change DeliveryOptionID
├── Set TxtPhoneNumber
└── Copy Phone1CarrierID
        ↓
SCHEDULED TASK: Upload Patrons (4:00 AM)
        ↓
shoutbomb.bat text_patrons
shoutbomb.bat voice_patrons
        ↓
text_patrons.sql / voice_patrons.sql query:
├── DeliveryOptionID = 8 or 3
├── Valid phone number
├── Not expired
└── Output: Phone|Barcode
        ↓
Upload to Shoutbomb FTP:
├── /text_patrons/text_patrons.txt
└── /voice_patrons/voice_patrons.txt
        ↓
Shoutbomb updates patron registry
```

---

### Courtesy Reminder Flow

```
ITEM DUE IN 3 DAYS
        ↓
SCHEDULED TASK: Upload Courtesy (6:00 AM)
        ↓
shoutbomb.bat renew
        ↓
renew.sql queries ItemCheckouts
├── DeliveryOptionID = 3 OR 8
├── DueDate = today + 3 days
└── MaterialTypeID != 12
        ↓
Output to C:\shoutbomb\ftp\renew\renew.txt
Format: PatronID|ItemBarcode|Title|DueDate|ItemID||||Renewals|BibID|RenewalLimit|PatronBarcode
        ↓
Upload to Shoutbomb FTP: /renew/renew.txt
        ↓
Shoutbomb delivers courtesy reminders

SPECIAL THURSDAY PROCESSING:
        ↓
THURSDAY: Upload Renew Thursday (6:00 AM)
        ↓
shoutbomb_renew_thursday.bat
        ↓
renew_thursday.sql (DueDate = today + 4 days)
        ↓
Accounts for Sunday not counting against loan period
```

---

## Advantages Over Standard PhoneNotices.csv

### 1. Direct Queue Integration

**Standard Export**:
- Polaris generates PhoneNotices.csv
- Contains redundant data already in NotificationQueue
- Requires parsing complex CSV format

**DCPL Method**:
- Queries NotificationQueue directly
- No duplicate exports
- Clean, simple pipe-delimited format

### 2. Flexible Scheduling

**Standard Export**:
- Single daily export of all notice types
- All-or-nothing approach
- Difficult to troubleshoot

**DCPL Method**:
- Independent schedule for each notice type
- Can adjust timing based on needs
- Easy to disable/test individual types

### 3. Better Conflict Resolution

**Standard Export**:
- No built-in conflict handling
- Manual resolution required
- Can cause Shoutbomb errors

**DCPL Method**:
- Automatic conflict detection
- Syncs shared phone numbers
- Prevents Shoutbomb errors
- Audit trail of all changes

### 4. Simplified Data Format

**Standard PhoneNotices.csv**:
```
"V","eng","2","1","23307015084918"," ","ROSEMARY","OBRIEN","2703139600"," ","DCPL",
"Daviess County Public Library","33307008042350","10/8/2025","We are all guilty here",
"3","1033","2","3","8130","895311","873091"," "," "," ",
```

**DCPL Format**:
```
We are all guilty here|2025-10-08|12345|8130|3|2025-10-15|23307015084918
```

Simpler, easier to debug, faster to process.

### 5. Comprehensive Logging

**Standard Export**:
- Limited logging
- Difficult to trace issues

**DCPL Method**:
- WinSCP logs for every upload
- Timestamped backups of all files
- Separate conflict resolution logs
- Full audit trail

### 6. Environment Variable Security

**Standard Export**:
- Credentials in config files

**DCPL Method**:
- FTP credentials in environment variable
- Not visible in scripts or logs
- Easy to update without editing files

---

## Monitoring & Maintenance

### Daily Checks

**Morning Routine** (8:00 AM):
```batch
:: Check if overnight tasks completed
dir C:\shoutbomb\logs\*_submitted_*.txt

:: Review conflict resolution logs
dir C:\shoutbomb\logs\text_conflicts_*.txt
dir C:\shoutbomb\logs\voice_conflicts_*.txt

:: Check WinSCP logs for errors
findstr /i "error" C:\shoutbomb\logs\*.log
```

**Expected Files**:
- `text_patrons_submitted_YYYY-MM-DD_HH-MM-SS.txt`
- `voice_patrons_submitted_YYYY-MM-DD_HH-MM-SS.txt`
- `renew_submitted_YYYY-MM-DD_HH-MM-SS.txt`
- `overdue_submitted_YYYY-MM-DD_HH-MM-SS.txt`
- `holds_submitted_YYYY-MM-DD_HH-MM-SS.txt`

### Troubleshooting Queries

**1. Check Pending Holds in Queue**:
```sql
SELECT COUNT(*) as PendingHolds
FROM Results.Polaris.NotificationQueue
WHERE NotificationTypeID = 2  -- Hold notices
  AND (DeliveryOptionID = 3 OR DeliveryOptionID = 8)
  AND Processed = 0;
```

**2. Find Recent Conflicts**:
```sql
-- Patrons sharing phone numbers with different notification methods
SELECT pr1.PatronID as Patron1, pr2.PatronID as Patron2,
       pr1.PhoneVoice1 as SharedPhone,
       pr1.DeliveryOptionID as Method1,
       pr2.DeliveryOptionID as Method2
FROM Polaris.Polaris.PatronRegistration pr1
JOIN Polaris.Polaris.PatronRegistration pr2 
  ON pr1.PhoneVoice1 = pr2.PhoneVoice1
WHERE pr1.PatronID < pr2.PatronID
  AND pr1.DeliveryOptionID IN (3, 8)
  AND pr2.DeliveryOptionID IN (3, 8)
  AND pr1.DeliveryOptionID != pr2.DeliveryOptionID
  AND pr1.ExpirationDate > GETDATE()
  AND pr2.ExpirationDate > GETDATE();
```

**3. Verify Upload Counts**:
```sql
-- Count holds that should be uploaded
SELECT COUNT(*) as HoldCount
FROM Results.polaris.NotificationQueue q (nolock)
JOIN Results.polaris.HoldNotices hn (nolock) 
  ON q.ItemRecordID=hn.ItemRecordID 
  AND q.PatronID=hn.PatronID
WHERE (q.DeliveryOptionID=3 OR q.DeliveryOptionID=8)
  AND hn.HoldTillDate>GETDATE();
```

**4. Check Last Update Times**:
```sql
-- When was PatronRegistration last modified?
SELECT TOP 10 PatronID, UpdateDate, DeliveryOptionID, PhoneVoice1
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID IN (3, 8)
ORDER BY UpdateDate DESC;
```

### Manual Upload (Emergency)

If scheduled tasks fail, manually upload:

```batch
cd C:\shoutbomb\scripts
shoutbomb.bat holds
shoutbomb.bat overdue
shoutbomb.bat renew
shoutbomb.bat text_patrons
shoutbomb.bat voice_patrons
```

### Testing Individual Components

**Test SQL Query**:
```batch
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\holds.sql -o C:\temp\test_holds.txt -h-1 -W -s "|"
```

**Test WinSCP Upload**:
```batch
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="C:\temp\test_upload.log" /ini=nul ^
  /command ^
    "open %shoutbomb%" ^
    "cd /holds" ^
    "ls" ^
    "exit"
```

---

## Common Issues & Solutions

### Issue 1: Holds Not Uploading

**Symptoms**:
- Patrons report not receiving hold notices
- holds_submitted_*.txt file missing or empty

**Diagnosis**:
```sql
-- Check NotificationQueue
SELECT * FROM Results.Polaris.NotificationQueue
WHERE NotificationTypeID = 2
  AND (DeliveryOptionID = 3 OR DeliveryOptionID = 8);

-- Check HoldNotices
SELECT * FROM Results.Polaris.HoldNotices
WHERE HoldTillDate > GETDATE();
```

**Possible Causes**:
1. No holds ready for pickup → Normal
2. Holds expired before upload → Adjust schedule
3. NotificationQueue not populated → Check Polaris notice generation
4. Wrong DeliveryOptionID → All holds set to email (ID=2)

**Solution**:
- Run `shoutbomb.bat holds` manually
- Check WinSCP log for errors
- Verify Shoutbomb FTP connectivity

---

### Issue 2: Conflicts Not Resolving

**Symptoms**:
- Multiple patrons with same phone show different delivery methods
- Shoutbomb rejects patron registration

**Diagnosis**:
```sql
-- Find unresolved conflicts
SELECT pr1.PatronID, pr2.PatronID, pr1.PhoneVoice1,
       pr1.DeliveryOptionID, pr2.DeliveryOptionID,
       pr1.UpdateDate, pr2.UpdateDate
FROM Polaris.Polaris.PatronRegistration pr1
JOIN Polaris.Polaris.PatronRegistration pr2 
  ON pr1.PhoneVoice1 = pr2.PhoneVoice1
WHERE pr1.PatronID < pr2.PatronID
  AND pr1.DeliveryOptionID IN (3, 8)
  AND pr2.DeliveryOptionID IN (3, 8)
  AND pr1.DeliveryOptionID != pr2.DeliveryOptionID;
```

**Possible Causes**:
1. Conflict resolution task not running
2. Accounts updated same day (outside 1-week window)
3. Expired accounts not filtered properly

**Solution**:
```batch
:: Manually run conflict resolution
C:\shoutbomb\scripts\shoutbomb_conflicts.bat text
C:\shoutbomb\scripts\shoutbomb_conflicts.bat voice

:: Then re-upload patrons
C:\shoutbomb\scripts\shoutbomb.bat text_patrons
C:\shoutbomb\scripts\shoutbomb.bat voice_patrons
```

---

### Issue 3: WinSCP Upload Fails

**Symptoms**:
- Error in WinSCP log
- Files not appearing on Shoutbomb FTP
- ERRORLEVEL != 0

**Diagnosis**:
```batch
:: Check most recent log
type C:\shoutbomb\logs\holds.log

:: Look for errors
findstr /i "error" C:\shoutbomb\logs\holds.log
```

**Common Errors**:

**"Can't connect to server"**:
- Check network connectivity
- Verify FTP credentials in %shoutbomb% variable
- Test connection in WinSCP GUI

**"Access denied"**:
- Verify username/password
- Check directory permissions
- Confirm SSL certificate if using FTPS

**"Timeout"**:
- Check firewall rules
- Verify proxy settings (ProxyPort=0 in script)
- Try increasing timeout

**Solution**:
```batch
:: Test connection manually
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="C:\temp\test.log" ^
  /command "open %shoutbomb%" "ls" "exit"
```

---

### Issue 4: Courtesy Reminders Missing

**Symptoms**:
- Patrons not receiving 3-day reminders
- renew_submitted_*.txt empty or missing

**Diagnosis**:
```sql
-- Check items due in 3 days
SELECT COUNT(*) as ItemsDueInThreeDays
FROM Polaris.ItemCheckouts ic (nolock)
JOIN Polaris.Polaris.PatronRegistration pr (nolock) 
  ON ic.PatronID=pr.PatronID
WHERE (pr.DeliveryOptionID=3 OR pr.DeliveryOptionID=8)
  AND CONVERT(varchar(11), ic.DueDate, 101) = CONVERT(varchar(11), GETDATE()+3, 101);
```

**Possible Causes**:
1. No items due in 3 days → Normal
2. All patrons using email → Expected
3. MaterialTypeID=12 filtered out → By design
4. Schedule not running → Check Task Scheduler

**Solution**:
- Verify task schedule
- Run `shoutbomb.bat renew` manually
- Check if Thursday schedule needed

---

### Issue 5: Thursday Reminders Not Working

**Symptoms**:
- Thursday reminders missing items due Sunday
- renew_thursday_submitted_*.txt not created

**Diagnosis**:
```sql
-- Check items due in 4 days (Thursday)
SELECT COUNT(*) as ItemsDueInFourDays
FROM Polaris.ItemCheckouts ic (nolock)
JOIN Polaris.Polaris.PatronRegistration pr (nolock) 
  ON ic.PatronID=pr.PatronID
WHERE (pr.DeliveryOptionID=3 OR pr.DeliveryOptionID=8)
  AND CONVERT(varchar(11), ic.DueDate, 101) = CONVERT(varchar(11), GETDATE()+4, 101);
```

**Possible Causes**:
1. Thursday task not scheduled
2. Task running wrong day
3. Regular renew task overriding

**Solution**:
- Import "Upload Renew Thursday.xml" task
- Set trigger to Thursday only
- Verify doesn't conflict with regular renew task

---

## Maintenance Tasks

### Weekly Maintenance

**1. Review Logs** (Every Monday):
```batch
:: Count successful uploads last week
dir C:\shoutbomb\logs\*_submitted_2025-11-*.txt | find /c ".txt"

:: Check for errors
findstr /i /c:"error" /c:"fail" C:\shoutbomb\logs\*.log
```

**2. Verify Patron Counts**:
```sql
-- Check SMS patron count trend
SELECT CAST(GETDATE() AS DATE) as Today,
       COUNT(*) as TextPatronCount
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID = 8
  AND PhoneVoice1 IS NOT NULL
  AND ExpirationDate > DATEADD(MONTH, -3, GETDATE());

-- Check voice patron count
SELECT CAST(GETDATE() AS DATE) as Today,
       COUNT(*) as VoicePatronCount
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID = 3
  AND PhoneVoice1 IS NOT NULL
  AND ExpirationDate > DATEADD(MONTH, -3, GETDATE());
```

**3. Conflict Resolution Audit**:
```batch
:: Review conflict logs
dir C:\shoutbomb\logs\text_conflicts_*.txt
dir C:\shoutbomb\logs\voice_conflicts_*.txt

:: Check if conflicts remain
sqlcmd -S DCPLPRO -d Polaris -i C:\shoutbomb\sql\conflicts\log_text_conflicts.sql
```

---

### Monthly Maintenance

**1. Archive Old Logs** (First of Month):
```batch
:: Move logs older than 90 days to archive
robocopy C:\shoutbomb\logs C:\shoutbomb\archive\logs_%date:~-4,4%-%date:~-10,2% *.txt /MINAGE:90 /MOV

:: Keep only last 3 months of WinSCP logs
forfiles /p C:\shoutbomb\logs /s /m *.log /d -90 /c "cmd /c del @path"
```

**2. Verify Task Schedules**:
- Open Task Scheduler
- Check all tasks in \Shoutbomb folder
- Verify "Last Run Result" = 0x0 (success)
- Check "Next Run Time" is correct

**3. Test Manual Execution**:
```batch
:: Test each upload type
FOR %%i IN (holds,overdue,renew,text_patrons,voice_patrons) DO (
    C:\shoutbomb\scripts\shoutbomb.bat %%i
)
```

**4. Review Shoutbomb Reports**:
- Log into Shoutbomb dashboard
- Check delivery success rates
- Review patron opt-outs
- Verify notification counts match uploads

---

### Quarterly Maintenance

**1. Review SQL Queries**:
- Check for slow performance
- Verify filters still appropriate
- Update date logic if needed

**2. Update WinSCP**:
- Download latest version
- Test with existing scripts
- Update if no issues

**3. Audit Patron Data Quality**:
```sql
-- Find patrons with invalid phone numbers
SELECT PatronID, PhoneVoice1, DeliveryOptionID
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID IN (3, 8)
  AND (PhoneVoice1 IS NULL 
       OR LEN(PhoneVoice1) < 10
       OR PhoneVoice1 NOT LIKE '[0-9]%');

-- Find expired patrons still registered
SELECT COUNT(*) as ExpiredWithNotifications
FROM Polaris.Polaris.PatronRegistration
WHERE DeliveryOptionID IN (3, 8)
  AND ExpirationDate < GETDATE();
```

---

## Disaster Recovery

### Backup Strategy

**Critical Files to Backup**:
```
C:\shoutbomb\
├── sql\                  # All SQL queries
├── scripts\              # All batch files
└── scheduled_tasks\      # Task XML files
```

**Backup Command**:
```batch
:: Weekly backup to network share
robocopy C:\shoutbomb \\fileserver\backups\shoutbomb /MIR /XD logs ftp /XF *.log *.txt
```

---

### Recovery Procedures

**Scenario 1: Server Rebuild**

1. Install WinSCP
2. Restore C:\shoutbomb from backup
3. Set %shoutbomb% environment variable
4. Import scheduled tasks from XML files
5. Test each upload manually
6. Enable scheduled tasks

**Scenario 2: Lost Environment Variable**

```batch
:: Retrieve from WinSCP saved session
"C:\Program Files (x86)\WinSCP\WinSCP.com" /script=C:\temp\get_url.txt

:: get_url.txt contains:
:: session generate "Shoutbomb"
:: exit
```

**Scenario 3: FTP Credentials Changed**

1. Update WinSCP saved session
2. Generate new session URL
3. Update %shoutbomb% environment variable
4. Test connection
5. No need to modify scripts

---

## Performance Optimization

### Query Optimization

**1. Add Indexes** (if not present):
```sql
-- NotificationQueue indexes
CREATE INDEX IX_NotificationQueue_DeliveryOption 
  ON Results.Polaris.NotificationQueue (DeliveryOptionID, NotificationTypeID);

CREATE INDEX IX_NotificationQueue_CreationDate 
  ON Results.Polaris.NotificationQueue (CreationDate);

-- PatronRegistration indexes
CREATE INDEX IX_PatronRegistration_PhoneVoice1 
  ON Polaris.Polaris.PatronRegistration (PhoneVoice1, DeliveryOptionID);
```

**2. Update Statistics** (Monthly):
```sql
UPDATE STATISTICS Results.Polaris.NotificationQueue;
UPDATE STATISTICS Results.Polaris.HoldNotices;
UPDATE STATISTICS Results.Polaris.OverdueNotices;
UPDATE STATISTICS Polaris.Polaris.PatronRegistration;
```

---

### Script Optimization

**Parallel Uploads**:
If multiple uploads can run simultaneously:

```batch
:: parallel_upload.bat
start /b shoutbomb.bat holds
start /b shoutbomb.bat overdue
start /b shoutbomb.bat renew
```

**Caution**: Only use if uploads don't interfere with each other

---

## Security Considerations

### 1. Environment Variable Protection

✅ **Good**: `%shoutbomb%` environment variable
- Not visible in Task Scheduler history
- Not in log files
- Easy to update centrally

❌ **Bad**: Hardcoded credentials in scripts
- Visible in Task Scheduler
- Appears in logs
- Must update each file

---

### 2. File System Permissions

**Recommended Permissions**:
```
C:\shoutbomb\
├── sql\          (Read for SERVICE_ACCOUNT)
├── scripts\      (Read + Execute for SERVICE_ACCOUNT)
├── ftp\          (Full Control for SERVICE_ACCOUNT)
└── logs\         (Full Control for SERVICE_ACCOUNT)
```

**Restrict Access**:
- Only IT staff should have full control
- Service account needs minimal permissions
- No public access

---

### 3. Audit Trail

All actions are logged:
- WinSCP logs (FTP activity)
- Submitted files (timestamped backups)
- Conflict resolution logs
- Task Scheduler history

---

## Change Log

### 2025-06-03
**Streamlined Scheduled Tasks**:
- Removed carrier-specific conflict resolution tasks
- Simplified to single text/voice resolution
- Removed unnecessary mobile provider tracking

**Added Patron Notes** (DCPL Only):
- Optional custom data field notes for account changes
- Commented out by default for other libraries

---

### 2020-08-22
**Initial Release**:
- Main upload script (shoutbomb.bat)
- Conflict resolution system
- SQL queries for all notice types
- Scheduled task XML files
- WinSCP integration

---

## Support & Credits

**Created By**: Brian Lashbrook (blashbrook@dcplibrary.org)  
**Organization**: Daviess County Public Library  
**License**: Free to use and modify

**Repository**: https://github.com/dcplibrary/shoutbomb-polaris-integration  
**Documentation**: https://docs.dcplibrary.org/shoutbomb-polaris-integration

---

## Quick Reference

### File Locations

| File | Location |
|------|----------|
| SQL Queries | `C:\shoutbomb\sql\` |
| Batch Scripts | `C:\shoutbomb\scripts\` |
| Output Files | `C:\shoutbomb\ftp\{type}\` |
| Logs | `C:\shoutbomb\logs\` |
| Scheduled Tasks | `C:\shoutbomb\scheduled_tasks\` |

### Key Commands

```batch
:: Manual upload
shoutbomb.bat holds
shoutbomb.bat overdue
shoutbomb.bat renew
shoutbomb.bat text_patrons
shoutbomb.bat voice_patrons

:: Conflict resolution
shoutbomb_conflicts.bat text
shoutbomb_conflicts.bat voice

:: Thursday special
shoutbomb_renew_thursday.bat
```

### Delivery Option IDs

- **1** = Mailing Address (not used for Shoutbomb)
- **2** = Email (not used for Shoutbomb)
- **3** = Phone Voice (Shoutbomb voice calls)
- **8** = Text Messaging (Shoutbomb SMS)

### Notification Type IDs

- **1** = 1st Overdue
- **2** = Hold Ready
- **7** = Almost Overdue/Auto-renew
- **8** = Fine Notice
- **11** = Bill
- **12** = 2nd Overdue
- **13** = 3rd Overdue

---

**Document Version**: 1.0  
**Last Updated**: November 5, 2025  
**For**: Daviess County Public Library Polaris-Shoutbomb Integration
