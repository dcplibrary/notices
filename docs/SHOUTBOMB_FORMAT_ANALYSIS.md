# Shoutbomb Integration Format Analysis
## Based on Actual Submitted Query Results

---

## File Formats

### 1. Holds (`holds_submitted_*.txt`)

**Format**: Pipe-delimited (`|`)
**Fields** (7 total):
```
Title|CreationDate|SysHoldRequestID|PatronID|PickupOrgID|HoldTillDate|PatronBarcode
```

**Example**:
```
Shatter me|2025-10-31|885042|20100|3|2025-11-04|23307013567732
```

**Field Breakdown**:
1. **Title** (`BrowseTitle`): Item title
2. **CreationDate**: When hold was created
3. **SysHoldRequestID**: Hold request ID from SysHoldRequests table
4. **PatronID**: Patron ID
5. **PickupOrgID**: Organization ID for pickup location (3 = DCPL main)
6. **HoldTillDate**: Date hold expires
7. **PatronBarcode**: Patron barcode (233070########)

**Key Observations**:
- Multiple holds for same patron appear as separate rows
- PatronID 32319 has 3 holds (Diary of Wimpy Kid + 2 Mary Poppins)
- PatronID 71468 has 3 holds (different titles)
- PatronID 8549 has 2 holds (both mysteries)
- All have PickupOrgID = 3 (DCPL)
- HoldTillDate typically 3-5 days after CreationDate

---

### 2. Overdues (`overdue_submitted_*.txt`)

**Format**: Pipe-delimited (`|`)
**Fields** (13 total with empty fields):
```
PatronID|ItemBarcode|Title|DueDate|ItemRecordID|||||OvdNoticeCount|BibRecordID|NotificationTypeID|PatronBarcode
```

**Example**:
```
16717|33307004430393|Baby, let's play house : Elvis Presley and the women who loved him|2025-10-31|241458|||||2|202197|2|23307014941845
```

**Field Breakdown**:
1. **PatronID**: Patron ID
2. **ItemBarcode**: Item barcode (333070########)
3. **Title**: Item title
4. **DueDate**: Original due date (in the past)
5. **ItemRecordID**: Item record ID
6-9. **Empty fields** (4 pipe separators with no data)
10. **OvdNoticeCount**: Overdue notice sequence (0=first, 1=second, 2=third/billing)
11. **BibRecordID**: Bibliographic record ID
12. **NotificationTypeID**: 2 (from NotificationTypes)
13. **PatronBarcode**: Patron barcode

**Key Observations**:
- **High-volume patrons**:
  - PatronID 3198: 3 overdue items (all due 2025-10-03)
  - PatronID 16717: 7 overdue items! (mix of dates: 10/21, 10/31)
  - PatronID 15563: 4 overdue items (all due 2025-10-10) - Amelia Bedelia books!
  - PatronID 19781: 2 overdue items (Berserk manga)
- OvdNoticeCount varies: 0, 1, or 2 (escalation levels)
- Some patrons have all items same due date (forgot together)
- Some have staggered due dates (accumulated over time)

---

### 3. Courtesy Reminders (`renew_submitted_*.txt`)

**Format**: Pipe-delimited (`|`)
**Fields**: Same as overdues (13 total)
```
PatronID|ItemBarcode|Title|DueDate|ItemRecordID|||||OvdNoticeCount|BibRecordID|NotificationTypeID|PatronBarcode
```

**Example**:
```
30829|33307008140873|The Correspondent : a novel|2025-11-07|883396|||||0|896962|2|23307014865218
```

**Key Observations**:
- **Massive volume for PatronID 30829**: 14 items due 2025-11-07!
  - Mix of kids books: Sunny, Pete the Cat, Fly Guy
  - All due same day (checked out together)
- DueDate is FUTURE (items not yet overdue)
- OvdNoticeCount typically 0 or 2
- NotificationTypeID = 2

**Pattern**: This is sent 3 days before due date (or 4 on Thursdays)

---

### 4. Text Patron Registration (`text_patrons_submitted_*.txt`)

**Format**: Pipe-delimited (`|`)
**Fields** (2 total):
```
PhoneNumber|PatronBarcode
```

**Example**:
```
2706452638|**014207734
2705703097|01042001
```

**Key Observations**:
- Simple phone|barcode format
- Some barcodes start with `**` (possibly inactive/deleted?)
- Some barcodes are short (legacy format?)
- Some barcodes are full 233070######## format
- Phone numbers are 10 digits (no formatting)
- Mix of 270 (local) and other area codes

**Purpose**: Registers patrons who want SMS notifications

---

### 5. Voice Patron Registration (`voice_patrons_submitted_*.txt`)

**Format**: Pipe-delimited (`|`)
**Fields** (2 total):
```
PhoneNumber|PatronBarcode
```

**Example**:
```
2709260288|001177015
2703026663|013745460
```

**Key Observations**:
- Same format as text_patrons
- Different patron list (voice vs SMS preference)
- Some patrons appear in BOTH files (conflict resolution needed)

**Purpose**: Registers patrons who want voice call notifications

---

## Emailed Reports

### 1. Invalid Patron Phone Number Report
**Purpose**: Alerts staff about patrons with invalid/missing phone numbers who are set for voice/SMS notifications

**Typical Issues**:
- Phone number missing
- Phone number improperly formatted
- International numbers
- Non-working numbers

### 2. Voice Notices Not Delivered Report
**Purpose**: Tracks voice notifications that failed to deliver

**Typical Reasons**:
- No answer (after retries)
- Busy signal
- Number disconnected
- Call rejected

---

## Cross-Reference Patterns Found

### Multi-Item Patrons in Holds:
```
PatronID 32319: 3 holds ready (Wimpy Kid + Mary Poppins movies)
PatronID 71468: 3 holds ready (fiction novels)
PatronID 66689: 2 holds ready (literary fiction)
PatronID 8549: 2 holds ready (thriller/mystery)
PatronID 78026: 2 holds ready (astronomy books)
PatronID 35919: 2 holds ready (Smurfs + Jenny Pen)
```

### Multi-Item Patrons in Overdues:
```
PatronID 3198: 3 overdues (all due same day 10/03)
PatronID 16717: 7 overdues! (music bios, philosophy, horror)
PatronID 15563: 4 overdues (Amelia Bedelia series)
PatronID 19781: 2 overdues (Berserk manga volumes)
PatronID 66689: 2 overdues (literary fiction)
```

### Multi-Item Patrons in Courtesy Reminders:
```
PatronID 30829: 14 items! (kids books, all due 11/07)
PatronID 66689: 2 items (due 11/07)
PatronID 56652: 2 items (due 11/07)
PatronID 39910: 2 items (due 11/07)
```

### Topic-Related Collections:
- **PatronID 15563**: Amelia Bedelia fan (4 books from series)
- **PatronID 16717**: Music/pop culture enthusiast (Elvis, Beatles, Dolly Parton)
- **PatronID 19781**: Manga reader (Berserk volumes)
- **PatronID 30829**: Parent/teacher (14 kids books)
- **PatronID 32319**: Family movies (Wimpy Kid + Mary Poppins)

---

## Data Generation Requirements

### For Realistic Shoutbomb Exports:

1. **Holds Export** - Need:
   - SysHoldRequests table populated
   - CreationDate, SysHoldRequestID, PatronID, PickupOrgID, HoldTillDate
   - Patron barcodes (233070########)
   - Multiple holds per patron (2-3 common)

2. **Overdue Export** - Need:
   - Item records with DueDates in past
   - PatronID, ItemBarcode, ItemRecordID, BibRecordID
   - OvdNoticeCount (0, 1, or 2 for escalation)
   - Some patrons with 3-7 overdues
   - Some with all items same DueDate, some staggered

3. **Courtesy/Renew Export** - Need:
   - Items due in future (3-4 days out)
   - Same format as overdues
   - Some patrons with many items (10+)

4. **Patron Registration Exports** - Need:
   - Phone numbers (270####### format)
   - Patron barcodes
   - Split between text_patrons and voice_patrons based on DeliveryOptionID

---

## Important Note: PhoneNotices.csv vs Shoutbomb Integration

**PhoneNotices.csv**:
- Standard Polaris export for voice/SMS notifications
- Exported to FTP server for record-keeping purposes only
- NOT used by the Shoutbomb integration
- Kept as a backup/historical record

**Shoutbomb Integration** (the actual workflow):
- Custom batch scripts in `shoutbomb/scripts/`
- SQL queries in `shoutbomb/sql/` that query Polaris databases directly
- Generates pipe-delimited files (holds.txt, overdue.txt, etc.)
- Uploads to Shoutbomb via WinSCP FTP

## SQL Query Implications

These files are generated by SQL queries in `shoutbomb/sql/`:
- `holds.sql` - Queries NotificationQueue + SysHoldRequests
- `overdue.sql` - Queries overdue items
- `renew.sql` - Queries items due in 3 days
- `text_patrons.sql` - PatronRegistration WHERE DeliveryOptionID = 8
- `voice_patrons.sql` - PatronRegistration WHERE DeliveryOptionID = 3

**Our generated data must match these query expectations!**

---

## Next Steps for Data Generation

1. ✅ Understand formats (DONE)
2. ✅ Identify cross-reference patterns (DONE)
3. ⏭️ Generate data matching these exact formats
4. ⏭️ Include patron scenarios found in real data:
   - Parent with 14 kids books
   - Music enthusiast with 7 overdues
   - Series collector (Amelia Bedelia, Berserk)
   - Multi-hold users (2-3 holds)
5. ⏭️ Create export scripts that match shoutbomb SQL queries
6. ⏭️ Validate output matches pipe-delimited format exactly
