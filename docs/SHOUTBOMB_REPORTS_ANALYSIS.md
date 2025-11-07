# Shoutbomb Reports Analysis

## Report Types

### 1. Monthly Report
**Frequency:** Monthly
**Sent to:** Library staff

**Contents:**
- Hold notification counts (text/voice, reminders)
- Overdue notification counts with renewal eligibility tracking
- Renewal/almost overdue notification counts
- Patron interaction statistics (keyword usage: HL, OA, OI, RI, RHL, STOP, etc.)
- Registration statistics (total users, new signups)
- Daily call volume statistics
- Opt-outs and invalid numbers (cumulative for month)
- Removed barcodes (expired cards)
- Card expiration notifications

**Key Metrics:**
- Total registered users: 18,506
- Text subscribers: 13,307 (72%)
- Voice subscribers: 5,199 (28%)
- Average daily voice call volume: 52 calls

### 2. Weekly Report
**Frequency:** Weekly (manually requested by library staff)
**Request Method:** Email to dcpl@shoutbomb with subject "WEEKLY_REPORT+{MM-YYYY}"
**Sent to:** Library staff

**Contents:**
- Weekly breakdown for each week of the month (Week 1, Week 2, etc.)
- New registrations per week (voice vs text)
- Hold notification counts (text/voice, reminders)
- Overdue notification counts with renewal eligibility
- Renewal/almost overdue notification counts
- Patron keyword usage for the week
- Invalid barcodes removed during the week
- Patron-initiated cancellations

**Format:**
- Multiple sections, one per week in the month
- Empty sections for future weeks (report can be requested mid-month)
- Same detailed metrics as monthly report but broken down weekly

**Example Week 1 (Nov 1-7, 2025):**
- 61 new registrations (10 voice, 51 text)
- 298 hold text notices, 79 hold voice notices
- 171 overdue text notices, 29 overdue voice notices
- 177 renewal text notices, 55 renewal voice notices
- 112 invalid barcodes removed
- RHL keyword used 1 time

### 3. Daily Invalid Phone Number Report
**Frequency:** Daily (around 6:00 AM)
**Subject:** "Invalid patron phone number [Date]"
**Sent to:** John Saalwaechter, Brian Lashbrook, support@shoutbomb.com

**Contents:**
Two sections:
1. **Opt-outs:** Phone numbers that have opted out from SMS/MMS
2. **Invalid numbers:** Phone numbers that appear invalid based on delivery attempts

**Format:**
```
phone :: barcode :: patron_id :: 3 :: SMS
```

**Example (Nov 4, 2025):**
- 3 opt-outs
- 3 invalid numbers

### 4. Daily Undelivered Voice Notices Report
**Frequency:** Daily (around 4:10 PM - after voice calls complete)
**Subject:** "Voice notices that were not delivered on [Date]"
**Sent to:** John Saalwaechter, Brian Lashbrook, support@shoutbomb.com

**Contents:**
List of voice notifications that failed to deliver

**Format:**
```
phone | barcode | library | patron_name | message_type
```

**Example (Nov 3, 2025):**
- 1 undelivered overdue item voice message
- Patron: COOPER, RICHARD (barcode 23307015135959)

## Notification Types in Reports

### Monthly Report Categories:
1. **Hold notices** - Initial hold ready notifications
2. **Hold reminders** - Second notification for holds not picked up
3. **Overdue notices** - Items past due date
   - Eligible for renewal
   - Ineligible for renewal
4. **Renewal notices** (Almost Overdue/Auto-renew reminders - Type 7)
   - Items eligible for renewal
   - Items ineligible for renewal
5. **Renewal reminders** - Second almost overdue notification

### Patron Response Keywords (Interactive System):
Shoutbomb allows patrons to reply to notifications with keywords to interact with the system:

- `HELP` - Get help with available commands
- `HL` - View hold list
- `MYBOOK` - View checked out items
- `OA` - Overdue All - show all overdue items
- `OI` - Overdue Ineligible - show items ineligible for renewal
- `OK` - Overdue eligible for renewal (?)
- `OL` - Overdue List (?)
- `ON` - Overdue Now (?)
- `RA` - Renew All - attempt to renew all eligible items
- `RHL` - Renewal Hold List (most used - 62 times/month)
- `RI` - Renewal Ineligible - show items that cannot be renewed
- `STOP` - Opt out of notifications
- `THANK`/`THANKS` - Thank you acknowledgment

**Patron Actions:** Patrons can renew items directly via SMS responses, check their account status, and manage their notifications.

## Key Insights

1. **Renewal vs Almost Overdue:** The monthly report uses "Renewal" terminology for what we call "Almost Overdue" or "Auto-renew reminder" (Type 7) notifications.

2. **Renewal Eligibility:** Shoutbomb tracks whether items are eligible or ineligible for renewal separately. This explains why some almost overdue notifications might not include renewal options.

3. **Interactive System:** Shoutbomb is a two-way interactive system. Patrons can reply to SMS notifications with keywords to:
   - Renew items (RA command)
   - Check their hold list (HL command)
   - View overdue items (OA, OI commands)
   - Manage notifications (STOP command)
   - High engagement: RHL keyword used 62 times in one month

4. **Error Tracking:** Daily reports help library staff identify and fix patron contact issues quickly, while monthly reports aggregate these for longer-term tracking.

5. **Voice vs Text Distribution:** About 72% of registered users prefer text notifications, 28% prefer voice calls.

6. **Timing:**
   - Invalid number report: 6:01 AM (before business hours)
   - Undelivered voice report: 4:10 PM (after voice call batches complete)

## Sample Data Implications

For our sample data generation, we should consider:
1. Including failed notification statuses for some SMS/Voice notifications
2. Adding more realistic patron scenarios with renewal eligibility tracking
3. Potentially tracking patron responses/keyword usage (future enhancement)
