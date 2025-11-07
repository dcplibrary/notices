# Table Generation Status

This document tracks which tables have been regenerated with new sample data (PatronIDs 10000-10024) and which still contain old data.

## ✅ Tables with NEW Data (Already Generated)

These tables have been regenerated with proper cross-references and realistic data:

1. **Polaris.Polaris.Patrons.csv** (25 patrons)
   - PatronIDs: 10000-10024
   - Proper distribution of delivery preferences
   - Generated: ✅

2. **Polaris.Polaris.PatronRegistration.csv** (25 registrations)
   - 88% have both email and phone
   - Proper contact info validation for delivery methods
   - Generated: ✅

3. **Results.Polaris.HoldNotices.csv** (19 holds)
   - Cross-referenced with new PatronIDs
   - Proper hold notification dates
   - Generated: ✅

4. **Results.Polaris.NotificationHistory.csv** (47 records)
   - Item-level notification records
   - Correct timing (8:00 AM email/mail, 8:05/9:05/13:05/17:05 SMS/voice holds, 8:04 AM overdues)
   - Generated: ✅

5. **PolarisTransactions.Polaris.NotificationLogs.csv** (45 logs)
   - Patron-level combined notification records
   - Proper HoldsCount and OverduesCount
   - Correct DeliveryString matching
   - Generated: ✅

## ❌ Tables with OLD Data (Need Regeneration)

These tables still reference old PatronIDs and need to be regenerated:

### Priority 1: Core Notification Tables

1. **Polaris.Polaris.SysHoldRequests.csv**
   - Current: 4 old patrons (26240-63188)
   - Purpose: Source table for hold requests
   - Needs: Complete field list and SQL query
   - Should match: The 19 holds we generated

2. **Results.Polaris.OverdueNotices.csv**
   - Current: 5 old patrons (41613-115172)
   - Purpose: Overdue notice data for Shoutbomb
   - Needs: SQL query or sample showing all fields
   - Should match: The 28 overdues we generated

3. **Results.Polaris.NotificationQueue.csv**
   - Current: 5 old patrons (41613-115172)
   - Purpose: Queued notifications before processing
   - Needs: SQL query or sample showing format
   - Note: May not be needed if we're showing already-sent notifications

### Priority 2: Supporting Tables

4. **Polaris.Polaris.PatronAddresses.csv**
   - Current: 2 old patrons (78-186)
   - Purpose: Links patrons to their addresses
   - Needs: Should I generate physical addresses?

5. **Polaris.Polaris.Addresses.csv**
   - Current: Contains some addresses but no PatronID
   - Purpose: Physical address storage
   - Needs: Should addresses be generated for all 25 patrons?

### Priority 3: Optional Tables

6. **Results.Polaris.CircReminders.csv**
   - Current: 1 old patron (2666)
   - Purpose: Circulation reminders
   - Question: Is this needed for your testing?

7. **Results.Polaris.FineNotices.csv**
   - Current: 4 old patrons (6573-49636)
   - Purpose: Fine/fee notifications
   - Question: Should I generate fines for some patrons?

## 📋 Tables Needing Review

These are view tables or may not need PatronID updates:

1. **Polaris.Polaris.ViewHoldNoticesData.csv**
   - Purpose: View of hold notice data
   - Current: No PatronID found (may be ItemRecordID based)
   - Question: Does this need regeneration?

2. **Polaris.Polaris.ViewOverdueNoticesData.csv**
   - Purpose: View of overdue notice data
   - Current: No PatronID found
   - Question: Does this need regeneration?

## 🔍 Reference Tables (No Changes Needed)

These tables contain static reference data and should NOT be changed:

- Polaris.Polaris.DeliveryOptions.csv
- Polaris.Polaris.NotificationStatuses.csv
- Polaris.Polaris.NotificationTypes.csv
- Polaris.Polaris.SysHoldStatuses.csv
- Polaris.Polaris.ItemStatuse.csv
- Polaris.Polaris.AddressLabels.csv
- Polaris.Polaris.AddressTypes.csv
- PolarisTransactions.Polaris.TransactionTypes.csv
- PolarisTransactions.Polaris.TransactionSubTypes.csv

## 📁 Large Data Files

1. **Polaris.Polaris.CircReserveItemRecords_View.csv** (5.1M)
   - Purpose: Complete item/circulation view
   - Current: Large anonymized dataset
   - Question: Does this need regeneration or can we keep it as-is?

## 🎯 Next Steps

Please let me know:

1. **Which tables are priority?** (Probably SysHoldRequests, OverdueNotices, NotificationQueue)

2. **For priority tables, do you have:**
   - SQL queries that generate them?
   - Sample data showing all required fields?
   - Documentation on field meanings?

3. **Optional features:**
   - Should I generate physical addresses for patrons?
   - Should I generate fine/fee data?
   - Should CircReminders be regenerated?

4. **Can I skip:**
   - CircReminders.csv?
   - FineNotices.csv?
   - The large CircReserveItemRecords_View.csv?

Once I know which tables are priority and have the SQL/samples, I'll extend the script to generate them with proper cross-references and timing.
