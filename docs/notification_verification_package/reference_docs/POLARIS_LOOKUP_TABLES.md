# POLARIS LOOKUP TABLES - COMPLETE REFERENCE

---

## OVERVIEW

This document provides complete reference tables for all Polaris notification system lookup values. These are used throughout NotificationQueue, NotificationLog, NotificationHistory, and related tables.

**Source:** Actual Polaris ILS database exports
- Polaris_NotificationStatuses.csv
- Polaris_NotificationTypes.csv  
- Polaris_DeliveryOptions.csv
- Polaris_Languages.csv

**Last Verified:** November 13, 2024

---

## TABLE 1: NOTIFICATION TYPES

### NotificationTypeID Reference

| ID | Type | Description | Used by Shoutbomb? |
|----|------|-------------|-------------------|
| 0 | Combined | Combined notification types | No |
| 1 | 1st Overdue | First overdue notice | Yes - overdue.txt |
| 2 | Hold | Hold ready for pickup | Yes - holds.txt |
| 3 | Cancel | Cancellation notice | No |
| 4 | Recall | Item recall notice | No |
| 5 | All | All notification types | No |
| 6 | Route | Routing notice | No |
| 7 | Almost overdue/Auto-renew reminder | Courtesy/pre-overdue notice | Yes - overdue.txt |
| 8 | Fine | Fine/fee notice | Yes - overdue.txt |
| 9 | Inactive Reminder | Inactive account reminder | No |
| 10 | Expiration Reminder | Card expiration reminder | No |
| 11 | Bill | Billing notice | Yes - overdue.txt |
| 12 | 2nd Overdue | Second overdue notice | Yes - overdue.txt |
| 13 | 3rd Overdue | Third overdue notice | Yes - overdue.txt |
| 14 | Serial Claim | Serial publication claim | No |
| 15 | Polaris Fusion Access Request | Fusion access request response | No |
| 16 | Course Reserves | Course reserves notice | No |
| 17 | Borrow-By-Mail Failure | Borrow-by-mail failure notice | No |
| 18 | 2nd Hold | Second hold notice | No |
| 19 | Missing Part | Missing part notice | No |
| 20 | Manual Bill | Manual billing notice | No |
| 21 | 2nd Fine Notice | Second fine notice | No |

### Most Common Types at DCPL

**Shoutbomb Exports:**
- **Type 2** (Hold) - Item ready for pickup → holds.txt export
- **Type 1** (1st Overdue) - Item overdue → overdue.txt export
- **Type 7** (Almost overdue) - Courtesy reminder → overdue.txt export
- **Type 8** (Fine) - Outstanding fines → overdue.txt export
- **Type 11** (Bill) - Outstanding bills → overdue.txt export
- **Type 12** (2nd Overdue) - Second overdue notice → overdue.txt export
- **Type 13** (3rd Overdue) - Third overdue notice → overdue.txt export

**Email/Mail (Polaris Direct):**
- **Type 3** (Cancel) - Hold cancellation
- **Type 10** (Expiration Reminder) - Card expiring soon

---

## TABLE 2: DELIVERY OPTIONS

### DeliveryOptionID Reference

| ID | Delivery Option | Description | Shoutbomb Export | Channel |
|----|-----------------|-------------|------------------|---------|
| 1 | Mailing Address | Physical mail notification (postcard) | No | Mail |
| 2 | Email Address | Email notification | No | Email |
| 3 | Phone 1 | Primary phone number (voice call) | Yes - voice_patrons.txt | Voice |
| 4 | Phone 2 | Secondary phone number | No | Voice |
| 5 | Phone 3 | Tertiary phone number | No | Voice |
| 6 | FAX | Fax notification | No | Fax |
| 7 | EDI | Electronic Data Interchange | No | EDI |
| 8 | TXT Messaging | SMS/Text message notification | Yes - text_patrons.txt | SMS |

### Shoutbomb Filter

**All Shoutbomb exports use this filter:**
```sql
WHERE (DeliveryOptionID = 3 OR DeliveryOptionID = 8)
```

**DeliveryOptionID = 3** → Voice notifications (voice_patrons.txt, holds.txt, overdue.txt)  
**DeliveryOptionID = 8** → Text notifications (text_patrons.txt, holds.txt, overdue.txt)  
**DeliveryOptionID = 1, 2** → Handled by Polaris directly (not Shoutbomb)

---

## TABLE 3: NOTIFICATION STATUS

### NotificationStatusID Reference

| ID | Status | Description | Channel | Use When |
|----|--------|-------------|---------|----------|
| 1 | Call completed - Voice | Successfully spoke with person | Voice | Person answered phone call |
| 2 | Call completed - Answering machine | Left message on voicemail | Voice | Voicemail answered |
| 3 | Delivered (SMS) | Text message delivered | SMS | Successful SMS delivery |
| 4 | Failed - Invalid Phone | Phone number invalid/disconnected | Voice/SMS | Bad phone number |
| 5 | Failed - No Answer | Call rang but no answer (after 30 sec) | Voice | No one picked up |
| 6 | Failed - Invalid Email | Email address invalid | Email | Bad email address |
| 7 | Delivered (Email) | Email successfully sent | Email | Successful email delivery |
| 8 | Failed - Disconnected | Intercept tone detected | Voice | Disconnected number |
| 9 | Delivered (Voice) | Voice call completed | Voice | Successful voice delivery |
| 10 | Failed - Opted Out | Patron opted out of SMS | SMS | Patron replied STOP |
| 11 | Call failed - Undetermined error | Unknown error occurred | Voice | Unknown failure |
| 12 | Email Completed | Email successfully sent (alternate) | Email | Successful email |
| 13 | Email Failed - Invalid address | Email address is invalid | Email | Bad email |
| 14 | Email Failed | Email failed to send (generic) | Email | Email error |
| 15 | Mail Printed | Physical mail notice printed | Mail | Postcard printed |
| 16 | Sent | Generic sent status (all channels) | All | Default success status |

### Status Categories

**Successful Delivery:**
- 1 (Voice - person answered)
- 2 (Voice - voicemail)
- 3 (SMS delivered)
- 7 (Email delivered)
- 9 (Voice delivered)
- 12 (Email completed)
- 15 (Mail printed)
- 16 (Generic sent)

**Failed Delivery:**
- 4 (Invalid phone)
- 5 (No answer)
- 6 (Invalid email)
- 8 (Disconnected)
- 10 (Opted out)
- 11 (Unknown error)
- 13 (Invalid email address)
- 14 (Email failed)

### Recommended Usage

**For Shoutbomb Integration:**
- **SMS Delivery:** Use Status 3 (Delivered SMS) or 16 (Sent)
- **Voice Call - Answered:** Use Status 1 (Call completed - Voice)
- **Voice Call - Voicemail:** Use Status 2 (Call completed - Answering machine)
- **SMS Failed - Invalid:** Use Status 4 (Failed - Invalid Phone)
- **SMS Failed - Opted Out:** Use Status 10 (Failed - Opted Out)
- **Voice Failed - Disconnected:** Use Status 8 (Failed - Disconnected)
- **Voice Failed - No Answer:** Use Status 5 (Failed - No Answer)
- **Generic Success:** Use Status 16 (Sent) when specific status unknown

---

## TABLE 4: LANGUAGES

### LanguageID Reference (Top 25 Most Common)

| ID | Code | Language Name |
|----|------|---------------|
| 1033 | eng | English |
| 1034 | spa | Spanish |
| 1036 | fre | French |
| 1031 | ger | German |
| 1040 | ita | Italian |
| 1045 | pol | Polish |
| 1046 | por | Portuguese (Brazil) |
| 1049 | rus | Russian |
| 1052 | sqi | Albanian |
| 1053 | swe | Swedish |
| 1054 | tha | Thai |
| 1055 | tur | Turkish |
| 1057 | ind | Indonesian |
| 1058 | ukr | Ukrainian |
| 1060 | slv | Slovenian |
| 1061 | est | Estonian |
| 1062 | lav | Latvian |
| 1063 | lit | Lithuanian |
| 1065 | fas | Persian (Farsi) |
| 1066 | vie | Vietnamese |
| 1068 | aze | Azerbaijani |
| 1069 | eus | Basque |
| 1071 | mkd | Macedonian |
| 1081 | hin | Hindi |
| 1086 | msa | Malay |

**Default Language:** 1033 (English) - Used at DCPL for most patrons

**Note:** Complete list includes 44+ languages. Only most common shown here.

---

## USAGE EXAMPLES

### Query by Notification Type
```sql
-- Get all hold notifications (Type 2)
SELECT * FROM Results.Polaris.NotificationQueue
WHERE NotificationTypeID = 2;
```

### Query by Delivery Method
```sql
-- Get all SMS notifications (DeliveryOptionID 8)
SELECT * FROM Results.Polaris.NotificationQueue
WHERE DeliveryOptionID = 8;
```

### Query by Status
```sql
-- Get all failed notifications
SELECT * FROM PolarisTransactions.Polaris.NotificationLog
WHERE NotificationStatusID IN (4, 5, 6, 8, 10, 11, 13, 14);
```

### Shoutbomb Export Filter
```sql
-- Get notifications for Shoutbomb (Voice + SMS only)
SELECT * FROM Results.Polaris.NotificationQueue
WHERE (DeliveryOptionID = 3 OR DeliveryOptionID = 8)
  AND NotificationTypeID IN (1, 2, 7, 8, 11, 12, 13);
```

---

## CROSS-REFERENCE

### By Document

| Document | Uses NotificationTypeID | Uses DeliveryOptionID | Uses NotificationStatusID |
|----------|------------------------|----------------------|--------------------------|
| TABLE_NotificationQueue.md | ✓ | ✓ | No |
| TABLE_NotificationLog.md | ✓ | ✓ | ✓ |
| TABLE_HoldNotices.md | ✓ (always 2) | ✓ | No |
| SHOUTBOMB_HOLDS_EXPORT.md | ✓ (always 2) | ✓ (filter 3,8) | No |
| SHOUTBOMB_OVERDUE_EXPORT.md | ✓ (1,7,8,11,12,13) | ✓ (filter 3,8) | No |

### By Table

| Table | NotificationTypeID | DeliveryOptionID | NotificationStatusID | LanguageID |
|-------|-------------------|------------------|---------------------|------------|
| NotificationQueue | ✓ | ✓ | No | No |
| NotificationLog | ✓ | ✓ | ✓ | ✓ |
| NotificationHistory | ✓ | ✓ | ✓ | No |
| HoldNotices | ✓ | ✓ | No | ✓ |
| OverdueNotices | ✓ | ✓ | No | ✓ |
| FineNotices | ✓ | ✓ | No | No |

---

## API INTEGRATION

### Laravel papiclient Validation

When using the Laravel papiclient package to update notifications:

**DeliveryOptionID Validation:**
```php
if (!in_array($data['DeliveryOptionID'], [1, 2, 3, 4, 5, 6, 7, 8])) {
    throw new \InvalidArgumentException(
        'DeliveryOptionID must be 1-8'
    );
}
```

**Recommended Status IDs for Shoutbomb:**
```php
// SMS successful delivery
$statusId = 3; // Delivered (SMS)
// OR
$statusId = 16; // Generic sent

// Voice successful delivery
$statusId = 1; // Call completed - Voice
// OR
$statusId = 2; // Call completed - Answering machine

// SMS failure
$statusId = 4; // Failed - Invalid Phone
// OR
$statusId = 10; // Failed - Opted Out

// Voice failure
$statusId = 5; // Failed - No Answer
// OR
$statusId = 8; // Failed - Disconnected
```

---

## VALIDATION RULES

### NotificationTypeID
- Must be valid ID from table (0-21)
- Most common: 1, 2, 7, 8, 11, 12, 13
- Hold notifications ALWAYS use Type 2
- Overdue notifications use Types 1, 7, 12, 13

### DeliveryOptionID
- Must be valid ID from table (1-8)
- Shoutbomb ONLY processes 3 (Voice) and 8 (SMS)
- Each patron has ONE delivery method preference
- Cannot have both Voice (3) and SMS (8) simultaneously

### NotificationStatusID
- Must be valid ID from table (1-16)
- Use success statuses (1, 2, 3, 7, 9, 12, 15, 16) for delivered
- Use failure statuses (4, 5, 6, 8, 10, 11, 13, 14) for failed
- Status 16 (Sent) is safe generic success status

### LanguageID
- Must be valid ID from Polaris Languages table
- Default to 1033 (English) if unknown
- Used for notification text translation

---

## CHANGE LOG

| Date | Change | Impact |
|------|--------|--------|
| 2025-11-19 | Initial lookup tables document created | Centralized reference |
| 2024-11-13 | Verified against actual Polaris database exports | Data accuracy confirmed |

---

## CONTACT

**System Owner:** Brian Lashbrook (blashbrook@dcplibrary.org)

**Related Documentation:**
- Polaris_Notification_Guide_PAPIClient.md
- TABLE_NotificationQueue.md
- TABLE_NotificationLog.md
- POLARIS_TABLES_INDEX.md

**Last Updated:** 2025-11-19
