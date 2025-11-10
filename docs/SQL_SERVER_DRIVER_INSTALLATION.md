# SQL Server Driver Installation Guide

This guide explains how to fix the "could not find driver" error when connecting to the Polaris database.

## The Problem

The error message `could not find driver` indicates that PHP cannot connect to Microsoft SQL Server because the required PDO driver is not installed.

## Check Current Drivers

First, check which PDO drivers are currently installed:

```bash
php -m | grep pdo
```

You should see output like:
```
PDO
pdo_mysql
pdo_pgsql
```

For SQL Server connections, you need **one** of these additional drivers:
- `pdo_sqlsrv` (Microsoft's official driver)
- `pdo_dblib` (FreeTDS driver - easier to install on Linux)

## Solution Options

Choose the option that best fits your environment:

### Option 1: FreeTDS (Recommended for Linux)

FreeTDS is easier to install and works well for most use cases.

#### Ubuntu/Debian (PHP 8.4)

```bash
# Install the PHP Sybase extension (includes pdo_dblib)
sudo apt-get update
sudo apt-get install php8.4-sybase freetds-common

# Restart PHP-FPM
sudo service php8.4-fpm restart

# Or if using Apache
sudo service apache2 restart
```

**Note:** If `php8.4-sybase` is not available in your repositories:

1. **Option A - Use generic package (recommended):**
   ```bash
   sudo apt-get install php-sybase freetds-common
   # This installs the sybase extension for your default PHP version
   ```

2. **Option B - Enable ondrej/php PPA:**
   ```bash
   sudo add-apt-repository ppa:ondrej/php
   sudo apt-get update
   sudo apt-get install php8.4-sybase freetds-common
   ```

3. **Option C - Compile from source (advanced):**
   ```bash
   sudo apt-get install freetds-dev php8.4-dev
   sudo pecl install pdo_dblib
   echo "extension=pdo_dblib.so" | sudo tee /etc/php/8.4/mods-available/pdo_dblib.ini
   sudo phpenmod pdo_dblib
   ```

#### For other PHP versions

Replace `php8.4` with your version (e.g., `php8.3`, `php8.2`, etc.):

```bash
sudo apt-get install php8.3-sybase freetds-common
```

#### CentOS/RHEL

```bash
sudo yum install php-mssql freetds
sudo systemctl restart php-fpm
```

#### macOS

```bash
brew install freetds
pecl install pdo_dblib
```

#### Update Configuration

After installing FreeTDS, update your `.env` file to use the `dblib` driver:

```env
POLARIS_DB_DRIVER=dblib
POLARIS_DB_HOST=your-sql-server-host
POLARIS_DB_PORT=1433
POLARIS_DB_DATABASE=Polaris
POLARIS_DB_USERNAME=your-username
POLARIS_DB_PASSWORD=your-password
```

### Option 2: Microsoft ODBC Driver (Recommended for Production)

Microsoft's official driver provides the best compatibility and performance but requires more setup.

#### Ubuntu/Debian

```bash
# 1. Add Microsoft repository
curl https://packages.microsoft.com/keys/microsoft.asc | sudo tee /etc/apt/trusted.gpg.d/microsoft.asc
curl https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/prod.list | sudo tee /etc/apt/sources.list.d/mssql-release.list

# 2. Install ODBC Driver
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18

# 3. Install PHP sqlsrv extension
sudo pecl install sqlsrv pdo_sqlsrv

# 4. Enable the extensions
echo "extension=sqlsrv.so" | sudo tee /etc/php/8.4/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.4/mods-available/pdo_sqlsrv.ini
sudo phpenmod sqlsrv pdo_sqlsrv

# 5. Restart PHP-FPM
sudo service php8.4-fpm restart
```

#### Configuration

With Microsoft's driver, use `sqlsrv` driver in your `.env`:

```env
POLARIS_DB_DRIVER=sqlsrv
POLARIS_DB_HOST=your-sql-server-host
POLARIS_DB_PORT=1433
POLARIS_DB_DATABASE=Polaris
POLARIS_DB_USERNAME=your-username
POLARIS_DB_PASSWORD=your-password
```

### Option 3: Windows

On Windows, download the Microsoft Drivers for PHP for SQL Server:

1. Visit: https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
2. Download the appropriate version for your PHP installation
3. Extract the DLL files to your PHP extension directory
4. Add to `php.ini`:
   ```ini
   extension=php_sqlsrv_84_ts.dll
   extension=php_pdo_sqlsrv_84_ts.dll
   ```
5. Restart your web server

## Verify Installation

After installation, verify the driver is available:

```bash
# Check for the driver
php -m | grep -i pdo

# Should show one of:
# - pdo_sqlsrv (Microsoft driver)
# - pdo_dblib (FreeTDS driver)
```

## Test Connection

Use the test command to verify your Polaris connection:

```bash
php artisan notices:test-connections --polaris
```

You should see:
```
✅ Polaris connection successful
📊 Total notifications in database: [count]
```

## Troubleshooting

### Still getting "could not find driver"?

1. **Verify PHP version matches the extension:**
   ```bash
   php -v
   dpkg -l | grep php8.4-sybase  # Check package is for correct version
   ```

2. **Check if the extension is enabled:**
   ```bash
   php -m | grep pdo
   php --ri pdo_dblib  # For FreeTDS
   php --ri pdo_sqlsrv  # For Microsoft driver
   ```

3. **Restart PHP-FPM** (the extension won't load until you do):
   ```bash
   sudo service php8.4-fpm restart
   ```

4. **Check PHP-FPM vs CLI:**
   If the command line works but web requests don't, you may need to install the extension for both CLI and FPM:
   ```bash
   sudo apt-get install php8.4-sybase  # Usually installs for both
   ```

### Connection errors after driver is installed?

1. **Check SQL Server is accessible:**
   ```bash
   telnet your-sql-server-host 1433
   ```

2. **Verify credentials** in your `.env` file

3. **Check FreeTDS configuration** (if using dblib):
   Edit `/etc/freetds/freetds.conf` if you need specific SQL Server version settings

4. **Enable error logging** in your Laravel app to see detailed connection errors

### Driver compatibility issues?

If you have issues with the `dblib` driver, try these FreeTDS configurations in `/etc/freetds/freetds.conf`:

```ini
[global]
    tds version = 7.4
    client charset = UTF-8
```

## Driver Comparison

| Feature | FreeTDS (dblib) | Microsoft (sqlsrv) |
|---------|-----------------|-------------------|
| Installation | ✅ Easy (apt-get) | ⚠️ Complex |
| Linux Support | ✅ Native | ✅ Supported |
| macOS Support | ✅ Native | ⚠️ Limited |
| Windows Support | ❌ Not recommended | ✅ Native |
| Performance | ✅ Good | ✅ Excellent |
| Compatibility | ✅ Good | ✅ Excellent |
| Maintenance | ✅ Active | ✅ Active |

**Recommendation:**
- **Linux**: Use FreeTDS (`dblib`) for easier installation
- **Windows**: Use Microsoft driver (`sqlsrv`)
- **Production**: Microsoft driver preferred for best performance
- **Development**: FreeTDS is fine

## Development/Testing Without Driver

If you're in a restricted environment (CI/CD, containers, etc.) where you can't install the driver:

### Option 1: Mock Connection for Testing

Update your testing database configuration to use SQLite or MySQL:

```php
// config/database.php or tests/TestCase.php
'polaris' => [
    'driver' => 'sqlite',  // or 'mysql'
    'database' => ':memory:',
],
```

### Option 2: Skip Driver-Dependent Tests

```php
// In your tests
public function testPolarisImport()
{
    if (!extension_loaded('pdo_dblib') && !extension_loaded('pdo_sqlsrv')) {
        $this->markTestSkipped('SQL Server driver not available');
    }

    // Test code here...
}
```

### Option 3: Use Docker for Development

Create a `docker-compose.yml` with the driver pre-installed:

```yaml
version: '3.8'
services:
  app:
    image: php:8.4-fpm
    volumes:
      - .:/var/www/html
    command: |
      bash -c "
      apt-get update &&
      apt-get install -y freetds-dev php-pear php-dev &&
      pecl install pdo_dblib &&
      docker-php-ext-enable pdo_dblib &&
      php-fpm
      "
```

## Getting Help

If you're still having issues:

1. Check the error logs: `storage/logs/laravel.log`
2. Run with verbose output: `php artisan notices:test-connections --polaris -v`
3. Check PHP error logs: `tail -f /var/log/php8.4-fpm.log`
4. Verify repository access: `apt-cache policy php8.4-sybase`

## References

- [Microsoft PHP SQL Server Drivers](https://docs.microsoft.com/en-us/sql/connect/php/)
- [FreeTDS Documentation](https://www.freetds.org/)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
