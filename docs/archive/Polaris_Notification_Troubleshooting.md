# Polaris Notification System - Troubleshooting Flowchart
**Daviess County Public Library**

---

## Quick Diagnosis Decision Tree

```
PATRON REPORTS NOT RECEIVING NOTICES
        ↓
    Check 1: Is the patron record active?
        ↓
    NO → Activate patron record
        ↓
    YES → Check 2: Does patron have valid contact info?
        ↓
    NO → Update email/phone in PatronRegistration
        ↓
    YES → Check 3: Are they excluded from notices?
        ↓
    YES → Review exclusion flags (ExcludeFrom...)
        ↓
    NO → Check 4: Do they have the right delivery preference?
        ↓
    NO → Update DeliveryOptionID
        ↓
    YES → Check 5: Was notice generated?
        ↓
    NO → Check notice generation triggers
        ↓
    YES → Check 6: Was delivery attempted?
        ↓
    NO → Check NotificationQueue processing
        ↓
    YES → Check 7: Did delivery succeed?
        ↓
    NO → Check NotificationLog for failure reason
        ↓
    YES → Patron may have missed notice - resend
```

---

## Troubleshooting by Symptom

### Symptom 1: "No Notices Being Sent At All"

**Step 1: Check the Queue**
```sql
SELECT COUNT(*), MIN(CreationDate)
FROM Results.Polaris.NotificationQueue
WHERE Processed = 0;
```
- **If count > 100**: Queue is backed up → Go to Step 2
- **If count = 0**: No notices being generated → Go to Step 3

**Step 2: Queue Backed Up**
```sql
SELECT TOP 10 *
FROM Results.Polaris.NotificationQueue
WHERE Processed = 0
ORDER BY CreationDate;
```
**Possible Causes**:
- Notice processing service stopped
- Database connectivity issue
- Email/SMS gateway down

**Actions**:
1. Check service status
2. Review error logs
3. Test connectivity to mail server
4. Restart processing service if needed

**Step 3: No Notices Being Generated**
```sql
-- Check if there are items that should trigger notices
SELECT COUNT(*) FROM Results.Polaris.HoldNotices;
SELECT COUNT(*) FROM Results.Polaris.OverdueNotices;
```
**Possible Causes**:
- Notice generation job not running
- Hold shelf empty (for holds)
- No overdue items (for overdues)
- Generation trigger disabled

**Actions**:
1. Check scheduled jobs
2. Verify trigger conditions
3. Review hold shelf status
4. Check overdue item count

---

### Symptom 2: "Patron Says They Didn't Get Hold Notice"

**Diagnostic Flow**:

**Step 1: Verify Hold Status**
```sql
SELECT 
    shr.SysHoldRequestID,
    shr.SysHoldStatusID,
    shs.Description as HoldStatus,
    shr.HoldNotificationDate,
    shr.HoldTillDate
FROM Polaris.Polaris.SysHoldRequests shr
INNER JOIN Polaris.Polaris.SysHoldStatuses shs 
    ON shr.SysHoldStatusID = shs.SysHoldStatusID
WHERE shr.PatronID = [PatronID]
    AND shr.SysHoldStatusID = 6;  -- Status 6 = Held
```
**Check**: Is SysHoldStatusID = 6 (Held)?
- **NO**: Hold not actually ready → Investigate hold status
- **YES**: Hold is ready → Continue to Step 2

**Step 2: Check if Notice Was Generated**
```sql
SELECT *
FROM Results.Polaris.HoldNotices
WHERE PatronID = [PatronID]
    AND ItemRecordID = [ItemRecordID];
```
**Check**: Does record exist?
- **NO**: Notice not generated → Go to Step 3
- **YES**: Notice was staged → Go to Step 4

**Step 3: Why Wasn't Notice Generated?**
```sql
SELECT 
    pr.ExcludeFromHolds,
    pr.DeliveryOptionID,
    pr.EmailAddress,
    pr.PhoneVoice1,
    p.RecordStatusID
FROM Polaris.Polaris.PatronRegistration pr
INNER JOIN Polaris.Polaris.Patrons p ON pr.PatronID = p.PatronID
WHERE pr.PatronID = [PatronID];
```
**Possible Issues**:
- ExcludeFromHolds = 1 → Patron excluded
- RecordStatusID != 1 → Patron record inactive
- HoldNotificationDate not set in SysHoldRequests

**Actions**:
1. Update exclusion flag if incorrect
2. Activate patron record if needed
3. Manually set HoldNotificationDate
4. Manually generate notice

**Step 4: Was Notice Delivered?**
```sql
SELECT *
FROM PolarisTransactions.Polaris.NotificationLog
WHERE PatronID = [PatronID]
    AND NotificationTypeID = 2  -- Hold notices
ORDER BY NotificationDateTime DESC;
```
**Check Delivery Status**:
- **NotificationStatusID = 12**: Email sent successfully
- **NotificationStatusID IN (1,2)**: Phone call completed
- **NotificationStatusID IN (13,14)**: Email failed
- **NotificationStatusID IN (3-11)**: Phone failed

**Step 5: If Delivery Failed**
```sql
SELECT 
    ns.Description as FailureReason,
    nl.DeliveryString,
    nl.Details
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.NotificationStatuses ns 
    ON nl.NotificationStatusID = ns.NotificationStatusID
WHERE nl.PatronID = [PatronID]
    AND nl.NotificationTypeID = 2
    AND nl.NotificationStatusID NOT IN (1,2,12,15,16)
ORDER BY nl.NotificationDateTime DESC;
```
**Common Failure Reasons**:
- Status 13: Invalid email address → Update patron email
- Status 9: Bad phone number → Update patron phone
- Status 5: No answer → Normal, may need retry
- Status 14: Email failed → Check email server

**Actions Based on Failure**:
1. **Invalid Contact Info**: Update patron record
2. **Temporary Failure**: Resend notice
3. **Persistent Failure**: Contact patron directly

**Step 6: If Delivery Succeeded**
- Check patron's spam/junk folder
- Verify they're checking correct email/phone
- Resend notice as courtesy
- Extend hold if near expiration

---

### Symptom 3: "Overdue Notices Escalating Too Fast"

**Diagnostic Flow**:

**Step 1: Check Current Overdue Status**
```sql
SELECT 
    o.BillingNotice,
    COUNT(*) as Count,
    AVG(DATEDIFF(day, o.DueDate, GETDATE())) as AvgDaysOverdue
FROM Results.Polaris.OverdueNotices o
WHERE o.PatronID = [PatronID]
GROUP BY o.BillingNotice;
```

**Step 2: Review Notice Timeline**
```sql
SELECT 
    nl.NotificationDateTime,
    nt.Description as NoticeType,
    ns.Description as DeliveryStatus
FROM PolarisTransactions.Polaris.NotificationLog nl
INNER JOIN Polaris.Polaris.NotificationTypes nt 
    ON nl.NotificationTypeID = nt.NotificationTypeID
INNER JOIN Polaris.Polaris.NotificationStatuses ns 
    ON nl.NotificationStatusID = ns.NotificationStatusID
WHERE nl.PatronID = [PatronID]
    AND nl.NotificationTypeID IN (1, 12, 13)  -- Overdue types
ORDER BY nl.NotificationDateTime;
```

**Expected Timeline** (verify against your policy):
- Day 1: Due date
- Day 2-7: First overdue notice (BillingNotice=0)
- Day 8-14: Second overdue notice (BillingNotice=1)
- Day 15+: Billing notice (BillingNotice=2)

**Check for Issues**:
- Notices sent too close together? → Review scheduling
- Patron never received earlier notices? → Check delivery status
- Items jumped to billing too quickly? → Review escalation rules

---

### Symptom 4: "Fine Notices Not Being Sent"

**Diagnostic Flow**:

**Step 1: Check Patron Balance**
```sql
SELECT 
    p.PatronID,
    p.ChargesAmount,
    p.CreditsAmount,
    (SELECT MAX(NotificationDateTime) 
     FROM PolarisTransactions.Polaris.NotificationLog 
     WHERE PatronID = p.PatronID 
       AND NotificationTypeID IN (8, 21)) as LastFineNotice
FROM Polaris.Polaris.Patrons p
WHERE p.PatronID = [PatronID];
```
**Verify**: Is ChargesAmount > threshold (typically $25)?
- **NO**: Balance below threshold for notice
- **YES**: Should receive notice → Continue to Step 2

**Step 2: Check if Patron Excluded**
```sql
SELECT ExcludeFromBills
FROM Polaris.Polaris.PatronRegistration
WHERE PatronID = [PatronID];
```
**Check**: Is ExcludeFromBills = 1?
- **YES**: Patron is excluded → Update flag if incorrect
- **NO**: Not excluded → Continue to Step 3

**Step 3: Check Fine Notice Generation**
```sql
SELECT *
FROM Results.Polaris.FineNotices
WHERE PatronID = [PatronID];
```
**Check**: Does record exist?
- **NO**: Notice not generated → Check generation triggers
- **YES**: Notice generated → Check delivery

**Step 4: Review Fine Notice Schedule**
- Most libraries send fine notices weekly or monthly
- Check when last notice was sent
- Verify patron hasn't received too many notices recently

---

### Symptom 5: "Courtesy Reminders Not Working"

**Diagnostic Flow**:

**Step 1: Check CircReminders Generation**
```sql
SELECT COUNT(*)
FROM Results.Polaris.CircReminders
WHERE CreationDate >= CAST(GETDATE() AS DATE);
```
**Expected**: ~1,700 per day for DCPL
- **If 0**: Generation failed → Check scheduled job
- **If normal**: Generation working → Continue to Step 2

**Step 2: Check Specific Patron**
```sql
SELECT 
    cr.DueDate,
    cr.BrowseTitle,
    cr.DeliveryOptionID,
    cr.AutoRenewal,
    cr.CreationDate
FROM Results.Polaris.CircReminders cr
WHERE cr.PatronID = [PatronID]
    AND cr.CreationDate >= DATEADD(day, -7, GETDATE());
```
**Check**: Was reminder created?
- **NO**: Patron excluded or no items due soon
- **YES**: Reminder created → Check delivery

**Step 3: Verify Item Due Date**
- Reminders typically sent 3 days before due date
- Check if item's due date qualifies for reminder
- Verify item not already returned

---

### Symptom 6: "PhoneNotices.csv Not Generated"

**Diagnostic Flow**:

**Step 1: Check Export Job Status**
- Verify scheduled task/cron job is running
- Check job execution logs
- Confirm database connectivity

**Step 2: Check File System**
- Verify export directory exists
- Check file permissions
- Look for error logs in export directory

**Step 3: Test Export Query Manually**
- Run rpt_exportnotices.sql manually
- Check for SQL errors
- Verify output format

**Step 4: Check Data Availability**
```sql
SELECT COUNT(*)
FROM Results.Polaris.HoldNotices
WHERE DeliveryOptionID IN (3,4,5,8);  -- Voice and SMS

SELECT COUNT(*)
FROM Results.Polaris.OverdueNotices
WHERE DeliveryOptionID IN (3,4,5,8);
```
**Expected**: Should have records if notices are due

**Step 5: Verify Shoutbomb Integration**
- Check Shoutbomb logs for errors
- Verify file pickup process
- Test manual file upload if automated pickup fails

---

### Symptom 7: "Duplicate Notices Being Sent"

**Diagnostic Flow**:

**Step 1: Check NotificationQueue**
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
**Check**: Are there duplicate queue entries?
- **YES**: Queue has duplicates → Go to Step 2
- **NO**: Duplicates elsewhere → Go to Step 3

**Step 2: Identify Duplicate Source**
- Check if scheduled job running multiple times
- Review notice generation triggers
- Look for race conditions in processing

**Step 3: Check NotificationLog**
```sql
SELECT 
    PatronID,
    ItemRecordID,
    NotificationTypeID,
    NotificationDateTime,
    COUNT(*) as SendCount
FROM PolarisTransactions.Polaris.NotificationLog
WHERE NotificationDateTime >= CAST(GETDATE() AS DATE)
GROUP BY PatronID, ItemRecordID, NotificationTypeID, NotificationDateTime
HAVING COUNT(*) > 1;
```

**Possible Causes**:
- Processing job running concurrently
- Manual notices sent while automated running
- Queue not being marked as processed correctly

**Actions**:
1. Add duplicate detection to processing logic
2. Implement locking mechanism
3. Verify Processed flag being set correctly
4. Review scheduling to prevent overlap

---

## Emergency Response Procedures

### Emergency 1: Complete System Outage
**Symptoms**: No notices being sent, queue not processing

**Immediate Actions** (First 15 minutes):
1. Check database connectivity
2. Check email/SMS gateway status
3. Review service status (Windows Services or systemd)
4. Check error logs

**Temporary Workaround** (If can't fix quickly):
1. Generate manual notice lists for critical items:
   - Holds expiring today
   - High-value overdues
2. Make direct phone calls for critical notices
3. Post notice at circulation desk about potential delays

**Recovery Steps**:
1. Restart processing service
2. Clear stuck queue items
3. Verify notices resume processing
4. Monitor for backlog

---

### Emergency 2: Email Server Down
**Symptoms**: Email notices failing with status 14

**Immediate Actions**:
1. Verify email server status
2. Check SMTP configuration
3. Test email connectivity

**Temporary Workaround**:
1. Switch critical patrons to SMS temporarily:
   ```sql
   UPDATE Polaris.Polaris.PatronRegistration
   SET DeliveryOptionID = 8  -- SMS
   WHERE DeliveryOptionID = 2  -- Email
       AND PatronID IN ([Critical Patron List]);
   ```
2. Generate paper notices for holds expiring soon
3. Post notice at desk about email delays

---

### Emergency 3: SMS Gateway Down
**Symptoms**: Text messages failing with various statuses

**Immediate Actions**:
1. Check SMS gateway status
2. Verify carrier connections
3. Check PhoneNotices.csv generation

**Temporary Workaround**:
1. Switch critical patrons to email
2. Make direct phone calls for urgent holds
3. Increase hold shelf time to compensate

---

## Preventive Maintenance Checklist

### Daily
- [ ] Monitor queue health (pending count)
- [ ] Check delivery failure rates
- [ ] Verify exports running (PhoneNotices.csv)
- [ ] Review critical alerts

### Weekly
- [ ] Analyze delivery success rates by method
- [ ] Review patron contact information quality
- [ ] Clean up old processed queue items
- [ ] Check for orphaned records

### Monthly
- [ ] Archive old NotificationLog records
- [ ] Review notice volume trends
- [ ] Audit patron exclusion flags
- [ ] Test disaster recovery procedures
- [ ] Update documentation as needed

---

## Escalation Matrix

### Level 1: Circulation Staff
**Can Handle**:
- Individual patron notice issues
- Updating patron contact information
- Manual notice generation
- Hold shelf management

**Escalate When**:
- Multiple patrons affected
- System-wide delivery failures
- Database errors

### Level 2: Systems Administrator
**Can Handle**:
- Service restarts
- Database connectivity issues
- Scheduled job problems
- Export file issues

**Escalate When**:
- Database corruption
- Server hardware issues
- Vendor-level bugs

### Level 3: Polaris Vendor Support
**Can Handle**:
- Core system bugs
- Database structure issues
- Complex configuration problems
- Version-specific issues

---

**Last Updated**: November 5, 2025
**Version**: 1.0
