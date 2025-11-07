# CORRECTION: Patron Contact Info vs. Delivery Preference

## The Misunderstanding

**❌ WRONG:** "If patron has email, then DeliveryOptionID = 2 (Email)"

**✅ CORRECT:** "DeliveryOptionID determines notification method. Patrons can have multiple contact methods."

## How It Actually Works

### Contact Information (Multiple Methods)
Patrons can have ALL of these on file:
- `EmailAddress` - Their email
- `PhoneVoice1`, `PhoneVoice2`, `PhoneVoice3` - Their phone numbers
- Physical address (linked via PatronAddresses)

### Delivery Preference (Single Choice)
`DeliveryOptionID` determines which method to USE:
- **1** = Mail → Use physical address
- **2** = Email → Use EmailAddress
- **3** = Voice → Use PhoneVoice1
- **8** = SMS → Use PhoneVoice1

### Real-World Example

```
Patron: John Smith
  EmailAddress: john.smith@gmail.com
  PhoneVoice1: 2701234567
  PhoneVoice2: 2709876543
  DeliveryOptionID: 8 (SMS)

→ Notifications sent via SMS to 2701234567
→ Email and other phone are on file but NOT used for notifications
→ Could change DeliveryOptionID to 2 later to switch to email
```

## Data Generation Rules

### ✅ Correct Approach:
```python
# Generate contact info for most patrons (80-90%)
email = generate_email() if random.random() > 0.15 else ""
phone = generate_phone() if random.random() > 0.15 else ""

# Choose delivery preference
delivery_option = weighted_choice([1, 2, 3, 8])

# Validate required contact for chosen delivery method
if delivery_option == 2 and not email:
    email = generate_email()  # MUST have email for email delivery

if delivery_option in [3, 8] and not phone:
    phone = generate_phone()  # MUST have phone for voice/SMS delivery
```

### ❌ Wrong Approach (What I Did Before):
```python
# Only generate email if delivery is email
if delivery_option == 2:
    email = generate_email()
else:
    email = ""  # Wrong! They could still have email on file
```

## NotificationLogs.DeliveryString Logic

The DeliveryString in NotificationLogs should match the patron's chosen delivery method:

```python
if delivery_option == 2:  # Email
    delivery_string = patron.email_address

elif delivery_option in [3, 8]:  # Voice or SMS
    delivery_string = patron.phone_voice1

else:  # Mail
    delivery_string = ""  # Physical address used, not in DeliveryString
```

## Impact on Generated Data

### Before (Wrong):
- Email patrons have email, no phone
- SMS patrons have phone, no email
- Unrealistic - most people have both!

### After (Correct):
- Most patrons have both email AND phone
- DeliveryOptionID selects which to use
- More realistic contact information
- Allows for future preference changes

## Validation Rules

When generating test data, ensure:
1. ✅ If DeliveryOptionID = 2, EmailAddress must be populated
2. ✅ If DeliveryOptionID = 3 or 8, PhoneVoice1 must be populated
3. ✅ Patrons can have BOTH email and phone regardless of preference
4. ✅ NotificationLogs.DeliveryString matches the contact method for their DeliveryOptionID
5. ✅ Some patrons may have only one contact method (but it must match their preference)

## Why This Matters

This affects:
- **Data realism**: Real patrons have multiple contact methods
- **Testing flexibility**: Can test preference changes
- **Verification**: DeliveryString must match actual preference, not just any available contact
- **Shoutbomb integration**: Voice/SMS patrons must have phone, but could also have email

---

**Date:** November 7, 2025
**Fixed in:** Next data generation update
