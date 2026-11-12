# Package Tests

This directory contains tests for the Notices package that can be run independently without copying them to a Laravel application.

## Running Tests

### From the Package Directory

```bash
# Install dependencies (if not already installed)
composer install

# Run all tests (Pest runner)
vendor/bin/pest --ci

# Run specific test suite
vendor/bin/pest tests/Feature/NotificationAccountingTest.php --ci
vendor/bin/pest tests/Feature/IssueDetectionTest.php --ci
vendor/bin/pest tests/Feature/NotificationServiceRefactoringTest.php --ci
vendor/bin/pest tests/Unit --ci
vendor/bin/pest tests/Unit/DateFormatterTest.php --ci
vendor/bin/pest tests/Unit/LookupTableServiceTest.php --ci

# Run with coverage (requires xdebug)
vendor/bin/pest --coverage-html coverage
```

### Database modes

By default tests run against in-memory SQLite for speed.

To run against a MySQL-compatible engine (for example Percona 8), set:

```bash
NOTICES_TEST_DB=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE=notices_test \
DB_USERNAME=notices \
DB_PASSWORD=notices \
vendor/bin/pest --ci
```


### Composer scripts

```bash
# Default test command (Pest in CI mode)
composer test

# Pest runner
composer test:pest
composer test:pest:ci
```

## Test Suites

### Feature Tests

#### NotificationAccountingTest
**Purpose:** Verify 100% of notices are accounted for during reconciliation.

**What it tests:**
- All raw Polaris records result in unified notification records
- No notices are lost during deduplication
- Patron/item counts match between raw and unified data
- Duplicate raw records are properly deduplicated
- Different notification types are tracked separately
- Patron barcodes are preserved from raw to unified data

**Key test:**
```php
test_all_raw_hold_notifications_are_accounted_for()
```
This test creates various scenarios (same patron/different items, different patrons, different delivery methods) and verifies that:
1. The correct number of unified notifications are created
2. Items are grouped correctly by patron+delivery+type
3. Total item count is preserved
4. All patron barcodes are accounted for

#### IssueDetectionTest
**Purpose:** Verify delivery issue detection logic works correctly.

**What it tests:**
- Mail notifications are NEVER flagged as `lx_starter_failure`
- Electronic notifications (email/SMS/voice) ARE flagged when pending
- `lx_starter_failure` issues auto-resolve when status changes
- Null preference issues are detected correctly
- Multiple issue types can exist for one notification

**Critical test:**
```php
test_mail_notifications_not_flagged_as_lx_starter_failure()
```
This verifies that mail (delivery_option_id = 1) notifications remain pending without creating issues, since they're printed manually by staff.

#### NotificationServiceRefactoringTest
**Purpose:** Verify refactored methods in `NotificationService` work correctly.

**What it tests:**
- `findPatronByIdOrBarcode()` - Centralized patron lookup across multiple Polaris tables
- `getMismatchedPreferenceConfirmation()` - Delivery confirmation query for mismatched preferences
- `buildFailedContactApiResponse()` - JSON response construction for failed contact modal

**Key tests:**
```php
test_find_patron_by_id()                              // Find by patron_id
test_find_patron_by_barcode_fallback()                // Fallback to barcode
test_find_patron_returns_null_when_not_found()        // Null handling
test_get_mismatched_preference_confirmation()         // Confirmation retrieval
test_get_mismatched_preference_confirmation_returns_null() // Null handling
test_build_failed_contact_api_response()              // Response building
test_build_failed_contact_api_response_with_nulls()   // Null handling
test_build_failed_contact_api_response_resolved()     // Resolved status
```

**Test Coverage:**
- ✅ Patron lookup across `polaris_hold_notifications`, `polaris_renewal_notifications`, `polaris_overdue_notifications`
- ✅ Delivery confirmation queries for mismatched preferences
- ✅ JSON response structure and field mapping
- ✅ Null handling and edge cases
- ✅ Resolved status handling

### Unit Tests

#### DeliveryMethodHelperTest
Tests the `DeliveryMethodHelper` class:
- Electronic vs manual delivery classification
- Constant values match Polaris DeliveryOptionID values
- Method name and icon type retrieval
- Helper arrays contain correct values

#### IssueDisplayHelperTest
Tests the `IssueDisplayHelper` class:
- Issue descriptions for all issue types
- Patron name extraction from notification payload
- Item information extraction
- Handling of missing/malformed data
- Multiple items in payload
- Phone number extraction from issue details

#### DateFormatterTest
Tests the `DateFormatter` helper class:
- Date formatting with standard format (M j, Y)
- DateTime formatting with standard format (M j, Y g:i A)
- Null handling (returns null for null input)
- Format constants (DATE_FORMAT, DATETIME_FORMAT)
- DateTimeInterface compatibility

**Key tests:**
```php
test_format_date()                    // Standard date formatting
test_format_datetime()                // Standard datetime formatting
test_format_date_with_null()          // Null handling
test_format_datetime_with_null()      // Null handling
test_format_constants()               // Constant values
test_with_datetime_interface()        // Interface compatibility
```

#### LookupTableServiceTest
Tests the `LookupTableService` class:
- Retrieval of enabled notification types with custom labels
- Retrieval of enabled delivery options with custom labels
- Filtering of disabled items (respects `is_enabled` override)
- Custom label application (from `lookup_table_overrides`)
- Correct ordering of results

**Key tests:**
```php
test_get_enabled_notification_types()        // Basic retrieval
test_notification_types_with_custom_labels() // Custom label override
test_disabled_notification_types_are_filtered() // Filtering logic
test_get_enabled_delivery_options()          // Delivery options retrieval
test_delivery_options_with_custom_labels()   // Custom label override
test_disabled_delivery_options_are_filtered() // Filtering logic
test_results_are_ordered()                   // Ordering verification
```

#### NotificationServiceRefactoringTest
**Purpose:** Verify refactored methods in `NotificationService` work correctly.

**What it tests:**
- `findPatronByIdOrBarcode()` - Centralized patron lookup across multiple Polaris tables
- `getMismatchedPreferenceConfirmation()` - Delivery confirmation query for mismatched preferences
- `buildFailedContactApiResponse()` - JSON response construction for failed contact modal

**Key tests:**
```php
test_find_patron_by_id()                              // Find by patron_id
test_find_patron_by_barcode_fallback()                // Fallback to barcode
test_find_patron_returns_null_when_not_found()        // Null handling
test_get_mismatched_preference_confirmation()         // Confirmation retrieval
test_get_mismatched_preference_confirmation_returns_null() // Null handling
test_build_failed_contact_api_response()              // Response building
test_build_failed_contact_api_response_with_nulls()   // Null handling
test_build_failed_contact_api_response_resolved()     // Resolved status
```

**Test Coverage:**
- ✅ Patron lookup across `polaris_hold_notifications`, `polaris_renewal_notifications`, `polaris_overdue_notifications`
- ✅ Delivery confirmation queries for mismatched preferences
- ✅ JSON response structure and field mapping
- ✅ Null handling and edge cases
- ✅ Resolved status handling

## Test Coverage Goals

### 100% Accounting Verification

The accounting tests verify that:

1. **No data loss:** Every raw notification record is accounted for in the unified notifications
2. **Correct grouping:** Notifications are grouped by `patron_id + delivery_option_id + notification_type_id`
3. **Item preservation:** All items from raw records appear in unified `raw_payload`
4. **Duplicate handling:** Identical raw records don't create duplicate notifications

### Issue Detection Accuracy

The issue detection tests verify that:

1. **Correct classification:** Only electronic delivery gets `lx_starter_failure` issues
2. **No false positives:** Mail staying pending is normal, not an issue
3. **Auto-resolution:** Issues resolve automatically when conditions change
4. **Complete coverage:** All issue types are detected appropriately

## Writing New Tests

### Testing Notification Accounting

When adding new notification reconciliation logic:

1. Create test data using `PolarisHoldNotification::create()`
2. Run `$this->reconciler->reconcileHolds($date)`
3. Query `Notification` model to verify results
4. Check counts, groupings, and data preservation

Example:
```php
// Create raw data
PolarisHoldNotification::create([...]);

// Run reconciliation
$this->reconciler->reconcileHolds($date);

// Verify results
$notifications = Notification::all();
$this->assertCount(expectedCount, $notifications);
```

### Testing Issue Detection

When adding new issue types or detection logic:

1. Create notifications with specific conditions
2. Run reconciliation (which triggers issue detection)
3. Query `PatronDeliveryIssue` to verify issues were created correctly

Example:
```php
// Create condition that should trigger issue
$notification = Notification::factory()->create([...]);

// Run detection
$this->reconciler->reconcileHolds($date);

// Verify issue was created
$issue = PatronDeliveryIssue::where('notification_id', $notification->id)
    ->where('issue_type', 'expected_type')
    ->first();
$this->assertNotNull($issue);
```

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
- name: Run Package Tests
  run: |
    cd packages/notices
    composer install
    vendor/bin/pest --ci
```

#### ImportStatusTest
**Purpose:** Verify dashboard import monitoring functionality.

**What it tests:**
- Import status data structure and required fields
- Status determination (success, warning, error) based on import age
- Schedule adherence checking based on time of day
- Last import time formatting (12-hour format)
- File counting for multiple imports from same source
- Behavior when no imports exist (defaults to error status)
- Mixed statuses across different sources

**Key tests:**
```php
test_shows_success_status_for_recent_imports()  // <25 hours = success
test_shows_warning_status_for_old_imports()    // 25-48 hours = warning
test_shows_error_status_for_very_old_or_missing_imports() // >48 hours = error
test_checks_schedule_adherence_after_first_import_time() // Behind schedule detection
```

**Test Coverage:**
- ✅ getImportStatus() return structure
- ✅ All four sources (ftp_holds, ftp_renew, ftp_overdue, email_reports)
- ✅ Status calculation logic
- ✅ Time-of-day schedule checks
- ✅ File counting and aggregation
- ✅ Date/time formatting
- ✅ Edge cases (no imports, very old imports, mixed statuses)

#### ScheduledTasksTest
**Purpose:** Verify automatic import scheduling registration.

**What it tests:**
- All four daily import schedules are registered
- Correct schedule times (8:30 AM, 9:30 AM, 1:30 PM, 5:30 PM)
- Proper command usage (`notices:import --date=today`)
- Background execution configuration
- Daily recurrence (not weekly/monthly)

**Key tests:**
```php
test_registers_scheduled_import_tasks()      // 4 tasks registered
test_schedules_morning_import_at_8_30_am()   // 8:30 AM schedule
test_scheduled_tasks_run_in_background()     // Background execution
test_scheduled_tasks_are_daily_not_weekly_or_monthly() // Daily recurrence
```

**Test Coverage:**
- ✅ Task registration via NoticesServiceProvider
- ✅ All four expected schedule times
- ✅ Command and parameters
- ✅ Background execution flag
- ✅ Cron expression validation
- ✅ Daily recurrence pattern

**Running These Tests:**
```bash
# Run import status tests
vendor/bin/pest tests/Feature/ImportStatusTest.php --ci

# Run scheduled tasks tests
vendor/bin/pest tests/Feature/ScheduledTasksTest.php --ci

# Run both new test suites
vendor/bin/pest tests/Feature/ImportStatusTest.php tests/Feature/ScheduledTasksTest.php --ci
```

## Test Database

Tests use in-memory SQLite by default via `tests/TestCase.php`:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

For MySQL/Percona compatibility runs, set `NOTICES_TEST_DB=mysql` and DB credentials.

Migrations run automatically via `RefreshDatabase` trait in `TestCase`.
