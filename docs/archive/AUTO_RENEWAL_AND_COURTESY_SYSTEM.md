# Auto-Renewal and Courtesy Notification System

This document details the circulation policy including auto-renewals, courtesy reminders, and the Sunday rule.

## Circulation Policy

### Checkout and Renewal Period
- **Initial checkout**: 21 days
- **Auto-renewal**: If item not on hold, automatically renewed for 21 days
- **Maximum renewals**: 2 auto-renewals (total possible checkout: 63 days = 21 + 21 + 21)
- **After renewals exhausted**: Courtesy/almost overdue reminders sent
- **Then**: Regular overdue process begins

### Sunday Rule
**Important**: The last Sunday within a checkout period does NOT count toward the due date or hold expiration date.

This affects:
- Due date calculations
- Hold expiration dates
- Courtesy reminder timing

### Thursday Special Handling
On Thursdays, courtesy reminders must look 4 days ahead instead of 3 to account for the upcoming Sunday that won't count.

## Notification Timeline

### Before Due Date (Courtesy/Almost Overdue)
**Purpose**: Remind patrons to return or renew items before they become overdue

**Scheduled Tasks:**
- **Upload Courtesy**: 07:30 AM daily
- **Upload Renew**: 08:03 AM daily

**Logic:**
- **Regular days**: Check items due in 3 days (`getdate()+3`)
- **Thursdays**: Check items due in 4 days (`getdate()+4`) - accounts for Sunday

**SQL Queries:**
- `renew.sql` - Regular days (3 days ahead)
- `renew_thursday.sql` - Thursdays only (4 days ahead)

**Notification Type**: Type 7 - "Almost overdue/Auto-renew reminder"

**Delivery**: SMS/Voice only (DeliveryOptionID 3 or 8)

**Material Type Filter**: Excludes MaterialTypeID 12

### After Due Date (Overdue)
**Notification Type**: Type 1 - "1st Overdue"

**Scheduled Task**: Upload Overdue at 08:04 AM

**Delivery**: All methods (Email, SMS, Voice, Mail)

## Complete Daily Schedule

```
04:00 AM - Upload Voice Patrons
05:00 AM - Upload Text Patrons
07:30 AM - Upload Courtesy (almost overdue reminders)
08:00 AM - Polaris sends Email/Mail notifications
08:03 AM - Upload Renew (renewal reminders)
08:04 AM - Upload Overdue (SMS/Voice)
08:05 AM - Upload Holds (SMS/Voice) [1st batch]
09:05 AM - Upload Holds (SMS/Voice) [2nd batch]
13:05 PM - Upload Holds (SMS/Voice) [3rd batch]
17:05 PM - Upload Holds (SMS/Voice) [4th batch]
```

## Impact on Sample Data Generation

### Current Implementation
The current script generates:
- ✅ Hold notifications (Type 2)
- ✅ Overdue notifications (Type 1)
- ❌ Courtesy/almost overdue reminders (Type 7) - **NOT YET IMPLEMENTED**

### Missing Data Components

1. **Courtesy Notifications (Type 7)**
   - Items due in 3 days
   - SMS/Voice only
   - Timing: 07:30 AM (courtesy) and 08:03 AM (renew)
   - Should exclude MaterialTypeID 12

2. **Renewal Tracking**
   - Current script doesn't track `Renewals` count (0, 1, or 2)
   - Doesn't track `RenewalLimit`
   - Important for realistic checkout scenarios

3. **ItemCheckouts Table**
   - Not currently generated
   - Required for courtesy notification SQL queries
   - Contains: PatronID, ItemRecordID, DueDate, Renewals

4. **Sunday Rule Application**
   - Due dates should account for last Sunday not counting
   - Example: If checkout on Monday, due 21 days later but skip last Sunday = 22 calendar days

5. **Checked Out Items**
   - Current script only generates:
     - Items on hold (not yet checked out)
     - Overdue items (checked out but past due)
   - Missing: Currently checked out items (not yet due)

## Recommendations

### Option 1: Add Courtesy Notifications (Minimal)
Add Type 7 notifications for items due in 3 days:
- Generate some items with DueDate = TODAY + 3
- Create courtesy notifications at 07:30 or 08:03
- Only for SMS/Voice patrons

### Option 2: Full Circulation Lifecycle (Comprehensive)
Generate complete checkout lifecycle:
- ItemCheckouts table with Renewals count
- Items at various stages:
  - Currently checked out (0 renewals, due in 14 days)
  - Auto-renewed once (1 renewal, due in 7 days)
  - Auto-renewed twice (2 renewals, due in 3 days) → Courtesy notice
  - Overdue items (past due date)
- Apply Sunday rule to due date calculations
- Generate courtesy notifications (Type 7)

### Option 3: Current State (No Changes)
Keep focus on hold and overdue notifications only, which are the primary notification types.

## Questions for User

1. **Should we add courtesy notifications (Type 7)?**
   - These are sent 3 days before due date
   - Would make the data more complete
   - Requires generating currently-checked-out items

2. **Should we track renewal counts?**
   - Items with 0, 1, or 2 renewals
   - Adds realism to circulation scenarios
   - Shows progression through checkout lifecycle

3. **Should we apply the Sunday rule?**
   - More realistic due date calculations
   - Adds complexity to date math
   - Important for verifying your system's logic

4. **Do we need ItemCheckouts table?**
   - Required for courtesy reminder SQL queries
   - Shows current checkouts vs overdue items
   - Needed if we generate courtesy notifications

5. **Priority level?**
   - Is this critical for your testing?
   - Or are hold and overdue notifications sufficient?

## Current Data Status

**Generated:**
- 25 patrons
- 19 holds (Type 2 notifications)
- 28 overdues (Type 1 notifications)
- Proper timing for holds and overdues

**Not Generated:**
- Courtesy/almost overdue reminders (Type 7)
- Currently checked out items (not overdue)
- ItemCheckouts table
- Renewal count tracking
- Sunday rule application
