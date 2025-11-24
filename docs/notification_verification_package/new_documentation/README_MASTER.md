# NOTIFICATION VERIFICATION SYSTEM - DOCUMENTATION PACKAGE

**DC Public Library - Polaris ILS Notification Verification**  
**Version:** 1.0  
**Date:** November 19, 2025  
**System Owner:** Brian Lashbrook

---

## 📦 PACKAGE CONTENTS

This documentation package provides everything needed to build a comprehensive notification verification system for investigating patron complaints about missing hold and overdue notifications.

### Core Documents

1. **DATA_INTEGRATION_STRATEGY.md** ⭐ START HERE
   - Complete field availability matrix
   - What's available vs what needs SQL queries
   - Detailed SQL query specifications
   - Recommended database tables
   - Implementation roadmap

2. **VERIFICATION_QUERIES.sql**
   - Ready-to-use SQL queries
   - Organized by scenario
   - Copy-paste and run immediately
   - Includes patron lookup, hold verification, overdue verification

3. **QUICK_REFERENCE_GUIDE.md**
   - Quick lookup for staff
   - "Patron calls with X complaint" → "Use Query Y"
   - Common failure reasons & solutions
   - Proactive monitoring checklist

### Supporting Documentation

4. **TABLE_NotificationQueue.md**
   - NotificationQueue table structure
   - Field definitions and quirks

5. **TABLE_NotificationLog.md**
   - NotificationLog table structure  
   - Status codes and delivery tracking

6. **TABLE_HoldNotices.md**
   - HoldNotices table structure
   - Hold-specific notification details

7. **POLARIS_LOOKUP_TABLES.md**
   - Complete reference for lookup values
   - NotificationTypeID, DeliveryOptionID, NotificationStatusID
   - Language codes

---

## 🎯 WHAT THIS SOLVES

### The Problem
Patrons complain: *"I never got a notification my hold was ready"* or *"I never got an overdue notice"*

Staff currently have no way to verify:
- Was the notification actually sent?
- To what phone number or email?
- Did it fail? Why?
- When was it sent?

### The Solution
SQL queries + file parsing to provide complete verification:
- ✅ Show complete timeline (hold placed → filled → notified → delivered)
- ✅ Verify correct contact info was used
- ✅ Identify failures (invalid phone, opted out, etc.)
- ✅ Cross-reference with Shoutbomb submission files
- ✅ Proactive monitoring to catch issues before patrons complain

---

## 🚀 QUICK START GUIDE

### Step 1: Understand the Data Flow
\`\`\`
Hold Placed → Hold Filled → Notification Queued → Submitted to Shoutbomb → Delivered

Database Tables:
SysHoldRequests → HoldNotices → NotificationQueue → NotificationLog
                                        ↓
                                  Submission Files
                                  (holds.txt)
                                        ↓
                                    Shoutbomb
                                        ↓
                                  Failure Reports
\`\`\`

### Step 2: Run Your First Query

**Scenario:** Patron says they never got hold notification

1. Open **VERIFICATION_QUERIES.sql**
2. Find Query **1.1** or **1.2** to get PatronID
3. Run Query **2.1** to see all hold notifications
4. Look for:
   - \`NotificationStatusID\` - Was it delivered? (3=SMS success, 9=Voice success)
   - \`DeliveryString\` - What phone/email was used?
   - \`Details\` - Any error messages?

### Step 3: Interpret Results

Use **QUICK_REFERENCE_GUIDE.md** to understand what you found:
- Green ✅ = Notification sent successfully
- Yellow ⚠️ = Notification failed (invalid phone, etc.)
- Red ❌ = No notification queued at all

### Step 4: Take Action

Common actions:
- **Invalid phone** → Update patron record
- **Opted out** → Ask patron if they want notifications
- **No notification found** → System issue, needs investigation

---

## 📊 KEY DATA SOURCES

### Database Tables You'll Query

| Priority | Table | Contains | Need For |
|----------|-------|----------|----------|
| ⭐⭐⭐ | **Patrons** | Contact info, preferences | Every lookup |
| ⭐⭐⭐ | **PatronRegistration** | Name, all phone numbers | Every lookup |
| ⭐⭐⭐ | **SysHoldRequests** | Hold dates (placed, filled) | Hold timeline |
| ⭐⭐⭐ | **ItemCheckouts** | Checkout/renewal dates | Overdue timeline |
| ⭐⭐⭐ | **NotificationQueue** | Queued notifications | What should be sent |
| ⭐⭐⭐ | **NotificationLog** | Delivery status | What was sent |
| ⭐⭐ | **CircItemRecords** | Item details | Complete info |
| ⭐⭐ | **HoldNotices** | Hold-specific details | Hold verification |
| ⭐ | **BibliographicRecords** | Title/author | Nice to have |

### Files You'll Parse

Located on Shoutbomb FTP:

**Submission Files** (\`/outbound/\`)
- \`holds_submitted_YYYY-MM-DD_HH-MM-SS.txt\`
- \`overdue_submitted_YYYY-MM-DD_HH-MM-SS.txt\`
- \`text_patrons_submitted_YYYY-MM-DD_HH-MM-SS.txt\`
- \`voice_patrons_submitted_YYYY-MM-DD_HH-MM-SS.txt\`

**Failure Reports** (\`/inbound/reports/\`)
- \`shoutbomb_invalid_phones_YYYY-MM-DD.txt\`
- \`shoutbomb_voice_failures_YYYY-MM-DD.txt\`

---

## 🔍 WHAT DATA IS MISSING (Needs SQL)

### Critical Timeline Dates
❌ **Checkout Date** - When item was borrowed  
❌ **Renewal Dates** - Each time item was renewed  
❌ **Hold Placed Date** - When patron placed hold  
❌ **Hold Filled Date** - When item was trapped

**Solution:** Query \`ItemCheckouts\` and \`SysHoldRequests\` tables

### Complete Patron Contact
❌ **All phone numbers** - Voice1, Voice2, Voice3  
❌ **Patron full name** - First, Last, Middle  
❌ **Email address** - For email notifications

**Solution:** Query \`Patrons\` and \`PatronRegistration\` tables

### Item Details
❌ **Call number** - For staff reference  
❌ **Author** - Complete bibliographic info  
❌ **Material type** - Book vs DVD vs audiobook

**Solution:** Query \`CircItemRecords\` and \`BibliographicRecords\` tables


---

## 🛠️ IMPLEMENTATION OPTIONS

### Option A: Simple SQL Queries (Quick Start)
**Time:** 1-2 days  
**Effort:** Low  
**Tools:** SQL Management Studio + Excel

1. Use VERIFICATION_QUERIES.sql
2. Run queries manually when patron calls
3. Copy results to Excel for review

**Pros:** 
- Quick to implement
- No programming needed
- Immediate results

**Cons:**
- Manual process
- No automation
- Can't proactively monitor

---

### Option B: Web-Based Verification Tool (Recommended)
**Time:** 3-4 weeks  
**Effort:** Medium  
**Tools:** Laravel/PHP or Python/Flask + SQL Server

1. Build web interface
2. Staff enters patron barcode
3. System runs all queries automatically
4. Displays timeline and status
5. Highlights failures

**Pros:**
- User-friendly for staff
- Automated verification
- Can add proactive monitoring
- Export reports

**Cons:**
- Requires web development
- Needs hosting
- More complex

---

### Option C: Full Automation (Advanced)
**Time:** 5-6 weeks  
**Effort:** High  
**Tools:** Option B + scheduled jobs + email alerts

1. Everything from Option B
2. Daily automated checks
3. Email alerts for failures
4. Weekly summary reports
5. Dashboard with statistics

**Pros:**
- Catch issues before patrons complain
- Minimal manual intervention
- Comprehensive reporting

**Cons:**
- Most complex
- Requires maintenance
- Higher initial investment

---

## 📋 COMMON USE CASES

### Use Case 1: Patron Complaint - Hold Notification
**Patron says:** *"I never got notified my hold was ready"*

**Steps:**
1. Run Query 1.1 or 1.2 → Get PatronID
2. Run Query 2.1 → Get all hold notifications
3. Check for their hold:
   - ✅ Found + Status 3 or 9 = Was delivered
   - ⚠️ Found + Status 4 or 8 = Failed delivery
   - ❌ Not found = System issue

**Response Examples:**
- *"I see the notification was sent via SMS to 270-555-0101 on Nov 4 at 8:15 AM. Can you check that phone?"*
- *"The notification failed because the phone number 270-555-9999 is invalid. Can we update your contact info?"*
- *"I don't see any notification for this hold. Let me investigate and call you back."*

---

### Use Case 2: Patron Complaint - Overdue Notice
**Patron says:** *"I never got an overdue notice"*

**Steps:**
1. Run Query 1.1 or 1.2 → Get PatronID
2. Run Query 3.1 → Get all overdue notifications
3. Check their item:
   - ✅ Found + delivered = Was sent
   - ⚠️ Found + failed = Delivery issue
   - ❌ Not found = Check if opted out

**Key Fields to Check:**
- \`ExcludeFromOverdue\` = 1? → Patron opted out
- \`NotificationStatusID\` = 4? → Invalid phone
- \`DaysOverdue\` < 1? → Not yet due for notice

---

### Use Case 3: Daily Monitoring
**Goal:** Proactively find issues

**Morning Checklist:**
1. Run Query 2.3 → Holds that should be notified but weren't
2. Run Query 3.3 → Overdues that should be notified but weren't
3. Run Query 4.1 → Yesterday's failures
4. Run Query 4.3 → Notifications stuck in queue

**Weekly Review:**
1. Run Query 4.2 → Success rate by delivery method
2. Review trends
3. Identify systematic issues

---

### Use Case 4: Phone Number Changed
**Patron says:** *"I changed my phone number and I'm not getting texts"*

**Steps:**
1. Run Query 1.3 → Get all contact info
2. Verify which numbers are in system
3. Check DeliveryOptionID (should be 8 for SMS)
4. Check EnableSMS flag
5. Update as needed

**Common Issues:**
- New number in PhoneVoice1 but SMS still going to old TxtPhoneNumber
- DeliveryOptionID still set to 3 (Voice) instead of 8 (SMS)
- EnableSMS = 0 (not enabled)

---

### Use Case 5: Systematic Failure Pattern
**Symptom:** Multiple patrons complaining about same issue

**Investigation Steps:**
1. Run Query 4.1 for last 7 days
2. Group by NotificationStatusID
3. Look for patterns:
   - All SMS failing? → Shoutbomb issue
   - Specific branch? → Configuration issue
   - Specific notification type? → Export issue

**Example Analysis:**
\`\`\`sql
-- Check failure patterns
SELECT 
    NotificationStatusID,
    nst.Description AS FailureReason,
    COUNT(*) AS FailureCount
FROM PolarisTransactions.Polaris.NotificationLog nl
JOIN Polaris.Polaris.NotificationStatuses nst 
    ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE nl.NotificationDateTime >= DATEADD(day, -7, GETDATE())
  AND nl.NotificationStatusID IN (4, 5, 6, 8, 10)
GROUP BY nl.NotificationStatusID, nst.Description
ORDER BY FailureCount DESC;
\`\`\`

---

## 🔧 DATABASE CONNECTION SETUP

### SQL Server Connection String (PHP/Laravel)
\`\`\`php
'polaris' => [
    'driver' => 'sqlsrv',
    'host' => 'POLARIS-SQL',
    'database' => 'Polaris',
    'username' => env('DB_POLARIS_USERNAME'),
    'password' => env('DB_POLARIS_PASSWORD'),
    'charset' => 'utf8',
    'prefix' => '',
],
\`\`\`

### Python Connection (pyodbc)
\`\`\`python
import pyodbc

connection_string = (
    'DRIVER={ODBC Driver 17 for SQL Server};'
    'SERVER=POLARIS-SQL;'
    'DATABASE=Polaris;'
    'UID=username;'
    'PWD=password'
)

conn = pyodbc.connect(connection_string)
\`\`\`

### Important Notes
- You'll need connections to **three databases**: \`Polaris\`, \`PolarisTransactions\`, \`Results\`
- Use read-only credentials for verification queries
- Consider connection pooling for web apps


---

## 📈 EXPECTED RESULTS

### Normal Success Rates
Based on typical library notification systems:
- **SMS:** 85-95% success rate
- **Voice:** 70-85% success rate (lower due to no answer)
- **Email:** 90-98% success rate

### Common Failure Reasons (by frequency)
1. **No Answer (Status 5)** - 40-50% of failures (Voice only)
2. **Invalid Phone (Status 4)** - 30-40% of failures
3. **Opted Out (Status 10)** - 10-20% of failures
4. **Disconnected (Status 8)** - 5-10% of failures

### Red Flags to Watch For
- ⚠️ **Success rate < 80%** for SMS → Investigate immediately
- ⚠️ **Success rate < 70%** for Voice → Normal, but monitor
- ⚠️ **Sudden spike in failures** → System issue
- ⚠️ **Notifications stuck in queue > 24 hours** → Export failure

---

## 🎓 TRAINING GUIDE FOR STAFF

### What Staff Need to Know

#### Level 1: Basic Verification
**Who:** Front desk staff answering patron questions
**Skills Needed:** 
- Use patron barcode to look up info
- Read verification results
- Explain to patron what happened

**Training:** 30 minutes
1. How to search for patron
2. How to read timeline display
3. Common explanations for patrons

---

#### Level 2: Troubleshooting
**Who:** Circulation supervisors
**Skills Needed:**
- Run SQL queries
- Interpret failure codes
- Update patron records
- Identify patterns

**Training:** 2 hours
1. SQL query basics
2. Understanding status codes
3. How to fix common issues
4. When to escalate

---

#### Level 3: System Administration
**Who:** IT/System admin (you!)
**Skills Needed:**
- All of the above plus:
- Database optimization
- Monitoring automation
- FTP file parsing
- System troubleshooting

**Training:** Ongoing with this documentation

---

## 🔐 SECURITY & PRIVACY

### Patron Data Handling
- **Minimum necessary:** Only query data needed for verification
- **No logging:** Don't log patron phone numbers or personal info
- **Access control:** Limit query access to authorized staff
- **HIPAA/Privacy:** Follow library's patron privacy policies

### Best Practices
1. Use read-only database connections
2. Don't export patron contact info to files
3. Clear screen after verification
4. Train staff on privacy requirements
5. Document access logs (who verified what, when)

---

## 🐛 TROUBLESHOOTING

### Problem: Can't Find Patron by Barcode
**Possible Causes:**
- Barcode typo
- Patron has multiple cards
- Merged/deleted patron record

**Solution:**
\`\`\`sql
-- Search by partial barcode
SELECT PatronID, Barcode, PatronFullName 
FROM Patrons 
WHERE Barcode LIKE '%567890%';

-- Search by name
SELECT PatronID, Barcode, PatronFullName 
FROM Patrons 
WHERE PatronFullName LIKE '%Cooper%';
\`\`\`

---

### Problem: No Notifications Found for Patron
**Possible Causes:**
1. Patron opted out
2. Wrong delivery preference
3. Hold/checkout too old (outside query date range)
4. System never queued notification

**Investigation Steps:**
1. Check \`ExcludeFromOverdue\` flag
2. Check \`DeliveryOptionID\` - is it 3 or 8?
3. Expand date range in query
4. Check if item is actually overdue or hold is actually filled

---

### Problem: Notification Shows "Sent" But Patron Didn't Receive
**Possible Causes:**
1. Status hasn't been updated (still shows "Sent to Shoutbomb")
2. Patron deleted text/voicemail
3. Spam filter (email)
4. Wrong phone number in system

**Investigation Steps:**
1. Check actual phone number in \`DeliveryString\` field
2. Verify it matches patron's current number
3. Check Shoutbomb failure reports manually
4. Ask patron to check spam/blocked numbers

---

### Problem: Query Times Out or Very Slow
**Possible Causes:**
- Missing indexes
- Too broad date range
- Complex joins

**Solutions:**
\`\`\`sql
-- Add indexes (requires DBA permissions)
CREATE INDEX IDX_NotificationQueue_PatronItem 
ON Results.Polaris.NotificationQueue (PatronID, ItemRecordID, NotificationTypeID);

CREATE INDEX IDX_NotificationLog_DateTime 
ON PolarisTransactions.Polaris.NotificationLog (NotificationDateTime);

-- Narrow date range
WHERE CreationDate >= DATEADD(day, -7, GETDATE())  -- Instead of -30
\`\`\`

---

## 📞 GETTING HELP

### When SQL Queries Don't Work
**Check:**
1. Database connection working?
2. Table/field names spelled correctly?
3. PatronID/ItemRecordID values are valid?
4. Date ranges include the notification?

**Resources:**
- VERIFICATION_QUERIES.sql - Pre-tested queries
- TABLE_*.md files - Field definitions
- POLARIS_LOOKUP_TABLES.md - Valid codes

---

### When Results Don't Make Sense
**Check:**
1. Understanding status codes correctly? (See POLARIS_LOOKUP_TABLES.md)
2. Looking at right notification type? (Hold vs Overdue)
3. Dates in correct timezone?
4. Patron might have multiple notifications?

**Resources:**
- QUICK_REFERENCE_GUIDE.md - Status code meanings
- DATA_INTEGRATION_STRATEGY.md - Field explanations

---

### When You Need to Extend This System
**Scenarios:**
- Add new notification types
- Integrate with Polaris API
- Build automated monitoring
- Create dashboard

**Resources:**
- DATA_INTEGRATION_STRATEGY.md - Architecture section
- Polaris API documentation (Swagger spec in project files)
- Laravel papiclient package documentation


---

## 📚 ADDITIONAL RESOURCES

### In This Package
- ✅ DATA_INTEGRATION_STRATEGY.md - Comprehensive strategy
- ✅ VERIFICATION_QUERIES.sql - Ready-to-use SQL
- ✅ QUICK_REFERENCE_GUIDE.md - Staff quick reference
- ✅ TABLE_NotificationQueue.md - Queue table docs
- ✅ TABLE_NotificationLog.md - Log table docs
- ✅ TABLE_HoldNotices.md - Hold details docs
- ✅ POLARIS_LOOKUP_TABLES.md - Lookup value reference

### External Documentation
- Polaris API Swagger: \`Polaris-API-swagger.json\` (in project)
- Polaris Notification Guide: \`Polaris_Notification_Guide_PAPIClient.md\` (in project)
- Shoutbomb API documentation: (provided by Shoutbomb)

### Tools Mentioned
- **SQL Server Management Studio** - For running queries
- **Laravel papiclient** - PHP package for Polaris API (github.com/blashbrook/papiclient)
- **Python pyodbc** - Python SQL Server connector
- **Excel/CSV** - For parsing submission files

---

## ✅ IMPLEMENTATION CHECKLIST

### Phase 1: Setup (Week 1)
- [ ] Install SQL Server Management Studio
- [ ] Get read-only database credentials
- [ ] Test database connectivity
- [ ] Run first test query (Query 1.1)
- [ ] Verify results look correct

### Phase 2: Basic Verification (Week 2)
- [ ] Practice all patron lookup queries (1.1, 1.2, 1.3)
- [ ] Practice hold verification (2.1, 2.2)
- [ ] Practice overdue verification (3.1, 3.2)
- [ ] Document first real patron verification
- [ ] Train one staff member

### Phase 3: Proactive Monitoring (Week 3)
- [ ] Set up daily query runs (2.3, 3.3, 4.1, 4.3)
- [ ] Create monitoring schedule
- [ ] Document baseline success rates
- [ ] Set up alerting thresholds

### Phase 4: File Integration (Week 4)
- [ ] Access Shoutbomb FTP
- [ ] Parse submission files
- [ ] Parse failure reports
- [ ] Cross-reference with SQL results

### Phase 5: Automation (Weeks 5-6)
- [ ] Build web interface OR
- [ ] Create automated scripts
- [ ] Set up email alerts
- [ ] Train all staff
- [ ] Document processes

---

## 🎉 SUCCESS CRITERIA

You'll know this system is successful when:

1. **Staff Confidence**
   - Staff can confidently verify patron notifications
   - Staff can explain what happened to patrons
   - Staff know when to escalate issues

2. **Patron Satisfaction**
   - Fewer repeat complaints
   - Faster resolution of issues
   - Patrons trust the explanation

3. **Proactive Issue Detection**
   - System issues caught before patron complaints
   - Bad phone numbers identified and updated
   - Pattern recognition prevents widespread problems

4. **Measurable Improvements**
   - Notification success rate > 90%
   - Average verification time < 5 minutes
   - Complaint resolution time < 24 hours

---

## 📊 SAMPLE VERIFICATION REPORT

Here's what a complete verification should produce:

\`\`\`
═══════════════════════════════════════════════════════════════
PATRON NOTIFICATION VERIFICATION
Generated: 2025-11-19 15:30:00
═══════════════════════════════════════════════════════════════

PATRON: Richard Cooper (21234567890001)
Email: rcooper@example.com
Phone: 270-555-0101 (SMS enabled)
Preferred: SMS (Text Message)

NOTIFICATION: Hold Ready - "The Midnight Library"
─────────────────────────────────────────────────────────────
Item: 31234567890001 - The Midnight Library by Matt Haig
Hold Request: 500001
Pickup: Central Library (CENTR)

TIMELINE:
  Oct 28, 2025 14:30  Hold placed
  Nov 03, 2025 09:15  Hold filled (item trapped)
  Nov 03, 2025 10:00  Notification queued
  Nov 04, 2025 08:00  Submitted to Shoutbomb
  Nov 04, 2025 08:15  ✓ Delivered via SMS
  
DELIVERY STATUS: ✓ SUCCESS
  Status: Delivered (SMS) - Code 3
  Sent to: 270-555-0101
  Shoutbomb file: holds_submitted_2025-11-04_08-00-01.txt
  
VERIFICATION: Notification was successfully delivered to 
patron's SMS number on November 4, 2025 at 8:15 AM.

═══════════════════════════════════════════════════════════════
\`\`\`

---

## 🔄 ONGOING MAINTENANCE

### Daily Tasks (5-10 minutes)
- [ ] Check for stuck notifications (Query 4.3)
- [ ] Review yesterday's failures (Query 4.1)
- [ ] Fix any invalid phone numbers found

### Weekly Tasks (30 minutes)
- [ ] Review success rate trends (Query 4.2)
- [ ] Check for missing notifications (Queries 2.3, 3.3)
- [ ] Update any documentation as needed

### Monthly Tasks (1-2 hours)
- [ ] Generate comprehensive report
- [ ] Review and optimize slow queries
- [ ] Train new staff members
- [ ] Update lookup tables if Polaris codes changed

---

## 📖 GLOSSARY

**NotificationQueue** - Table of pending notifications that haven't been sent yet

**NotificationLog** - Aggregate log of sent notifications (one per patron contact)

**NotificationHistory** - Detailed log of sent notifications (one per item per patron)

**DeliveryOptionID** - Patron's preferred notification method (1=Mail, 2=Email, 3=Voice, 8=SMS)

**NotificationTypeID** - Type of notification (2=Hold, 1=Overdue, 7=Courtesy, etc.)

**NotificationStatusID** - Delivery result (3=SMS delivered, 4=Invalid phone, etc.)

**PatronID** - Internal Polaris patron identifier (never changes)

**PatronBarcode** - Library card number (can change if card replaced)

**ItemRecordID** - Internal Polaris item identifier

**SysHoldRequestID** - Unique identifier for each hold request

**Processed** - Flag indicating if notification was exported (0=pending, 1=sent)

**DeliveryString** - The actual phone number or email address used for delivery

**HoldTillDate** - Date when hold expires if not picked up

**Shoutbomb** - Third-party service that handles voice and SMS notifications

**FTP** - File Transfer Protocol, how we exchange files with Shoutbomb

---

## 🎯 NEXT STEPS

### If You're Just Starting
1. **Read DATA_INTEGRATION_STRATEGY.md** - Understand the big picture
2. **Open VERIFICATION_QUERIES.sql** - Look at the queries
3. **Try Query 1.1** - Look up a patron by barcode
4. **Read QUICK_REFERENCE_GUIDE.md** - Learn the common scenarios

### If You're Ready to Build
1. **Choose implementation option** (Simple SQL vs Web tool vs Full automation)
2. **Set up database connections** - Test connectivity
3. **Run all queries** - Make sure they work in your environment
4. **Modify as needed** - Adjust for your specific setup
5. **Train staff** - Start with one or two power users

### If You Hit Problems
1. **Check troubleshooting section** above
2. **Review table documentation** (TABLE_*.md files)
3. **Verify lookup codes** (POLARIS_LOOKUP_TABLES.md)
4. **Contact for help** - blashbrook@dcplibrary.org

---

## 📝 VERSION HISTORY

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-11-19 | Initial documentation package created |

---

## 📧 CONTACT & SUPPORT

**System Owner:**  
Brian Lashbrook  
Daviess County Public Library  
blashbrook@dcplibrary.org

**Documentation Issues:**  
Report errors or request clarifications via email

**Polaris Support:**  
Contact Innovative Interfaces for Polaris ILS issues

**Shoutbomb Support:**  
Contact Shoutbomb for delivery service issues

---

## ⚖️ LICENSE & USAGE

This documentation is created for DC Public Library's internal use. Feel free to adapt and modify for your library's needs.

**No warranty:** Use at your own risk. Test thoroughly in your environment.

**Data privacy:** Always follow your library's patron privacy policies when using these queries.

---

**Last Updated:** November 19, 2025  
**Documentation Version:** 1.0  
**Polaris Version:** Compatible with Polaris ILS 7.x

═══════════════════════════════════════════════════════════════
END OF MASTER DOCUMENTATION
═══════════════════════════════════════════════════════════════
