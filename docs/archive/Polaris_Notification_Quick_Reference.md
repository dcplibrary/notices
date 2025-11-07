# Polaris Notification System - Quick Reference Card
**Daviess County Public Library**

---

## Critical Tables at a Glance

| Table | Database | Purpose | Check Daily? |
|-------|----------|---------|--------------|
| NotificationQueue | Results.Polaris | Master notice queue | ✓ Yes |
| NotificationLog | PolarisTransactions.Polaris | Delivery log | ✓ Yes |
| HoldNotices | Results.Polaris | Holds ready for pickup | ✓ Yes |
| OverdueNotices | Results.Polaris | Overdue items | ✓ Yes |
| FineNotices | Results.Polaris | Outstanding balances | Weekly |
| CircReminders | Results.Polaris | Courtesy reminders | Weekly |
| PatronRegistration | Polaris.Polaris | Patron preferences | As needed |

---

## Notification Types Quick Lookup

| ID | Type | Common Delivery |
|----|------|-----------------|
| 1 | 1st Overdue | Email, SMS |
| 2 | Hold Ready | Email, SMS, Phone |
| 7 | Almost Overdue/Auto-renew | Email, SMS |
| 8 | Fine Notice | Email, Mail |
| 12 | 2nd Overdue | Email, Mail |
| 13 | 3rd Overdue (Billing) | Mail |
| 20 | Manual Bill | Mail |

---

## Delivery Options Quick Lookup

| ID | Method | Active? |
|----|--------|---------|
| 1 | Mailing Address | Yes |
| 2 | Email | Yes |
| 3 | Phone 1 (Voice) | Yes |
| 4 | Phone 2 (Voice) | Yes |
| 5 | Phone 3 (Voice) | Yes |
| 6 | FAX | Yes |
| 7 | EDI | Yes |
| 8 | SMS/Text | Yes |
| 9 | Mobile App | No |

---

## Notification Status Quick Lookup

**Success Statuses**: 1, 2, 12, 15, 16
- 1 = Call completed (person answered)
- 2 = Call completed (answering machine)
- 12 = Email completed
- 15 = Mail printed
- 16 = Sent (generic success)

**Failure Statuses**: 3-11, 13, 14
- Phone failures: 3-6 (busy, hang up, no answer, no ring)
- System failures: 7-11 (dial tone, intercept, bad number, retries, unknown)
- Email failures: 13-14 (invalid address, general failure)

---

## Daily Checklist

### Morning (8:00 AM)
- [ ] Check pending queue count (should be < 50)
- [ ] Review holds expiring today
- [ ] Check for stuck notices (> 2 hours old)
- [ ] Verify PhoneNotices.csv was generated

### Midday (12:00 PM)
- [ ] Monitor delivery failures
- [ ] Review high-value overdues (> $50)
- [ ] Check courtesy reminder count (should be ~1,700/day)

### End of Day (5:00 PM)
- [ ] Verify all notices processed (Processed=1)
- [ ] Review daily volume summary
- [ ] Check for orphaned records

---

## Quick SQL Checks

### Check Pending Notices
```sql
SELECT COUNT(*) FROM Results.Polaris.NotificationQueue WHERE Processed=0;
-- Should be: 0-50 normally
```

### Check Today's Processed Count
```sql
SELECT COUNT(*) 
FROM Results.Polaris.NotificationQueue 
WHERE Processed=1 AND CreationDate >= CAST(GETDATE() AS DATE);
-- Should be: 100-2000 depending on day
```

### Find Stuck Notices (> 2 hours)
```sql
SELECT COUNT(*) 
FROM Results.Polaris.NotificationQueue 
WHERE Processed=0 AND CreationDate < DATEADD(hour, -2, GETDATE());
-- Should be: 0
```

### Holds Expiring Today
```sql
SELECT COUNT(*) 
FROM Results.Polaris.HoldNotices 
WHERE HoldTillDate < DATEADD(day, 1, GETDATE());
-- Varies: 10-50 typical
```

### Recent Delivery Failures
```sql
SELECT COUNT(*) 
FROM PolarisTransactions.Polaris.NotificationLog 
WHERE NotificationDateTime >= CAST(GETDATE() AS DATE)
    AND NotificationStatusID NOT IN (1,2,12,15,16);
-- Should be: < 5% of total deliveries
```

---

## Alert Thresholds

### 🔴 CRITICAL (Take immediate action)
- Pending notices > 100
- Stuck notices > 4 hours old
- Holds expired without pickup > 10
- PhoneNotices.csv not generated
- Delivery failure rate > 10%

### 🟡 WARNING (Monitor closely)
- Pending notices 50-100
- Stuck notices 2-4 hours old
- High-value overdues (> $100) increasing
- Delivery failure rate 5-10%

### 🟢 NORMAL
- Pending notices < 50
- No stuck notices
- Delivery failure rate < 5%
- All exports running on schedule

---

## Common Issues & Quick Fixes

### Issue: Notices not sending
**Check**: NotificationQueue Processed flag
**Fix**: Restart notice processing service

### Issue: Wrong delivery method
**Check**: PatronRegistration.DeliveryOptionID
**Fix**: Update patron preference

### Issue: Duplicate notices
**Check**: NotificationLog for multiple entries
**Fix**: Review notice generation logic

### Issue: Missing email/phone
**Check**: PatronRegistration contact fields
**Fix**: Contact patron to update info

---

## File Locations

**PhoneNotices.csv Export**:
- Generated: Daily, early morning
- Contains: Voice (V) and SMS (T) notices
- Used for: Shoutbomb/third-party delivery

**Notice Processing**:
- Results.Polaris → Staging
- Polaris.Polaris → Master data
- PolarisTransactions.Polaris → Permanent logs

---

## Key Patron Fields

**Delivery Preferences**:
- `DeliveryOptionID` - Preferred method
- `EmailAddress` - Email for notifications
- `PhoneVoice1` - Primary phone
- `EnableSMS` - SMS opt-in flag

**Exclusion Flags**:
- `ExcludeFromOverdues` - No overdue notices
- `ExcludeFromHolds` - No hold notices
- `ExcludeFromBills` - No billing notices

---

## Typical Daily Volumes (DCPL)

| Notice Type | Typical Daily Count |
|-------------|---------------------|
| Courtesy Reminders | 1,500-1,800 |
| Hold Notices | 50-100 |
| Overdue Notices | 50-75 |
| Fine Notices | 20-40 |
| Manual Bills | 5-15 |

---

## Contact Information

**Library**: Daviess County Public Library (DCPL)
**Phone**: 270-684-0211
**Address**: 2020 Frederica Street, Owensboro, KY 42301

**For Polaris Support**: Contact your Polaris vendor

---

## Quick Links to Documentation

- **Complete Guide**: `Polaris_Complete_Notification_System_Guide.md`
- **SQL Queries**: `Polaris_Notification_Monitoring_Queries.sql`

---

**Last Updated**: November 5, 2025
**Version**: 1.0
