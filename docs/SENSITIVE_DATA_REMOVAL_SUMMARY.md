# Sensitive Data Removal Summary

## Overview
All real patron data has been removed from the repository. Report structures and statistics have been preserved for documentation purposes, but all personal information has been replaced with fake data.

## Files Deleted

### Shoutbomb Emailed Reports (2 PDFs)
**Location:** `shoutbomb/emailed-reports/`

1. **Invalid patron phone number Tue, November 4th 2025.pdf**
   - Contained real phone numbers, barcodes, and patron IDs
   - Showed opt-outs and invalid numbers

2. **Voice notices that were not delivered on Mon, November 3rd 2025.pdf**
   - Contained real phone number, barcode, patron name
   - Showed failed voice call delivery

### Shoutbomb Query Results (6 files, 23,132 lines of data)
**Location:** `shoutbomb/submitted-query-results/`

1. **holds_submitted_2025-11-04_08-05-01.txt**
   - Real patron barcodes linked to specific items

2. **holds_submitted_2025-05-13_13-05-01.txt**
   - Real patron barcodes linked to specific items

3. **overdue_submitted_2025-11-04_08-04-01.txt**
   - Real patron barcodes with overdue items

4. **renew_submitted_2025-11-04_08-03-01.txt**
   - Real patron barcodes with renewal information

5. **voice_patrons_submitted_2025-11-04_04-00-01.txt**
   - Real phone numbers and patron barcode fragments

6. **text_patrons_submitted_2025-11-04_05-00-01.txt**
   - Real phone numbers and patron barcode fragments

## Files Anonymized

### Email Summary Reports
**Location:** `polaris-databases/sample-data/`

1. **Email_Summary_Report_Sample.txt** (September 2025)
   - Replaced real patron names with "PATRON ONE", "PATRON TWO", etc.
   - Replaced real emails with patron@example.com format
   - Replaced real barcodes with 23307010000XXX series
   - Replaced real phone numbers with 270555XXXX series

2. **Email_Summary_Report_November_Sample.txt** (November 2025)
   - Replaced real patron names with Greek letter names (PATRON ALPHA, PATRON BETA, etc.)
   - Replaced real emails with patron.alpha@example.com format
   - Replaced real barcodes with 2330701XXXXXXX series
   - Replaced real phone numbers with 270555XXXX series
   - **Key feature preserved:** Shows 79 email + 72 SMS almost overdue notifications

### Shoutbomb Reports
**Location:** `polaris-databases/sample-data/`

1. **Shoutbomb_Monthly_Report_Sample.txt** (October 2025)
   - Replaced real phone numbers in opt-out list with fake numbers
   - Replaced real barcodes with fake barcodes
   - Removed full list of invalid barcodes (kept structure with placeholder)
   - Preserved all statistics and keyword usage data

2. **Shoutbomb_Weekly_Report_Sample.txt** (November 2025 Week 1)
   - Removed full list of invalid barcodes (kept structure with placeholder)
   - Preserved weekly statistics and new registration counts

## Additional Security Measures

### Generated Sample Data - Phone Number Update (November 6, 2025)
**Location:** `polaris-databases/sample-data/*.csv`

All generated patron phone numbers were updated to use the reserved "555" exchange:
- **Before:** Random 7-digit numbers (e.g., 2707082668) with theoretical risk of matching real numbers
- **After:** 270-555-01XX format (e.g., 2705550187) - guaranteed fictional
- **Standard:** Uses 555-0100 through 555-0199 range, reserved for fictional use in North America
- **Impact:** All 25 generated patron records now have verifiably fake phone numbers

This ensures zero possibility of accidentally matching real patron phone numbers.

## What Was Preserved

All report structures, formats, and statistics were preserved:
- Notification counts by type and delivery method
- Keyword usage statistics
- Registration statistics (13,307 text, 5,199 voice subscribers)
- Daily call volume data
- Percentages and ratios
- Report timestamps and date ranges
- Section headers and formatting

## Verification

After removal, the repository contains:
- ✅ Generated fake patron data (PatronIDs 10000-10024)
- ✅ Reference/lookup tables (no patron data)
- ✅ Documentation files
- ✅ Anonymized report samples
- ✅ SQL queries and batch scripts (no patron data)
- ✅ Configuration files

## Git History Note

The sensitive data was removed in commits:
1. `62300dc` - Removed sensitive data from emailed reports (PDFs and report text files)
2. `04f12b4` - Removed sensitive data from Shoutbomb query results (23,132 lines)

**Important:** The sensitive data still exists in the git history prior to these commits. If this repository needs to be made public, consider using `git filter-branch` or BFG Repo-Cleaner to completely remove the sensitive data from git history.

## Commands Used

```bash
# Deleted PDFs
rm shoutbomb/emailed-reports/*.pdf

# Deleted query result files
rm shoutbomb/submitted-query-results/*.txt

# Anonymized report text files
# (Manual edit to replace real data with fake data)
```

## Total Data Removed

- **8 files deleted** (2 PDFs + 6 query result files)
- **24,170 lines of sensitive data removed** (1,038 from reports + 23,132 from query results)
- **4 files anonymized** (preserved structure, replaced content)
