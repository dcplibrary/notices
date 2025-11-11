# Notification Verification System - Architecture Design

## Core Purpose
**Verify that notices were sent and help troubleshoot failures**

Every notice should answer:
- ✅ **WHEN** was it sent?
- ✅ **WHERE** was it sent? (phone number, email address)
- ✅ **FOR WHAT** item/material?
- ✅ **TO WHOM** (patron details)?
- ✅ **HOW** was it delivered? (voice, text, email)
- ✅ **STATUS**: Was it successful?

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    VERIFICATION LAYER                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Search &   │  │ Verification │  │Troubleshoot  │      │
│  │   Timeline   │  │   Engine     │  │   Engine     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            ▲
                            │
┌─────────────────────────────────────────────────────────────┐
│                      DATA LAYER                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │         notification_logs (Master Record)            │   │
│  │  - patron_barcode, phone, email, item_barcode       │   │
│  │  - notification_type, delivery_method, status       │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│  Shoutbomb   │   │    Email     │   │   Future     │
│   Plugin     │   │   Plugin     │   │   Plugins    │
└──────────────┘   └──────────────┘   └──────────────┘
```

## 1. Plugin Architecture

### Core Plugin Interface
Each notification channel is a self-contained plugin:

```php
interface NotificationPlugin {
    // Configuration
    public function getName(): string;
    public function getConfig(): array;

    // Data Import
    public function getImportCommands(): array;
    public function importData(): void;

    // Verification
    public function verifyNotice(NotificationLog $log): VerificationResult;
    public function getSubmissionRecord($noticeId): ?Model;
    public function getDeliveryRecord($noticeId): ?Model;

    // Dashboard
    public function getDashboardWidget(): View;
    public function getStatistics(Carbon $start, Carbon $end): array;

    // API
    public function getApiRoutes(): array;
    public function getApiEndpoints(): array;
}
```

### Plugin: Shoutbomb Voice/Text

**Purpose**: Track notices sent via Shoutbomb (voice and text delivery)

**Data Tables**:
- `shoutbomb_submissions` - What we sent (official)
- `polaris_phone_notices` - Polaris verification
- `shoutbomb_deliveries` - Delivery reports from Shoutbomb

**Commands**:
- `notices:import-shoutbomb-submissions`
- `notices:import-polaris-phone-notices`
- `notices:import-shoutbomb-reports`

**Verification Flow**:
```
1. Check shoutbomb_submissions → Was it submitted?
2. Check polaris_phone_notices → Did Polaris confirm?
3. Check shoutbomb_deliveries → Was it delivered?
4. Return verification status with all details
```

### Plugin: Email

**Purpose**: Track email notifications

**Data Tables**:
- `email_submissions` (if applicable)
- `email_deliveries` - Delivery reports

**Commands**:
- `notices:import-email-reports`

**Verification Flow**:
```
1. Check notification_logs → Was email queued?
2. Check email_deliveries → Was it sent?
3. Check delivery status (bounced, delivered, opened)
4. Return verification status
```

### Future Plugins
- **SMS Direct**: Direct SMS via Twilio/etc (not through Shoutbomb)
- **IVR Plugin**: Interactive voice response
- **Print Plugin**: Physical notices
- **In-App Plugin**: Mobile app notifications

## 2. Dashboard Design

### A. Overview Page (Home)

**Top Metrics** (Last 30 days):
```
┌─────────────────┬─────────────────┬─────────────────┬─────────────────┐
│ Total Sent      │ Successful      │ Failed          │ Pending         │
│ 45,234          │ 44,891 (99.2%)  │ 343 (0.8%)     │ 0               │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┘
```

**By Channel Breakdown** (clickable):
```
Shoutbomb Voice:  23,456 sent → 23,234 delivered (99.1%)  [View Details]
Shoutbomb Text:   18,678 sent → 18,557 delivered (99.4%)  [View Details]
Email:             3,100 sent →  3,100 delivered (100%)   [View Details]
```

**Recent Failures** (clickable to details):
```
┌──────────────┬──────────────┬─────────────┬──────────────────────┬────────┐
│ Time         │ Patron       │ Type        │ Reason               │ Action │
├──────────────┼──────────────┼─────────────┼──────────────────────┼────────┤
│ 2 hours ago  │ P123456      │ Voice       │ Invalid phone number │ [View] │
│ 5 hours ago  │ P789012      │ Text        │ Opted out            │ [View] │
│ 1 day ago    │ P345678      │ Email       │ Bounced              │ [View] │
└──────────────┴──────────────┴─────────────┴──────────────────────┴────────┘
```

**Trend Chart**: Daily sent/success/failed for last 30 days

### B. Verification Page (NEW - Most Important)

**Search Interface**:
```
┌────────────────────────────────────────────────────────────────┐
│  Search for Notice:                                            │
│                                                                │
│  ○ Patron Barcode:  [________________]                        │
│  ○ Phone Number:    [________________]                        │
│  ○ Email:           [________________]                        │
│  ○ Item Barcode:    [________________]                        │
│  ○ Date Range:      [2025-11-01] to [2025-11-10]             │
│                                                                │
│                                         [Search] [Clear]       │
└────────────────────────────────────────────────────────────────┘
```

**Search Results**:
```
Found 3 notices for Patron: 23307013757366

┌─────────────────────────────────────────────────────────────────────────────┐
│ Notice #1: Hold Ready                                 ✅ Verified & Delivered│
├─────────────────────────────────────────────────────────────────────────────┤
│ When:      2025-11-09 14:23:15                                              │
│ To Whom:   John Doe (Barcode: 23307013757366)                              │
│ Where:     555-123-4567 (Voice)                                            │
│ For What:  "The Bad Guys in Cut to the chase #13"                          │
│            Item: 810045                                                     │
│ How:       Shoutbomb Voice                                                  │
│                                                                             │
│ Verification Timeline:                                                      │
│   ✅ 2025-11-09 14:23:15 - Notice created in system                        │
│   ✅ 2025-11-09 14:25:00 - Submitted to Shoutbomb (holds_submitted.txt)   │
│   ✅ 2025-11-09 14:25:00 - Verified in PhoneNotices.csv                    │
│   ✅ 2025-11-09 14:27:43 - Delivered successfully (Shoutbomb report)       │
│                                                                             │
│                               [View Full Details] [View Patron History]     │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│ Notice #2: Overdue Item                                    ❌ Failed         │
├─────────────────────────────────────────────────────────────────────────────┤
│ When:      2025-11-08 09:15:22                                              │
│ To Whom:   John Doe (Barcode: 23307013757366)                              │
│ Where:     555-123-4567 (Text)                                             │
│ For What:  "Mystery Book"                                                   │
│            Item: 123456                                                     │
│ How:       Shoutbomb Text                                                   │
│                                                                             │
│ Verification Timeline:                                                      │
│   ✅ 2025-11-08 09:15:22 - Notice created in system                        │
│   ✅ 2025-11-08 09:20:00 - Submitted to Shoutbomb (overdue_submitted.txt) │
│   ❌ 2025-11-08 09:22:15 - Failed: Patron opted out                        │
│                                                                             │
│ Troubleshooting:                                                            │
│   • Patron has opted out of text notifications                             │
│   • Consider switching to voice or email                                   │
│   • Last successful text: 2025-10-15                                       │
│                                                                             │
│                               [View Full Details] [View Patron History]     │
└─────────────────────────────────────────────────────────────────────────────┘
```

### C. Troubleshooting Page (NEW)

**Failure Analysis Dashboard**:
```
Failed Notices: Last 7 Days

By Reason:
┌────────────────────────────┬───────┬──────────┬──────────┐
│ Reason                     │ Count │ % Failed │ Action   │
├────────────────────────────┼───────┼──────────┼──────────┤
│ Invalid Phone Number       │   45  │  45%     │ [Fix]    │
│ Opted Out                  │   32  │  32%     │ [Review] │
│ Phone Disconnected         │   18  │  18%     │ [Update] │
│ Network Error              │    5  │   5%     │ [Retry]  │
└────────────────────────────┴───────┴──────────┴──────────┘

By Notification Type:
┌────────────────────────────┬───────┬──────────┐
│ Type                       │ Count │ % Failed │
├────────────────────────────┼───────┼──────────┤
│ Overdue Notices            │   52  │  52%     │
│ Hold Ready                 │   30  │  30%     │
│ Renewal Reminder           │   18  │  18%     │
└────────────────────────────┴───────┴──────────┘

Recent Failures (Clickable):
[Same table format as Overview page, but filterable and sortable]
```

**Mismatch Detection**:
```
Verification Mismatches

⚠️ Submitted but Not Verified (PhoneNotices missing):
┌──────────────┬──────────────┬─────────────┬────────────────┐
│ Submitted    │ Patron       │ Type        │ Action         │
├──────────────┼──────────────┼─────────────┼────────────────┤
│ 2 hours ago  │ P123456      │ Hold        │ [Investigate]  │
└──────────────┴──────────────┴─────────────┴────────────────┘

⚠️ Verified but Not Delivered (Delivery report missing):
┌──────────────┬──────────────┬─────────────┬────────────────┐
│ Verified     │ Patron       │ Type        │ Action         │
├──────────────┼──────────────┼─────────────┼────────────────┤
│ 5 hours ago  │ P789012      │ Overdue     │ [Investigate]  │
└──────────────┴──────────────┴─────────────┴────────────────┘
```

### D. Channel-Specific Pages

**Shoutbomb Page**:
- Submission statistics (from SQL files)
- Verification statistics (from PhoneNotices.csv)
- Delivery statistics (from Shoutbomb reports)
- Voice vs Text breakdown
- Recent submissions with status
- **Verification Comparison**: Show if submissions match phone notices match deliveries

**Email Page**:
- Email statistics
- Delivery rates
- Bounce analysis
- Recent emails with status

## 3. API Design

### Core Endpoints

#### Verification API
```
GET /api/notices/verify
Query params: patron_barcode, phone, email, item_barcode, date_range

Response:
{
  "notices": [
    {
      "id": 12345,
      "date": "2025-11-09 14:23:15",
      "patron": {
        "barcode": "23307013757366",
        "name": "John Doe"
      },
      "contact": {
        "method": "voice",
        "value": "555-123-4567"
      },
      "item": {
        "barcode": "810045",
        "title": "The Bad Guys in Cut to the chase #13"
      },
      "verification": {
        "created": true,
        "submitted": true,
        "verified": true,
        "delivered": true,
        "status": "success"
      },
      "timeline": [
        {
          "step": "created",
          "timestamp": "2025-11-09 14:23:15",
          "source": "notification_logs"
        },
        {
          "step": "submitted",
          "timestamp": "2025-11-09 14:25:00",
          "source": "shoutbomb_submissions",
          "file": "holds_submitted_2025-11-09_14-25-00.txt"
        },
        {
          "step": "verified",
          "timestamp": "2025-11-09 14:25:00",
          "source": "polaris_phone_notices",
          "file": "PhoneNotices.csv"
        },
        {
          "step": "delivered",
          "timestamp": "2025-11-09 14:27:43",
          "source": "shoutbomb_deliveries",
          "status": "Delivered"
        }
      ]
    }
  ],
  "summary": {
    "total": 3,
    "verified": 2,
    "failed": 1
  }
}
```

#### Search API
```
GET /api/notices/search
Query params: q, type, status, date_from, date_to, limit, offset

Response:
{
  "notices": [...],
  "pagination": {
    "total": 1000,
    "page": 1,
    "per_page": 50
  }
}
```

#### Patron History API
```
GET /api/notices/patron/{barcode}
Query params: date_from, date_to, type

Response:
{
  "patron": {
    "barcode": "23307013757366",
    "total_notices": 45,
    "success_rate": 97.8,
    "last_notice": "2025-11-09 14:23:15"
  },
  "notices": [...],
  "statistics": {
    "by_type": {
      "holds": 20,
      "overdues": 15,
      "renewals": 10
    },
    "by_method": {
      "voice": 30,
      "text": 10,
      "email": 5
    }
  }
}
```

#### Failures API
```
GET /api/notices/failures
Query params: date_from, date_to, reason, type

Response:
{
  "failures": [
    {
      "id": 67890,
      "date": "2025-11-08 09:15:22",
      "patron_barcode": "23307013757366",
      "reason": "Opted Out",
      "type": "overdue",
      "method": "text"
    }
  ],
  "summary": {
    "total_failed": 100,
    "by_reason": {
      "Invalid Phone": 45,
      "Opted Out": 32,
      "Disconnected": 18,
      "Network Error": 5
    }
  }
}
```

#### Timeline API
```
GET /api/notices/{id}/timeline

Response:
{
  "notice_id": 12345,
  "timeline": [
    {
      "step": "created",
      "timestamp": "2025-11-09 14:23:15",
      "source": "notification_logs",
      "details": {...}
    },
    ...
  ]
}
```

## 4. Database Relationships

### Core Linking Strategy

All tables should link back to `notification_logs`:

```sql
-- notification_logs (master)
id, patron_barcode, phone, email, item_barcode, notification_type_id,
delivery_option_id, notification_status_id, notification_date

-- shoutbomb_submissions (link by patron + date + type)
id, patron_barcode, phone_number, notification_type, submitted_at,
source_file, delivery_type

-- polaris_phone_notices (link by patron + date + item)
id, patron_barcode, phone_number, item_barcode, notice_date,
delivery_type, source_file

-- shoutbomb_deliveries (link by phone + date)
id, phone_number, delivery_date, status, message_type

-- email_deliveries (link by email + date)
id, email_address, sent_date, status, bounce_reason
```

### Verification Service

Create a `NotificationVerificationService`:

```php
class NotificationVerificationService
{
    public function verify(NotificationLog $log): VerificationResult
    {
        $result = new VerificationResult();

        // Step 1: Check if created
        $result->created = true;
        $result->created_at = $log->notification_date;

        // Step 2: Check submission (based on channel)
        $submission = $this->findSubmission($log);
        $result->submitted = $submission !== null;
        $result->submitted_at = $submission?->submitted_at;

        // Step 3: Check verification (PhoneNotices, email logs, etc.)
        $verification = $this->findVerification($log);
        $result->verified = $verification !== null;
        $result->verified_at = $verification?->notice_date;

        // Step 4: Check delivery
        $delivery = $this->findDelivery($log);
        $result->delivered = $delivery !== null;
        $result->delivered_at = $delivery?->delivery_date;
        $result->delivery_status = $delivery?->status;

        return $result;
    }

    protected function findSubmission(NotificationLog $log)
    {
        // Match by patron + type + date
        return ShoutbombSubmission::where('patron_barcode', $log->patron_barcode)
            ->where('notification_type', $this->mapType($log->notification_type_id))
            ->whereDate('submitted_at', $log->notification_date->format('Y-m-d'))
            ->first();
    }

    // Similar methods for verification and delivery...
}
```

## 5. Implementation Plan

### Phase 1: Verification Core ✅ COMPLETED (2025-11-10)
- [x] Create `NotificationVerificationService`
- [x] Create `VerificationResult` value object
- [x] Create Verification API endpoints
- [x] Create basic Verification page
- [x] Add PHPUnit tests

### Phase 2: Timeline & Details ✅ COMPLETED (2025-11-10)
- [x] Create Timeline view component
- [x] Add detail pages for each notice
- [x] Add patron history view
- [x] Enhance API with timeline endpoints
- [x] Add navigation menu items

### Phase 3: Troubleshooting ✅ COMPLETED (2025-11-10)
- [x] Create Troubleshooting dashboard
- [x] Add mismatch detection
- [x] Add failure analysis
- [x] Create troubleshooting API
- [x] Add comprehensive tests

### Phase 4: Plugin Architecture ✅ COMPLETED (2025-11-10)
- [x] Create Plugin interface
- [x] Create PluginRegistry service
- [x] Refactor Shoutbomb into plugin
- [x] Add plugin tests (28 tests)
- [x] Document plugin creation guide

### Phase 5: Enhanced UI ✅ COMPLETED (2025-11-10)
- [x] Create CSV export service
- [x] Add export endpoints
- [x] Add export buttons to all views
- [x] Add comprehensive tests (26 tests)
- [x] Support filtering and date ranges

### Future Enhancements
- [ ] Create Email plugin
- [ ] Create SMS Direct plugin
- [ ] Add advanced search and sorting
- [ ] Add bulk retry operations
- [ ] Add automated failure alerts

## 6. Plugin Development Guide

### Creating a New Plugin

Example: SMS Direct Plugin

```php
namespace Dcplibrary\Notices\Plugins;

class SmsDirectPlugin implements NotificationPlugin
{
    public function getName(): string
    {
        return 'SMS Direct (Twilio)';
    }

    public function getImportCommands(): array
    {
        return [
            Commands\ImportTwilioReports::class,
        ];
    }

    public function verifyNotice(NotificationLog $log): VerificationResult
    {
        // Check twilio_deliveries table
        $delivery = TwilioDelivery::where('phone', $log->phone)
            ->whereDate('sent_at', $log->notification_date)
            ->first();

        return new VerificationResult([
            'delivered' => $delivery !== null,
            'status' => $delivery?->status,
        ]);
    }

    public function getDashboardWidget(): View
    {
        return view('notifications::plugins.sms-direct.widget');
    }

    // ... other methods
}
```

### Register Plugin

```php
// In NotificationsServiceProvider
protected function registerPlugins()
{
    $this->app->singleton('notification.plugins', function () {
        return new PluginRegistry([
            new Plugins\ShoutbombPlugin(),
            new Plugins\EmailPlugin(),
            new Plugins\SmsDirectPlugin(),
        ]);
    });
}
```

## Summary

This design provides:

✅ **Complete Verification** - Track every notice through its entire lifecycle
✅ **Easy Troubleshooting** - Quickly identify and diagnose failures
✅ **Detailed Information** - Full patron and item details at every level
✅ **Modular Architecture** - Easy to add new notification channels
✅ **Comprehensive API** - All data accessible programmatically
✅ **User-Friendly Dashboard** - Stats that drill down to details

The system is built around the core question: **"Did this notice get delivered?"** and makes it trivially easy to answer that question.
