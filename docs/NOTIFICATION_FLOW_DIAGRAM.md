# Polaris Notification System - Complete Flow Diagram

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                    POLARIS NOTIFICATION SYSTEM                         ┃
┃                         Complete Data Flow                              ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛


═══════════════════════════════════════════════════════════════════════════
                         1. TRIGGER EVENTS
═══════════════════════════════════════════════════════════════════════════

    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
    │   ITEM      │    │    ITEM     │    │  BALANCE    │    │   STAFF     │
    │  CHECKED    │    │  BECOMES    │    │  EXCEEDS    │    │   ACTION    │
    │    IN       │    │  OVERDUE    │    │  THRESHOLD  │    │  (MANUAL)   │
    └──────┬──────┘    └──────┬──────┘    └──────┬──────┘    └──────┬──────┘
           │                  │                   │                   │
           ▼                  ▼                   ▼                   ▼
    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
    │    HOLD     │    │   OVERDUE   │    │    FINE     │    │   MANUAL    │
    │   READY     │    │   NOTICE    │    │   NOTICE    │    │    BILL     │
    └──────┬──────┘    └──────┬──────┘    └──────┬──────┘    └──────┬──────┘
           │                  │                   │                   │
           └──────────────────┴───────────────────┴───────────────────┘
                                      │
                                      ▼


═══════════════════════════════════════════════════════════════════════════
              2. SOURCE DATA TABLES (Polaris.Polaris Database)
═══════════════════════════════════════════════════════════════════════════

           ┌──────────────────────────────────────────────────┐
           │                                                  │
           ▼                                                  ▼
    ┌──────────────────────┐                     ┌──────────────────────┐
    │  SysHoldRequests     │                     │  ItemCheckouts       │
    │  ─────────────────   │                     │  ─────────────────   │
    │  • PatronID          │                     │  • PatronID          │
    │  • ItemRecordID      │                     │  • ItemRecordID      │
    │  • SysHoldStatusID=6 │                     │  • DueDate (past)    │
    │  • HoldNotifDate     │                     │  • ItemStatusID=2    │
    │  • HoldTillDate      │                     │  ─────────────────   │
    │  • DeliveryOptionID  │                     │  TRIGGERS OVERDUE    │
    │  ─────────────────   │                     └──────────┬───────────┘
    │  TRIGGERS HOLD       │                                │
    └─────────┬────────────┘                                │
              │                                             │
              │                                             │
              ▼                                             ▼
    ┌──────────────────────┐                     ┌──────────────────────┐
    │  ViewHoldNoticesData │                     │ ViewOverdueNoticesData│
    │  ─────────────────   │                     │  ─────────────────   │
    │  Joins item & patron │                     │  Joins item & patron │
    │  details for notice  │                     │  details for notice  │
    └─────────┬────────────┘                     └──────────┬───────────┘
              │                                             │
              └──────────────────┬──────────────────────────┘
                                 │
                                 ▼
    ┌────────────────────────────────────────────────────────────────┐
    │                  PatronRegistration                            │
    │  ─────────────────────────────────────────────────────────────│
    │  • EmailAddress            (for email delivery)                │
    │  • PhoneVoice1             (for SMS/voice delivery)            │
    │  • DeliveryOptionID        (preferred method)                  │
    │  • ExcludeFromOverdues     (opt-out flag)                      │
    │  • ExcludeFromHolds        (opt-out flag)                      │
    │  • AdminLanguageID         (language preference)               │
    └──────────────────────────────┬─────────────────────────────────┘
                                   │
                                   ▼


═══════════════════════════════════════════════════════════════════════════
            3. NOTIFICATION QUEUE (Results.Polaris Database)
═══════════════════════════════════════════════════════════════════════════

                        ┌─────────────────────┐
                        │ NotificationQueue   │
                        │ ──────────────────  │
                        │ • PatronID          │
                        │ • ItemRecordID      │
                        │ • NotificationTypeID│
                        │ • DeliveryOptionID  │
                        │ • Processed=0       │
                        │ • CreationDate      │
                        └──────────┬──────────┘
                                   │
                   ┌───────────────┼───────────────┐
                   ▼               ▼               ▼
        ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
        │ HoldNotices  │  │   Overdue    │  │ FineNotices  │
        │              │  │   Notices    │  │              │
        │ Type ID = 2  │  │  Type ID = 1 │  │ Type ID = 8  │
        │ (Hold Ready) │  │  (1st Ovd)   │  │ (Fines)      │
        └──────┬───────┘  └──────┬───────┘  └──────┬───────┘
               │                 │                 │
               └─────────────────┴─────────────────┘
                                 │
                                 ▼


═══════════════════════════════════════════════════════════════════════════
                   4. DELIVERY METHOD ROUTING
═══════════════════════════════════════════════════════════════════════════

                    Based on DeliveryOptionID:

         ┌──────────┬──────────┬──────────┬──────────┐
         │    1     │    2     │    3     │    8     │
         │   MAIL   │  EMAIL   │  VOICE   │   SMS    │
         └────┬─────┴────┬─────┴────┬─────┴────┬─────┘
              │          │          │          │
              ▼          ▼          ▼          ▼
       ┌──────────┐ ┌──────────┐ ┌──────────────────────────┐
       │ Polaris  │ │ Polaris  │ │   Shoutbomb Integration  │
       │  Print   │ │  SMTP    │ │   (Custom)               │
       │  Queue   │ │  Server  │ └──────────────────────────┘
       └────┬─────┘ └────┬─────┘              │
            │            │                    │
            │            │      ┌─────────────┴─────────────┐
            │            │      ▼                           ▼
            │            │  ┌──────────┐              ┌──────────┐
            │            │  │  VOICE   │              │   SMS    │
            │            │  │  CALL    │              │   TEXT   │
            │            │  └────┬─────┘              └────┬─────┘
            │            │       │                         │
            └────────────┴───────┴─────────────────────────┘
                                 │
                                 ▼


═══════════════════════════════════════════════════════════════════════════
               5. SHOUTBOMB INTEGRATION (Voice/SMS Only)
═══════════════════════════════════════════════════════════════════════════

                    ┌─────────────────────────┐
                    │  Custom SQL Queries     │
                    │  ─────────────────────  │
                    │  • holds.sql            │
                    │  • overdue.sql          │
                    │  • renew.sql            │
                    │  • text_patrons.sql     │
                    │  • voice_patrons.sql    │
                    └──────────┬──────────────┘
                               │
                               ▼
                    ┌─────────────────────────┐
                    │  Pipe-Delimited Files   │
                    │  ─────────────────────  │
                    │  holds_submitted.txt    │
                    │  overdue_submitted.txt  │
                    │  renew_submitted.txt    │
                    │  text_patrons.txt       │
                    │  voice_patrons.txt      │
                    └──────────┬──────────────┘
                               │
                               ▼
                    ┌─────────────────────────┐
                    │  WinSCP FTP Upload      │
                    │  ─────────────────────  │
                    │  shoutbomb.bat script   │
                    │  → Shoutbomb server     │
                    └──────────┬──────────────┘
                               │
                               ▼
                    ┌─────────────────────────┐
                    │   Shoutbomb Processing  │
                    │  ─────────────────────  │
                    │  • Makes phone calls    │
                    │  • Sends SMS messages   │
                    │  • Tracks delivery      │
                    └──────────┬──────────────┘
                               │
                               ▼
                    ┌─────────────────────────┐
                    │   Delivery Reports      │
                    │  ─────────────────────  │
                    │  • Success/failure      │
                    │  • Invalid phones       │
                    │  • Emailed to staff     │
                    └───────────────────────────┘


═══════════════════════════════════════════════════════════════════════════
                    6. NOTIFICATION LOGGING & TRACKING
═══════════════════════════════════════════════════════════════════════════

                   All delivery methods converge:

                               │
                               ▼
              ┌────────────────────────────────────┐
              │  Update NotificationQueue          │
              │  ────────────────────────────────  │
              │  SET Processed = 1                 │
              └────────────────┬───────────────────┘
                               │
                   ┌───────────┴───────────┐
                   ▼                       ▼
    ┌──────────────────────────┐  ┌──────────────────────────┐
    │ Results.NotificationHistory│ │PolarisTransactions.      │
    │ ──────────────────────── │  │  NotificationLog         │
    │ • PatronId               │  │ ──────────────────────── │
    │ • ItemRecordId           │  │ • PatronID               │
    │ • NotificationTypeId     │  │ • NotificationDateTime   │
    │ • NoticeDate             │  │ • DeliveryString         │
    │ • DeliveryOptionId       │  │   (actual email/phone)   │
    │ • NotificationStatusId   │  │ • HoldsCount             │
    │ • Amount                 │  │ • OverduesCount          │
    │ • Title                  │  │ • NotificationStatusId   │
    │ ─────────────────────    │  │ • Details                │
    │ ITEM-LEVEL TRACKING      │  │ ─────────────────────    │
    │ (one row per item)       │  │ PATRON-LEVEL TRACKING    │
    └──────────────────────────┘  │ (one row per batch)      │
                                  └──────────────────────────┘
                   │                       │
                   └───────────┬───────────┘
                               │
                               ▼


═══════════════════════════════════════════════════════════════════════════
                     7. NOTIFICATION STATUS CODES
═══════════════════════════════════════════════════════════════════════════

    ┌─────────────────────────────────────────────────────────────────┐
    │                      SUCCESS STATUSES                           │
    ├─────────────────────────────────────────────────────────────────┤
    │  1  │ Call completed - Voice (person answered)                  │
    │  2  │ Call completed - Answering machine                        │
    │ 12  │ Email Completed                                           │
    │ 15  │ Mail Printed                                              │
    │ 16  │ Sent (generic success - used for SMS)                     │
    └─────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────────────────────────────────────────────┐
    │                      FAILURE STATUSES                           │
    ├─────────────────────────────────────────────────────────────────┤
    │  3  │ Call not completed - Hang up                              │
    │  4  │ Call not completed - Busy                                 │
    │  5  │ Call not completed - No answer                            │
    │ 13  │ Email Failed - Invalid address                            │
    │ 14  │ Email Failed (general)                                    │
    └─────────────────────────────────────────────────────────────────┘


═══════════════════════════════════════════════════════════════════════════
                     8. TIME CORRELATION PATTERNS
═══════════════════════════════════════════════════════════════════════════

    HOLD READY NOTIFICATION:
    ─────────────────────────
    Item checked in: 2025-11-06 10:30:00
         ↓
    Hold status → "Held" (on shelf)
         ↓
    SysHoldRequests.HoldNotificationDate: 2025-11-06 10:30:00
         ↓ (<60 min)
    NotificationHistory.NoticeDate: 2025-11-06 10:31:15
         ↓ (same time)
    NotificationLog.NotificationDateTime: 2025-11-06 10:31:15


    OVERDUE NOTIFICATION:
    ─────────────────────
    Item DueDate: 2025-10-15 23:59:59
         ↓
    Overnight processing (2:00 AM)
         ↓
    NotificationQueue created: 2025-10-16 02:00:00
         ↓
    Morning batch (10:00 AM)
         ↓
    NotificationHistory.NoticeDate: 2025-10-16 10:01:23
         ↓ (same time)
    NotificationLog.NotificationDateTime: 2025-10-16 10:01:23


    BATCH PROCESSING TIMES:
    ───────────────────────
    Hourly batches: 09:01, 10:01, 11:01, 12:01, 13:01, 14:01, 15:01, 16:01, 17:01

    Within batch variance:
    • Same patron, multiple items: ±0.1-2 seconds
    • Different patrons: ±10-59 seconds


═══════════════════════════════════════════════════════════════════════════
                 9. MULTI-ITEM NOTIFICATION PATTERNS
═══════════════════════════════════════════════════════════════════════════

    PATRON WITH 3 HOLDS READY (Same Day):
    ─────────────────────────────────────

    NotificationHistory (3 separate records):
    ┌──────────────────────────────────────────────────────────┐
    │ PatronId=121394, ItemId=193755, NoticeDate=14:01:17.0   │
    │ PatronId=121394, ItemId=793939, NoticeDate=14:01:17.0   │
    │ PatronId=121394, ItemId=148279, NoticeDate=14:01:17.0   │
    └──────────────────────────────────────────────────────────┘
              │
              ▼
    NotificationLog (1 combined record):
    ┌──────────────────────────────────────────────────────────┐
    │ PatronID=121394, NotificationDateTime=14:01:17.0         │
    │ HoldsCount=3, DeliveryString=patron@email.com            │
    └──────────────────────────────────────────────────────────┘


═══════════════════════════════════════════════════════════════════════════
                   10. DATA VERIFICATION PATH
═══════════════════════════════════════════════════════════════════════════

    To verify a notification was sent correctly:

    1. CHECK SOURCE
       └─→ SysHoldRequests (for holds) or ItemCheckouts (for overdues)

    2. CHECK QUEUE
       └─→ Results.Polaris.HoldNotices or OverdueNotices

    3. CHECK HISTORY
       └─→ Results.Polaris.NotificationHistory
           • Verify NoticeDate within 60 min of source
           • Verify NotificationStatusId = success code

    4. CHECK LOGS
       └─→ PolarisTransactions.NotificationLog
           • Verify DeliveryString matches patron's email/phone
           • Verify counts (HoldsCount, OverduesCount)

    5. CHECK EXTERNAL (for Voice/SMS)
       └─→ Shoutbomb delivery reports
           • Emailed daily with success/failure details


═══════════════════════════════════════════════════════════════════════════
                     11. COMPLETE FLOW SUMMARY
═══════════════════════════════════════════════════════════════════════════

    TRIGGER → SOURCE TABLES → VIEWS → QUEUE → ROUTING → DELIVERY → LOGGING

    Example: Hold Ready Notification
    ─────────────────────────────────
    Item checked in
      → SysHoldRequests (status=Held)
        → ViewHoldNoticesData (join item/patron)
          → NotificationQueue (Processed=0)
            → HoldNotices (staging)
              → Email/Voice/SMS routing
                → Delivery (SMTP or Shoutbomb)
                  → NotificationHistory (item-level)
                    → NotificationLog (patron-level)
                      → Success! ✓

    Status updates at each step ensure auditability and troubleshooting.


═══════════════════════════════════════════════════════════════════════════
                            END OF DIAGRAM
═══════════════════════════════════════════════════════════════════════════
```

## Key Takeaways

1. **Three Database System**: Polaris.Polaris (core), Results.Polaris (queue), PolarisTransactions.Polaris (logs)
2. **Custom Shoutbomb Integration**: Voice and SMS use pipe-delimited files, not standard Polaris export
3. **Time Correlations**: Related notifications within <60 minutes, batched hourly
4. **Dual Logging**: Item-level (NotificationHistory) and patron-level (NotificationLog)
5. **Verification Path**: Can trace notification through 5 checkpoints
6. **Multi-Item Handling**: Multiple items can be combined in single notification or sent separately

## Reference
- For detailed table schemas: See `claude-generated-documentation/Polaris_Complete_Notification_System_Guide.md`
- For Shoutbomb integration: See `claude-generated-documentation/DCPL_Shoutbomb_Integration_Documentation.md`
- For data generation: See `DATA_GENERATION_PLAN.md`
