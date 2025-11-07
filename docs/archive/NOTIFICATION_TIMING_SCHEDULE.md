# Notification Timing Schedule

This document details the exact timing of all notification processes based on Windows scheduled tasks and Polaris configuration.

## Daily Schedule

### 04:00 AM - Upload Voice Patrons
**Task:** `Upload Voice Patrons`
**Script:** `shoutbomb.bat voice_patrons`
**Purpose:** Upload list of patrons with voice notification preference to Shoutbomb
**Frequency:** Daily

### 05:00 AM - Upload Text Patrons
**Task:** `Upload Text Patrons`
**Script:** `shoutbomb.bat text_patrons`
**Purpose:** Upload list of patrons with SMS notification preference to Shoutbomb
**Frequency:** Daily

### 07:30 AM - Upload Courtesy Notifications
**Task:** `Upload Courtesy`
**Script:** `upload_courtesy.bat`
**Purpose:** Upload courtesy/almost overdue reminders to Shoutbomb
**Frequency:** Daily
**Target:** Items due in 3 days (or 4 days on Thursdays for Sunday rule)
**Notification Type:** Type 7 - "Almost overdue/Auto-renew reminder"
**Delivery:** SMS/Voice only
**Note:** Sent BEFORE items become overdue to remind patrons to renew or return

### 08:00 AM - Email and Mail Notifications
**System:** Polaris ILS
**Delivery Methods:** Email (DeliveryOptionID=2), Mail (DeliveryOptionID=1)
**Purpose:** Polaris sends email notifications and prints mail notifications
**Frequency:** Daily
**Note:** This is configured in Polaris, not in scheduled tasks

### 08:03 AM - Upload Renew Reminders
**Task:** `Upload Renew`
**Script:** `shoutbomb.bat renew`
**Purpose:** Upload renewal reminders to Shoutbomb (same as courtesy, different timing)
**Frequency:** Daily
**Target:** Items due in 3 days (SMS/Voice only)
**Notification Type:** Type 7 - "Almost overdue/Auto-renew reminder"
**Timing Note:** 3 minutes after email/mail batch

### 08:04 AM - Upload Overdue Notifications
**Task:** `Upload Overdue`
**Script:** `shoutbomb.bat overdue`
**Purpose:** Upload overdue item notifications (text/voice) to Shoutbomb
**Frequency:** Daily
**Timing Note:** 4 minutes after email/mail batch to avoid overlap

### 08:05 AM, 09:05 AM, 01:05 PM, 05:05 PM - Upload Hold Notifications
**Task:** `Upload Holds`
**Script:** `shoutbomb.bat holds`
**Purpose:** Upload hold ready notifications (text/voice) to Shoutbomb
**Frequency:** 4 times daily (every 4 hours)
**Description:** "Upload hold notifications to Shoutbomb every 4 hours"
**Triggers:**
- 08:05 - Morning batch (5 minutes after overdue)
- 09:05 - Mid-morning batch
- 13:05 - Early afternoon batch
- 17:05 - Late afternoon batch

## Notification Flow by Delivery Method

### Email (DeliveryOptionID = 2)
1. **08:00 AM** - Polaris sends email notifications
2. Results logged to NotificationHistory and NotificationLogs

### Mail (DeliveryOptionID = 1)
1. **08:00 AM** - Polaris prints mail notices
2. Results logged to NotificationHistory and NotificationLogs

### SMS (DeliveryOptionID = 8)
1. **05:00 AM** - Patron list uploaded to Shoutbomb
2. **08:04 AM** - Overdue notifications uploaded (if applicable)
3. **08:05 AM** (+ 09:05, 13:05, 17:05) - Hold notifications uploaded (if applicable)
4. Shoutbomb processes and sends SMS
5. Results logged to NotificationHistory and NotificationLogs

### Voice (DeliveryOptionID = 3)
1. **04:00 AM** - Patron list uploaded to Shoutbomb
2. **08:04 AM** - Overdue notifications uploaded (if applicable)
3. **08:05 AM** (+ 09:05, 13:05, 17:05) - Hold notifications uploaded (if applicable)
4. Shoutbomb processes and makes voice calls
5. Results logged to NotificationHistory and NotificationLogs

## Time Correlation for Sample Data

When generating sample data, notifications should be timestamped according to:

### Email/Mail Notifications
- `NotificationHistory.NoticeDate`: Around 08:00:00 with small variance (0-59 seconds)
- `NotificationLogs.NotificationDateTime`: Same time as NoticeDate

### SMS/Voice Overdue Notifications
- `NotificationHistory.NoticeDate`: Around 08:04:00 with small variance
- `NotificationLogs.NotificationDateTime`: Same time as NoticeDate

### SMS/Voice Hold Notifications
- `NotificationHistory.NoticeDate`: Around 08:05, 09:05, 13:05, or 17:05 with small variance
- `NotificationLogs.NotificationDateTime`: Same time as NoticeDate
- Multiple holds for same patron should be within 60 seconds

## Variance Guidelines

All notification times should have realistic variance:
- **Small variance**: 0-59 seconds for items in same batch
- **Related items**: Same patron's items within 60 seconds
- **Batch time**: Base time + variance (e.g., 08:00:00 + 0-59 seconds)

## Examples

**Example 1: Patron with 3 holds (Email)**
- NotificationHistory entries: 08:00:23, 08:00:24, 08:00:25
- NotificationLogs entry: 08:00:23, HoldsCount=3

**Example 2: Patron with 2 overdues (SMS)**
- NotificationHistory entries: 08:04:17, 08:04:18
- NotificationLogs entry: 08:04:17, OverduesCount=2

**Example 3: Patron with 1 hold (Voice, afternoon batch)**
- NotificationHistory entry: 17:05:42
- NotificationLogs entry: 17:05:42, HoldsCount=1
