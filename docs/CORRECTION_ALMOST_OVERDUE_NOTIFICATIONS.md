# CORRECTION: Almost Overdue Notifications

## Error Identified

**Previous (incorrect) understanding:** Almost overdue (Type 7) notifications are ONLY sent to SMS/Voice patrons (DeliveryOptionID 3 and 8).

**Correct understanding:** Almost overdue notifications are sent to ALL delivery methods:
- Email (DeliveryOptionID=2) - sent at 8:00 AM via Polaris
- SMS (DeliveryOptionID=8) - uploaded at 07:30 or 08:03 via Shoutbomb
- Voice (DeliveryOptionID=3) - uploaded at 07:30 or 08:03 via Shoutbomb
- Mail (DeliveryOptionID=1) - printed at 8:00 AM (needs verification)

## Evidence

### September 2025 Report
The September email summary report showed:
```
No Almost overdue/Auto-renew reminder notices sent.
```

This led to the incorrect conclusion that these notifications don't go out via email. However, this simply meant that on that particular day, no almost overdue notifications were triggered (no items were due in exactly 3 days on that date).

### November 2025 Report
The November email summary report clearly shows:
```
79 Almost overdue/Auto-renew reminder E-mail notices sent out
2 Almost overdue/Auto-renew reminder E-mail notices failed
72 Almost overdue/Auto-renew reminder TXT message notices sent out
2 Almost overdue/Auto-renew reminder TXT message notices failed
```

This confirms that almost overdue notifications ARE sent via both email and SMS/Voice channels.

## Required Changes

1. **Script Logic:** Update `generate_notification_history()` to generate almost overdue notifications for all delivery methods, not just SMS/Voice:
   - Email: 8:00 AM batch (same as holds/overdues)
   - SMS/Voice: 07:30 or 08:03 (Shoutbomb upload times)

2. **NotificationQueue:** Should still only contain SMS/Voice almost overdues (Shoutbomb integration)

3. **Documentation:** Update AUTO_RENEWAL_AND_COURTESY_SYSTEM.md to clarify all delivery methods receive these notifications

## Timing Summary

**Email/Mail Notifications (Polaris Native - 8:00 AM):**
- Hold notifications (Type 2)
- Overdue notifications (Types 1, 12, 13)
- Almost overdue notifications (Type 7) ← **ADDED**
- Fine notifications (Type 8)
- Other notification types

**SMS/Voice Notifications (Shoutbomb - Various Times):**
- Holds: 08:05, 09:05, 13:05, 17:05 (4x daily)
- Overdues: 08:04 AM
- Almost overdues: 07:30 (Courtesy) or 08:03 (Renew) ← **ALREADY CORRECT**
