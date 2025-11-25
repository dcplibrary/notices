# Notices Dashboard User Guide

This guide covers the features and functions of the Notices Dashboard.

---

## What Does This Dashboard Show?

Every notification your library sends to patrons - holds, overdues, renewals, etc. - and whether patrons actually received them.

**How you'll use it:**

- Answer "I never got my hold notification!" questions instantly
- Find out why notifications fail (wrong phone number, opted out, etc.)
- Export lists of patrons who need updated contact information
- See at-a-glance how well notifications are working

---

## Getting Started

### Accessing the Dashboard

Visit your library's notices dashboard at: `https://yourapp.com/notices`

You'll need to log in with your library account.

### Understanding the Main Screen

When you first arrive, you'll see the **Overview** page with:

📊 **Key Numbers at the Top**
- Total notifications sent in the last 7 days
- Success rate (what percentage got delivered)
- Failure rate (what percentage failed)

📈 **Charts in the Middle**
- Trend line showing notifications over time
- Pie charts showing types of notifications (holds, overdues, etc.)
- Breakdown by delivery method (email, SMS, voice, mail)

💡 **What the colors mean:**
- Green = Successful delivery
- Red = Failed delivery
- Yellow/Orange = In progress or unverified

---

## Common Tasks

### Looking Up a Patron's Notification

**Scenario:** Patron says "I never got my hold notification!"

**Steps:**

1. Click **"Verification"** in the top menu
2. In the search box, enter:
   - Patron's barcode (most common)
   - Phone number
   - Email address
   - Item barcode
3. Click **"Search"**

**What you'll see:**

A timeline showing exactly what happened:

```
✓ Created - Notice was created in Polaris
✓ Submitted - Sent to Shoutbomb/email
✗ Delivered - FAILED: Invalid phone number
```

**What to do next:**

- If it says **"Invalid phone number"** → Update patron's phone in Polaris
- If it says **"Opted out"** → Ask patron if they want to opt back in
- If it says **"Delivered"** → Patron may have deleted/missed it (suggest checking spam)

---

### Finding All Failed Notifications

**Scenario:** You want to clean up patron contact information.

**Steps:**

1. Click **"Troubleshooting"** in the top menu
2. Look at the **"Failures by Reason"** section
3. Click on any reason (like "Invalid Phone") to see full list
4. Click **"Export"** to download CSV
5. Open in Excel to review and fix patron records

**Common failure reasons:**

- **Invalid phone/email** - Contact info is wrong in Polaris
- **Opted out** - Patron chose not to receive notifications
- **System error** - Technical issue (rare - contact IT if you see many)
- **Undeliverable** - Carrier/email provider rejected the message

---

### Viewing a Patron's Complete Notification History

**Scenario:** Patron asks "What notifications have I received this month?"

**Steps:**

1. Click **"Verification"** in the top menu
2. Search for the patron
3. Click on any notification result
4. Click **"View Patron History"** button
5. Use the dropdown to change time range (7 days, 30 days, 90 days)
6. Click **"Export"** to give patron a printed report

---

### Checking if Notifications are Working Today

**Scenario:** Multiple patrons complaining about not receiving notifications.

**Steps:**

1. Go to **"Overview"** page
2. Look at today's numbers at the top
3. Compare to typical numbers

**What the numbers tell you:**

- **Big drop in success rate?** Something unusual is happening
- **Zero notifications sent?** No notifications were processed today
- **High failure rate?** Many patrons having delivery issues

**If numbers look unusual:**

Check the **"Troubleshooting"** page → **"Recent Failures"** section to see what's failing and why

---

## Understanding the Pages

### Overview Page

**What it shows:** Big-picture view of all notifications

**Use it when:** You want to see overall performance or trends

**Key sections:**
- Stats at the top (total, success, failures)
- Line chart showing daily trends
- Pie charts showing distribution by type and method

---

### Notifications List

**What it shows:** Every individual notification (can filter and search)

**Use it when:** You need to find specific notifications or see details

**Filters available:**
- Date range
- Notification type (Hold, Overdue, Renewal)
- Delivery method (Email, SMS, Voice, Mail)
- Status (Success, Failed)
- Patron barcode or name

**Tips:**
- Click any notification to see full details
- Use "Export" to get a spreadsheet of results
- Combine filters to narrow down (example: "Failed SMS holds from last week")

---

### Verification Page

**What it shows:** Search tool for looking up specific notices

**Use it when:** A patron has a question about their notification

**Search by:**
- Patron barcode (recommended)
- Phone number or email
- Item barcode

**What you get:**
- Complete timeline of the notification
- Delivery status and reason for failure (if any)
- Link to see all notifications for that patron

---

### Troubleshooting Page

**What it shows:** Analysis of failures and problems

**Use it when:** You want to fix systemic issues or clean up patron data

**Key sections:**
- **Failures by Reason** - What types of problems are most common
- **Recent Failures** - Last 50 failures with details
- **Verification Gaps** - Notices that should have been verified but weren't

**Best practice:** Check this weekly to catch issues early

---

### Shoutbomb Page

**What it shows:** SMS/Voice subscriber statistics (if using Shoutbomb)

**Use it when:** You want to track SMS enrollment or keyword usage

**What's included:**
- Current subscriber counts
- Growth over time
- Keyword usage (RHL for hold list, RA for renew all, etc.)

---

## Tips and Best Practices

### Daily Routine

**Start of day:**
1. Glance at Overview page - does everything look normal?
2. Check Troubleshooting page for any new failures

**When helping patrons:**
1. Use Verification page to search for their notifications
2. Show them the timeline so they can see what happened
3. Fix their contact info if needed

**Weekly maintenance:**
1. Export list of failed notifications
2. Update patron contact information in Polaris
3. Re-check next week to see if issues are resolved

---

### Reading the Timeline

When you search for a patron, you'll see a timeline with these steps:

1. **Created** ✓ - Polaris created the notification
2. **Submitted** ✓ - Sent to delivery service (Shoutbomb, email, etc.)
3. **Verified** ✓ - Delivery service confirmed they received it
4. **Delivered** ✓ - Patron actually got the message

**If something shows a ✗ (X):**

That's where the problem happened. The reason will be shown next to it.

**Common timeline patterns:**

- `✓ ✓ ✓ ✓` = Perfect! Everything worked.
- `✓ ✓ ✗` = Sent to delivery service but failed (invalid phone, opted out)
- `✓ ✗` = Failed to send (rare - usually a system issue)

---

### Understanding Success Rates

**What's a good success rate?**

- **90-95%** = Excellent
- **80-90%** = Good (some patrons will have invalid contacts)
- **Below 80%** = Investigate - might be systemic issue

**Why do some notifications fail?**

- Patrons change phone numbers/emails
- Patrons opt out of notifications
- Phone numbers or emails entered incorrectly in Polaris
- Technical issues (rare)

**You can't get 100%** - some patrons will always have outdated contact info or opt out. That's normal!

---

## Common Questions

### "How far back can I search?"

Use the date range dropdown on any page to search historical data. Most libraries keep 90 days to 1 year of notification history.

---

### "Can I see notifications from a specific branch?"

The dashboard shows all branches in your library system. Use the filters on the Notifications List page to narrow down results by patron, date, or notification type.

---

### "Why does it say 'Unverified'?"

"Unverified" means we're still waiting for confirmation from the delivery service about whether the notification was delivered. Check again tomorrow - most unverified notifications will update to either "Delivered" or "Failed" within 24 hours.

---

### "Can I print or save results?"

Yes! Every page with a list or search results has an **"Export"** button that downloads a spreadsheet (CSV file) you can open in Excel.

---

### "How do I know if a patron opted out of notifications?"

Search for the patron on the Verification page. If they opted out, you'll see "Opted Out" in the failure reason. You'll need to ask the patron if they want to opt back in through their usual notification settings.

---

## Quick Reference

| **I want to...** | **Go to...** | **Do this...** |
|------------------|--------------|----------------|
| Look up a patron's notification | Verification page | Search by patron barcode |
| See why notifications failed | Troubleshooting page | Check "Failures by Reason" |
| Get a list of invalid phone numbers | Troubleshooting page | Click "Invalid Phone" → Export |
| Check today's stats | Overview page | Look at numbers at the top |
| See a patron's full history | Verification page | Search patron → View History |
| Export data for reports | Any page | Click "Export" button |
| Track SMS enrollment | Shoutbomb page | View subscriber counts |

---

**Remember:** This dashboard is here to help you answer patron questions quickly and keep contact information up to date. Check it daily to stay on top of any notification issues!
