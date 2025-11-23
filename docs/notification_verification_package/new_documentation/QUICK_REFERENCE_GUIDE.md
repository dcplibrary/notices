# NOTIFICATION VERIFICATION - QUICK REFERENCE GUIDE

**Version:** 1.0  
**Date:** November 19, 2025  
**For:** DC Public Library Staff

---

## 📞 WHEN A PATRON CALLS WITH A COMPLAINT

Use this guide to quickly find the right SQL query for each scenario.

---

## SCENARIO 1: "I never got a notification my hold was ready"

### Step 1: Find the Patron
**Use Query:** `1.1 - Find Patron by Barcode`
```sql
-- Patron provides library card number
WHERE Barcode = '21234567890001'
```

**Use Query:** `1.2 - Find Patron by Phone Number`
```sql
-- Patron provides phone number
WHERE PhoneVoice1 = '270-555-0101'
```

### Step 2: Get Their Contact Info
**Use Query:** `1.3 - Get Complete Patron Contact Information`
- Verify correct phone/email in system
- Check if they opted out
- Check delivery preference

### Step 3: Check Hold Notification History
**Use Query:** `2.1 - Get All Recent Hold Notifications for Patron`
- See all holds in last 30 days
- Check notification dates
- Check delivery status

### What to Look For:
- ✅ **Notification sent successfully** → Tell patron when it was sent, to what phone/email
- ⚠️ **Notification failed** → Check failure reason (invalid phone, opted out, etc.)
- ❌ **No notification in queue** → Use Query `2.3` to check if it should have been sent

---

## SCENARIO 2: "I never got an overdue notice"

### Step 1: Find the Patron (Use Queries 1.1 or 1.2)

### Step 2: Check Overdue Notification History
**Use Query:** `3.1 - Get All Recent Overdue Notifications for Patron`
- See all overdue items
- Check notification dates
- Check delivery status

### Step 3: Verify Checkout Timeline
**Use Query:** `3.2 - Get Checkout and Renewal Timeline for Specific Item`
- Confirm checkout date
- Check due dates
- Verify renewals

### What to Look For:
- ✅ **Notification sent successfully** → Tell patron when it was sent
- ⚠️ **Notification failed** → Check failure reason
- ❌ **Patron opted out** → `ExcludeFromOverdue = 1`
- ❌ **No notification queued** → System issue - investigate

---

## SCENARIO 3: "My phone number changed and I'm not getting notifications"

### Step 1: Get Current Contact Info
**Use Query:** `1.3 - Get Complete Patron Contact Information`
- Show what phone numbers are in system
- Check delivery preference

### Step 2: Check Recent Failed Notifications
**Use Query:** `4.1 - Get All Failed Notifications in Last 30 Days`
- Filter by PatronID
- Look for "Invalid Phone" status

### Action:
1. Update patron phone number in Polaris
2. Verify delivery preference is correct
3. Test with next notification

---

## SCENARIO 4: "I keep getting notifications to the wrong number"

### Step 1: Get Complete Contact Info
**Use Query:** `1.3 - Get Complete Patron Contact Information`
- See all phone numbers (PhoneVoice1, PhoneVoice2, PhoneVoice3, TxtPhoneNumber)
- Check which is set as preferred

### Step 2: Check Recent Notification Log
**Use Query:** `2.1` or `3.1` (depending on hold vs overdue)
- Look at `DeliveryString` field
- Verify which number was used

### What Might Be Wrong:
- DeliveryOptionID might not match their preference
- Multiple phone numbers and wrong one is set as Voice1
- SMS notifications going to TxtPhoneNumber field

---

## SCENARIO 5: "Lots of patrons are complaining about missing notifications"

### Daily Monitoring Queries:

#### Check for Failed Notifications
**Use Query:** `4.1 - Get All Failed Notifications in Last 30 Days`
```sql
WHERE NotificationDateTime >= DATEADD(day, -1, GETDATE())  -- Just today
```
- Group by failure reason
- Identify patterns (all SMS failing? specific branch?)

#### Check Success Rate
**Use Query:** `4.2 - Get Notification Success Rate by Delivery Method`
```sql
WHERE NotificationDateTime >= DATEADD(day, -7, GETDATE())  -- Last week
```
- Compare SMS vs Voice vs Email
- Normal success rate should be >90%

#### Check for Stuck Notifications
**Use Query:** `4.3 - Get Notifications Queued But Not Yet Processed`
- Find notifications stuck in queue
- Should be empty or very few records

---

## SCENARIO 6: "Can you check if this specific hold was notified?"

### When You Have SysHoldRequestID (from submission file):
**Use Query:** `2.2 - Get Specific Hold Request Details`
```sql
WHERE SysHoldRequestID = 500001
```

### When You Only Have Item Barcode:
**Use Query:** `5.2 - Item-Level Verification`
```sql
WHERE cir.ItemRecordID = (
    SELECT ItemRecordID FROM CircItemRecords WHERE Barcode = '31234567890001'
)
```

---

## SCENARIO 7: "Verify everything for this patron - full history"

### Use the Big Query:
**Use Query:** `5.1 - Complete Patron Notification History (All Types)`
- Shows EVERYTHING for patron in last 30 days
- Hold timeline
- Checkout timeline
- All notifications queued and sent
- Chronological order

This is the "nuclear option" - use when you need to see the complete picture.

---

## PROACTIVE MONITORING (RUN THESE DAILY)

### Morning Check: Missing Hold Notifications
**Use Query:** `2.3 - Find Holds That Should Have Been Notified (But Weren't)`
- Finds holds filled but not notified
- Run every morning to catch issues early

### Morning Check: Missing Overdue Notifications
**Use Query:** `3.3 - Find Overdue Items That Should Have Been Notified (But Weren't)`
- Finds items overdue but not notified
- Run every morning

### Daily Summary: Failures
**Use Query:** `4.1 - Get All Failed Notifications in Last 30 Days`
```sql
WHERE NotificationDateTime >= DATEADD(day, -1, GETDATE())
```
- Review all failures from yesterday
- Fix bad phone numbers
- Contact patrons if needed

### Weekly Summary: Success Rates
**Use Query:** `4.2 - Get Notification Success Rate by Delivery Method`
```sql
WHERE NotificationDateTime >= DATEADD(day, -7, GETDATE())
```
- Monitor trends
- Identify systematic issues

---

## QUERY SELECTION FLOWCHART

```
Patron calls about notification
        |
        ├─ Have patron barcode? → Query 1.1
        ├─ Have phone number? → Query 1.2
        └─ Have patron name? → Search in Polaris first
        |
        ▼
Get PatronID from result
        |
        ▼
What type of notification?
        |
        ├─ HOLD → Query 2.1 (All recent holds)
        ├─ OVERDUE → Query 3.1 (All recent overdues)
        └─ GENERAL → Query 5.1 (Everything)
        |
        ▼
Check notification status
        |
        ├─ Notification sent successfully
        │   └─ Tell patron: "Sent on [date] to [phone/email]"
        │
        ├─ Notification failed
        │   ├─ Invalid phone → Update phone number
        │   ├─ Opted out → Explain opt-out status
        │   └─ Other failure → Investigate
        │
        └─ No notification found
            ├─ Use Query 2.3 or 3.3 → Should it have been sent?
            └─ Check if patron opted out
```

---

## COMMON FAILURE REASONS & SOLUTIONS

| Failure Reason | Status ID | Solution |
|---------------|-----------|----------|
| Invalid Phone | 4 | Update phone number in patron record |
| Opted Out | 10 | Ask patron if they want to opt back in |
| No Answer | 5 | Normal - patron didn't answer |
| Disconnected | 8 | Update phone number |
| Invalid Email | 6 | Update email address |

---

## FIELD QUICK REFERENCE

### Patron Tables
```
Polaris.Polaris.Patrons
├─ PatronID (use this in all queries)
├─ Barcode (library card number)
├─ EmailAddress
├─ DeliveryOptionID (preferred method)
├─ EnableSMS
└─ ExcludeFromOverdue (opted out flag)

Polaris.Polaris.PatronRegistration
├─ NameFirst, NameLast
├─ PhoneVoice1 (primary phone)
├─ PhoneVoice2 (secondary phone)
└─ PhoneVoice3 (tertiary phone)
```

### Hold Tables
```
Polaris.Polaris.SysHoldRequests
├─ SysHoldRequestID (hold ID)
├─ RequestDate (when placed)
├─ FilledDate (when item pulled)
├─ HoldTillDate (expiration)
└─ HoldNotificationDate (when notified)

Results.Polaris.HoldNotices
├─ ItemBarcode
├─ BrowseTitle
├─ BrowseAuthor
└─ ItemCallNumber
```

### Checkout Tables
```
Polaris.Polaris.ItemCheckouts
├─ CheckOutDate (when borrowed)
├─ DueDate (original due date)
├─ RenewalCount (how many times renewed)
├─ LastRenewalDate (most recent renewal)
└─ LoanPeriodEndDate (current due date)
```

### Notification Tables
```
Results.Polaris.NotificationQueue
├─ CreationDate (when queued)
├─ NotificationTypeID (hold=2, overdue=1)
├─ DeliveryOptionID (SMS=8, Voice=3)
└─ Processed (0=pending, 1=sent)

PolarisTransactions.Polaris.NotificationLog
├─ NotificationDateTime (when sent)
├─ NotificationStatusID (delivery status)
├─ DeliveryString (phone/email used)
└─ Details (error messages)
```

---

## NOTIFICATION STATUS CODES (Quick Reference)

### Success Codes
- **1** - Voice call answered
- **2** - Voicemail
- **3** - SMS delivered ✓
- **7** - Email delivered ✓
- **9** - Voice delivered ✓
- **16** - Generic sent ✓

### Failure Codes
- **4** - Invalid phone ⚠️
- **5** - No answer
- **6** - Invalid email ⚠️
- **8** - Disconnected ⚠️
- **10** - Opted out ⚠️

---

## DELIVERY OPTION IDS

- **1** - Mail (postcard)
- **2** - Email
- **3** - Voice (phone call)
- **8** - SMS (text message)

**Note:** Shoutbomb only handles 3 (Voice) and 8 (SMS)

---

## NOTIFICATION TYPE IDS

- **1** - 1st Overdue
- **2** - Hold Ready ← Most common
- **7** - Almost Overdue / Courtesy
- **8** - Fine notice
- **11** - Bill notice
- **12** - 2nd Overdue
- **13** - 3rd Overdue

---

## TIME WINDOWS FOR QUERIES

| Query Purpose | Time Window | Why |
|--------------|-------------|-----|
| Hold notifications | 30 days | Holds expire in 3-7 days usually |
| Overdue notifications | 60 days | Can take multiple notices |
| Failed notifications | 30 days | Recent failures to fix |
| Success rate | 7-30 days | Trend monitoring |
| Stuck queue | 24-48 hours | Should be processed daily |

---

## TIPS & TRICKS

### Finding PatronID Quickly
```sql
-- By barcode (fastest)
SELECT PatronID FROM Patrons WHERE Barcode = '21234567890001';

-- By name (slower)
SELECT PatronID, PatronFullName, Barcode 
FROM Patrons 
WHERE PatronFullName LIKE '%Cooper%';

-- By phone (check multiple fields)
SELECT p.PatronID, p.Barcode, p.PatronFullName, pr.PhoneVoice1
FROM Patrons p
JOIN PatronRegistration pr ON p.PatronID = pr.PatronID
WHERE pr.PhoneVoice1 LIKE '%555-0101%'
   OR pr.PhoneVoice2 LIKE '%555-0101%'
   OR pr.PhoneVoice3 LIKE '%555-0101%';
```

### Checking If Notification Was in Submission File
After running SQL queries, also check:
1. Parse holds_submitted_YYYY-MM-DD_HH-MM-SS.txt
2. Look for PatronBarcode or SysHoldRequestID
3. Compare dates (should be same day as NotificationQueue.CreationDate)

### Understanding "Processed" Flag
```
Processed = 0 → Still in queue (pending)
Processed = 1 → Sent to Shoutbomb (but not necessarily delivered)
```

Check NotificationLog for actual delivery status!

---

## CONTACT FOR HELP

**Technical Issues:**
Brian Lashbrook  
blashbrook@dcplibrary.org

**Related Documentation:**
- DATA_INTEGRATION_STRATEGY.md
- VERIFICATION_QUERIES.sql
- TABLE_NotificationLog.md
- TABLE_NotificationQueue.md
- POLARIS_LOOKUP_TABLES.md

---

**Last Updated:** November 19, 2025
