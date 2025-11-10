# Deployment Checklist

This checklist helps you deploy the Polaris Notifications package to a production or staging environment.

## ✅ Pre-Deployment Checklist

### 1. SQL Server Driver Installation

**Required:** Install the SQL Server PDO driver on your production server.

**For Linux (Recommended - FreeTDS):**
```bash
# Try version-specific package first
sudo apt-get install php8.4-sybase freetds-common

# If unavailable, use generic package
sudo apt-get install php-sybase freetds-common

# Restart PHP-FPM
sudo service php8.4-fpm restart
```

**Verify installation:**
```bash
php -m | grep pdo
# Should show: pdo_dblib or pdo_sqlsrv
```

See [SQL_SERVER_DRIVER_INSTALLATION.md](SQL_SERVER_DRIVER_INSTALLATION.md) for detailed instructions.

### 2. Environment Configuration

Create or update your `.env` file with these settings:

```env
# Polaris MSSQL Database Connection
POLARIS_DB_DRIVER=dblib           # Use 'dblib' for FreeTDS or 'sqlsrv' for Microsoft driver
POLARIS_DB_HOST=your-server.local  # Your Polaris SQL Server hostname
POLARIS_DB_PORT=1433               # Default MSSQL port
POLARIS_DB_DATABASE=Polaris        # Usually 'Polaris'
POLARIS_DB_USERNAME=your-username  # Read-only user recommended
POLARIS_DB_PASSWORD=your-password
POLARIS_REPORTING_ORG_ID=3         # Your library's organization ID

# Shoutbomb FTP (if using FTP reports)
SHOUTBOMB_ENABLED=true
SHOUTBOMB_FTP_HOST=ftp.shoutbomb.com
SHOUTBOMB_FTP_PORT=21
SHOUTBOMB_FTP_USERNAME=your-username
SHOUTBOMB_FTP_PASSWORD=your-password
SHOUTBOMB_FTP_PASSIVE=true

# Email Reports (if using email ingestion)
EMAIL_REPORTS_ENABLED=true
EMAIL_HOST=imap.example.com
EMAIL_PORT=993
EMAIL_USERNAME=your-email@example.com
EMAIL_PASSWORD=your-password
EMAIL_ENCRYPTION=ssl
EMAIL_MAILBOX=INBOX
```

### 3. Database Setup

```bash
# Run migrations
php artisan migrate

# Verify tables were created
php artisan tinker
>>> DB::table('notification_logs')->count();
```

### 4. Test Connections

```bash
# Test all connections
php artisan notices:test-connections

# Test individually
php artisan notices:test-connections --polaris
php artisan notices:test-connections --shoutbomb
php artisan notices:test-connections --email
```

**Expected output for Polaris:**
```
✅ Polaris connection successful
📊 Total notifications in database: [count]
```

If you see errors, refer to the [Troubleshooting](#troubleshooting) section.

### 5. Initial Data Import

```bash
# Import last 7 days of notifications
php artisan notices:import --days=7

# Import Shoutbomb reports (if enabled)
php artisan notices:import-shoutbomb

# Generate daily summaries
php artisan notices:aggregate

# Verify data
php artisan tinker
>>> \Dcplibrary\Notices\Models\NotificationLog::count();
>>> \Dcplibrary\Notices\Models\DailyNotificationSummary::count();
```

### 6. Schedule Regular Imports

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Import notifications daily at 2 AM
    $schedule->command('notices:import --days=1')
        ->dailyAt('02:00')
        ->withoutOverlapping();

    // Aggregate summaries daily at 3 AM
    $schedule->command('notices:aggregate')
        ->dailyAt('03:00');

    // Import Shoutbomb reports weekly
    $schedule->command('notices:import-shoutbomb')
        ->weekly()
        ->sundays()
        ->at('04:00');

    // Import email reports hourly (if enabled)
    $schedule->command('notices:import-email-reports')
        ->hourly();
}
```

Verify the scheduler is running:
```bash
php artisan schedule:list
```

### 7. Set Up Cron Job

Ensure Laravel's scheduler runs on your server:

```bash
# Edit crontab
crontab -e

# Add this line:
* * * * * cd /path/to/your/app && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Authentication Setup

If using the built-in dashboard, ensure authentication is configured:

```php
// config/notices.php
'dashboard' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],  // Customize as needed
],
```

Or disable if building custom frontend:
```php
'dashboard' => [
    'enabled' => false,
],
```

## 🔍 Verification Steps

After deployment, verify everything works:

1. **Connection Test:**
   ```bash
   php artisan notices:test-connections
   ```

2. **Data Import:**
   ```bash
   php artisan notices:import --days=1
   ```

3. **Dashboard Access:**
   Visit: `https://yourapp.com/notices`

4. **API Test:**
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://yourapp.com/api/notices/logs/stats?days=7
   ```

5. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## 🐛 Troubleshooting

### Driver Not Found

**Error:** `could not find driver`

**Solution:**
```bash
# Check if driver is installed
php -m | grep pdo

# Should show pdo_dblib or pdo_sqlsrv
# If not, see SQL_SERVER_DRIVER_INSTALLATION.md
```

### Connection Refused

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Check:**
1. SQL Server is accessible: `telnet your-server 1433`
2. Firewall allows connection on port 1433
3. Credentials are correct in `.env`
4. SQL Server is running and accepting connections

### No Data Imported

**Possible causes:**
1. Wrong `POLARIS_REPORTING_ORG_ID`
2. No notifications in the date range
3. Database user lacks read permissions

**Debug:**
```bash
php artisan notices:import --days=30 -vvv
tail -f storage/logs/laravel.log
```

### Scheduler Not Running

**Verify:**
```bash
# Check if cron job is set
crontab -l

# Test scheduler manually
php artisan schedule:run

# View scheduled tasks
php artisan schedule:list
```

## 🔒 Security Considerations

1. **Database User Permissions:**
   - Use a read-only user for Polaris connection
   - Never use `sa` or admin accounts
   - Grant only SELECT permissions on required tables

2. **Environment Variables:**
   - Never commit `.env` file to version control
   - Use strong passwords for all services
   - Rotate credentials regularly

3. **API Authentication:**
   - Enable authentication on API routes
   - Use Laravel Sanctum or similar
   - Implement rate limiting

4. **Dashboard Access:**
   - Require authentication
   - Limit to authorized users only
   - Consider IP whitelisting if needed

## 📊 Performance Optimization

1. **Database Indexing:**
   Migrations include indexes on common query fields

2. **Batch Size:**
   Adjust in `config/notices.php`:
   ```php
   'import' => [
       'batch_size' => 500,  // Increase for better performance
   ],
   ```

3. **Cache Configuration:**
   Enable Redis/Memcached for better performance

4. **Queue Processing:**
   For large imports, consider using queues:
   ```bash
   php artisan queue:work
   ```

## 📝 Maintenance

### Regular Tasks

1. **Monitor disk space** (logs and database growth)
2. **Review error logs** regularly
3. **Update package** when new versions release:
   ```bash
   composer update dcplibrary/notices
   php artisan migrate
   ```

### Backup Strategy

1. **Database Backups:**
   - Local MySQL database (notification_logs, summaries, etc.)
   - Use Laravel backup package or native MySQL dumps

2. **Configuration Backups:**
   - `.env` file (store securely)
   - `config/notices.php` customizations

## 📚 Additional Resources

- [SQL Server Driver Installation](SQL_SERVER_DRIVER_INSTALLATION.md)
- [API Documentation](API.md)
- [Dashboard Guide](DASHBOARD.md)
- [Testing Guide](TESTING.md)

## ✉️ Support

If you encounter issues:

1. Check the documentation in `docs/` directory
2. Review `storage/logs/laravel.log` for errors
3. Run tests: `vendor/bin/phpunit`
4. Contact the package maintainer

---

**Last Updated:** 2025-11-09
