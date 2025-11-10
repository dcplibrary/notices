# Testing Guide

This document explains how to test the Polaris Notifications package.

## Quick Start

**Prerequisites:** SQLite or MySQL database must be available.

```bash
# 1. Install dependencies
composer install

# 2. Install SQLite (recommended)
sudo apt-get install php-sqlite3

# 3. Run tests
vendor/bin/phpunit
```

**Note:** If tests fail with "Connection refused", see [Troubleshooting](#troubleshooting) section below.

## Test Infrastructure

This package uses:
- **PHPUnit 10** for the test framework
- **Orchestra Testbench** for Laravel package testing
- **SQLite in-memory database** for fast, isolated tests (preferred)
- **MySQL fallback** if SQLite is not available
- **Model Factories** for generating realistic test data

### Database Requirements

The test suite automatically detects which database driver is available:
- **SQLite** (preferred): Tests run in-memory, very fast, no setup required
- **MySQL** (fallback): If SQLite is not installed, tests use MySQL

**To use SQLite** (recommended):
```bash
# Install SQLite extension for PHP
# Ubuntu/Debian
sudo apt-get install php-sqlite3

# macOS (with Homebrew)
brew install php

# Check if installed
php -m | grep sqlite
```

**To use MySQL**:
```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE notifications_test;"

# Set environment variables (optional)
export DB_TEST_DATABASE=notifications_test
export DB_USERNAME=root
export DB_PASSWORD=your_password
```

## Running Tests

### Install Dependencies

```bash
composer install
```

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Run only unit tests
vendor/bin/phpunit --testsuite Unit

# Run only feature tests
vendor/bin/phpunit --testsuite Feature
```

### Run Specific Test Files

```bash
# Test a specific model
vendor/bin/phpunit tests/Unit/Models/NotificationLogTest.php

# Test a specific service
vendor/bin/phpunit tests/Unit/Services/ShoutbombFileParserTest.php
```

### Run with Coverage (if xdebug installed)

```bash
vendor/bin/phpunit --coverage-html coverage
```

## Test Structure

```
tests/
├── TestCase.php              # Base test case with setup
├── Unit/                     # Unit tests (isolated components)
│   ├── Models/              # Model tests
│   │   ├── NotificationLogTest.php
│   │   └── DailyNotificationSummaryTest.php
│   └── Services/            # Service tests
│       └── ShoutbombFileParserTest.php
└── Feature/                  # Integration tests
    └── NotificationWorkflowTest.php
```

## Writing Tests

### Model Tests

Model tests verify:
- Model creation and attributes
- Scopes and query builders
- Attribute accessors
- Data casting
- Relationships

Example:
```php
/** @test */
public function it_can_filter_successful_notifications()
{
    NotificationLog::factory()
        ->count(5)
        ->successful()
        ->create();

    NotificationLog::factory()
        ->count(3)
        ->failed()
        ->create();

    $results = NotificationLog::successful()->get();

    $this->assertCount(5, $results);
}
```

### Service Tests

Service tests verify:
- Business logic
- Data parsing
- File processing
- External service integration (mocked)

Example:
```php
/** @test */
public function it_can_parse_monthly_report()
{
    $content = "Registration Statistics: 13307 text (72%), 5199 voice (28%)";
    $filePath = $this->createTempFile('report.txt', $content);

    $data = $this->parser->parseMonthlyReport($filePath);

    $this->assertEquals(13307, $data['registration_stats']['total_text_subscribers']);
}
```

### Feature Tests

Feature tests verify:
- End-to-end workflows
- Multiple components working together
- Data integrity across tables

## Using Factories

Factories are located in `database/factories/` and provide convenient states:

### NotificationLog Factory

```php
// Create basic notification
NotificationLog::factory()->create();

// Create with specific states
NotificationLog::factory()
    ->email()
    ->holds()
    ->successful()
    ->create();

// Available states:
// Types: holds(), overdues(), almostOverdue()
// Delivery: email(), sms(), voice(), mail()
// Status: successful(), failed(), unreported()
```

### DailyNotificationSummary Factory

```php
DailyNotificationSummary::factory()
    ->email()
    ->holds()
    ->highSuccess()
    ->create(['summary_date' => today()]);

// Available states:
// Types: holds(), overdues()
// Delivery: email(), sms(), voice()
// Performance: highSuccess(), lowSuccess()
```

### ShoutbombDelivery Factory

```php
ShoutbombDelivery::factory()
    ->sms()
    ->delivered()
    ->create();

// Available states:
// Type: sms(), voice()
// Status: delivered(), failed(), invalid()
// Notice: holdNotice(), overdueNotice()
```

## Demo Data Seeder

To visualize what the data looks like in your application, use the demo seeder:

### Seed Demo Data

```bash
# Generate 30 days of demo data (default)
php artisan notices:seed-demo

# Generate 60 days of data
php artisan notices:seed-demo --days=60

# Clear existing data and seed fresh
php artisan notices:seed-demo --fresh
```

This will create:
- **Notification logs** (email, SMS, voice notifications)
- **Daily summaries** (aggregated statistics)
- **Shoutbomb deliveries** (SMS/voice delivery tracking)
- **Keyword usage** (patron interactions)
- **Registration snapshots** (subscriber statistics)

### Use Cases for Demo Data

1. **Dashboard Development**: See realistic data in your UI
2. **Manual QA**: Test filters, charts, and reports
3. **Demos**: Show stakeholders what the system tracks
4. **Performance Testing**: Generate large datasets

## Mocking External Services

### Polaris Database

Tests mock the Polaris connection using SQLite:

```php
// TestCase.php automatically configures this
$app['config']->set('database.connections.polaris', [
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
```

### Shoutbomb FTP

Mock file operations in tests:

```php
use Illuminate\Support\Facades\Storage;

Storage::fake('shoutbomb');
Storage::disk('shoutbomb')->put('report.csv', $content);
```

## Continuous Integration

The package includes a GitHub Actions workflow (`.github/workflows/php.yml`).

To enable tests in CI, uncomment the test section in the workflow file.

## Best Practices

1. **Use factories** instead of manually creating records
2. **Mock external services** (Polaris DB, Shoutbomb FTP)
3. **Test one thing per test** - keep tests focused
4. **Use descriptive test names** - `it_can_filter_by_date_range()`
5. **Arrange-Act-Assert** pattern:
   ```php
   // Arrange - set up test data
   $notification = NotificationLog::factory()->create();

   // Act - perform the action
   $result = $notification->total_items;

   // Assert - verify the result
   $this->assertEquals(5, $result);
   ```

## Troubleshooting

### Tests fail with "Connection refused" or database errors

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

This means neither SQLite nor MySQL is available for testing.

**Solution 1: Install SQLite (Recommended)**
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3 php8.4-sqlite3

# Verify installation
php -m | grep sqlite
```

**Solution 2: Use MySQL**
```bash
# Start MySQL service
sudo service mysql start

# Create test database
mysql -u root -e "CREATE DATABASE notifications_test;"

# Run tests
vendor/bin/phpunit
```

**Solution 3: Skip database tests**

If you only need to test logic without database:
```php
// Mark tests as incomplete
$this->markTestSkipped('Database not available');
```

### Tests fail with "could not find driver"

**Error:** `could not find driver (Connection: testing, SQL: ...)`

SQLite PDO extension is not installed.

**Solution:**
```bash
# Check which PDO drivers are available
php -m | grep pdo

# Install SQLite PDO
sudo apt-get install php-sqlite3 php8.4-sqlite3

# Restart PHP-FPM if using it
sudo service php8.4-fpm restart
```

### Tests fail with database errors

Ensure migrations are running:
```php
protected function setUp(): void
{
    parent::setUp();
    $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
}
```

### Factory not found

Check that `composer.json` autoload-dev includes:
```json
"autoload-dev": {
    "psr-4": {
        "Dcplibrary\\Notifications\\Tests\\": "tests/"
    }
}
```

Run: `composer dump-autoload`

### Config values are null

Ensure TestCase loads the config:
```php
$app['config']->set('notices', require __DIR__ . '/../config/notices.php');
```

## Next Steps

- Add tests for remaining services (PolarisImportService, NotificationAggregatorService)
- Add command tests
- Add tests for edge cases and error handling
- Increase test coverage
- Add performance/stress tests for large datasets

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Orchestra Testbench](https://packages.tools/testbench.html)
- [Laravel Testing](https://laravel.com/docs/testing)
