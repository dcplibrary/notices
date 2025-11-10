# Polaris SQL Schema and Reference Data

This directory is for SQL schema files and CSV exports from the Polaris ILS database to help with data mapping and validation.

## Recommended Files to Upload

### 1. Reference/Lookup Tables (Static Data)

These tables rarely change and define the IDs used throughout the system:

```sql
-- Notification Types
SELECT * FROM Polaris.Polaris.NotificationTypes
ORDER BY NotificationTypeID;

-- Delivery Options
SELECT * FROM Polaris.Polaris.SA_DeliveryOptions
ORDER BY DeliveryOptionID;

-- Notification Statuses
SELECT * FROM Polaris.Polaris.NotificationStatuses
ORDER BY NotificationStatusID;
```

**Export as CSV**: `NotificationTypes.csv`, `DeliveryOptions.csv`, `NotificationStatuses.csv`

### 2. Table Schemas

Export the CREATE TABLE statements for key tables:

**From PolarisTransactions.Polaris**:
- NotificationLog (main transaction log)

**From Results.Polaris**:
- NotificationQueue
- NotificationHistory
- OverdueNotices
- HoldNotices
- FineNotices

**From Polaris.Polaris**:
- Patrons
- PatronRegistration
- SysHoldRequests

### 3. Sample Data (for validation)

Small sample datasets (last 7-30 days) to validate our import logic:

```sql
-- Sample notification logs (anonymized)
SELECT TOP 1000
    NotificationLogID,
    PatronID,
    NotificationDateTime,
    NotificationTypeID,
    DeliveryOptionID,
    NotificationStatusID,
    TotalItems,
    HoldsCount,
    OverduesCount
FROM PolarisTransactions.Polaris.NotificationLog
WHERE NotificationDateTime >= DATEADD(day, -7, GETDATE())
ORDER BY NotificationDateTime DESC;
```

**Export as CSV**: `sample_notification_logs.csv`

## Current Configuration Status

✅ **Updated** (2025-11-10): All reference data has been updated in `config/notices.php` based on the Polaris documentation:

- **Notification Types**: 22 types (IDs 0-21) - Complete
- **Delivery Options**: 9 options (IDs 1-9) - Complete
- **Notification Statuses**: 16 statuses (IDs 1-16) - Complete

This should eliminate "Unknown" values in the dashboard.

## Data Sources

The current configuration is based on:
1. `docs/archive/Polaris_Complete_Notification_System_Guide.md`
2. Production Polaris ILS database documentation

## Validation Commands

Once SQL files are uploaded, you can validate the configuration by running:

```bash
# Check for Unknown notification types
php artisan notices:diagnose-data

# Inspect delivery methods in use
php artisan notices:inspect-delivery-methods

# Sync Shoutbomb Voice/SMS data to main logs
php artisan notices:sync-shoutbomb-to-logs --days=7 --dry-run
```

## Notes

- Phone delivery options: Polaris has 3 separate phone fields (Phone 1, Phone 2, Phone 3) all with ID 3, 4, 5
- Voice/SMS from Shoutbomb uses delivery_option_id 3 (Voice) and 8 (SMS)
- Status ID 12 is used for successful notifications (both email and voice/SMS)
- The NotificationLog table in PolarisTransactions is the authoritative source for sent notifications
