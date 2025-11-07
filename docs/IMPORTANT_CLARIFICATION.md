# IMPORTANT CLARIFICATION - PhoneNotices.csv

## The Actual Workflow

### PhoneNotices.csv
- **What it is**: Standard Polaris export for voice/SMS notifications
- **Purpose**: Record-keeping / backup only
- **Where it goes**: FTP server for historical records
- **Used by Shoutbomb?**: **NO** - This is NOT part of the integration

### Shoutbomb Integration (The Real Process)
1. **Scheduled Batch Files** run (in `shoutbomb/scripts/`)
   - `shoutbomb.bat holds`
   - `shoutbomb.bat overdue`
   - `shoutbomb.bat renew`
   - `shoutbomb.bat text_patrons`
   - `shoutbomb.bat voice_patrons`

2. **SQL Queries** (in `shoutbomb/sql/`) query Polaris databases directly
   - `holds.sql` - Queries for hold ready notifications
   - `overdue.sql` - Queries for overdue items
   - `renew.sql` - Queries for courtesy reminders
   - `text_patrons.sql` - Gets SMS-enabled patrons
   - `voice_patrons.sql` - Gets voice-enabled patrons

3. **Pipe-Delimited Files** generated (in `shoutbomb/ftp/`)
   - `holds.txt` (format: Title|CreationDate|HoldReqID|PatronID|OrgID|HoldTillDate|Barcode)
   - `overdue.txt` (format: PatronID|ItemBarcode|Title|DueDate|...)
   - `renew.txt` (same format as overdue)
   - `text_patrons.txt` (format: Phone|Barcode)
   - `voice_patrons.txt` (format: Phone|Barcode)

4. **WinSCP Upload** to Shoutbomb FTP server

5. **Shoutbomb** processes the files and makes calls/sends SMS

## Why This Matters

- The files in `shoutbomb/submitted-query-results/` are the REAL integration files
- These show the actual format and data that Shoutbomb receives
- PhoneNotices.csv is a separate Polaris feature, not part of the custom integration
- Our data generation should match the SQL query expectations, not PhoneNotices.csv format

## For Tomorrow

When resuming work, remember:
- The pipe-delimited format in submitted-query-results is what matters
- PhoneNotices.csv can be ignored for Shoutbomb integration purposes
- The Shoutbomb scripts bypass Polaris's built-in phone notification system
- This gives DCPL more control and flexibility over voice/SMS notifications
