# Docker Setup Guide

This guide explains how to run the Polaris Notifications package using Docker.

## Overview

The Docker setup includes:
- **PHP 8.3 Apache** container with all required extensions
- **FreeTDS** for SQL Server connectivity (pdo_dblib driver)
- **Apache** web server with rewrite module enabled
- **Composer** for dependency management
- **Node.js 20** for frontend assets

## Prerequisites

- Docker installed (version 20.10+)
- Docker Compose installed (version 2.0+)
- Access to the nginx-proxy network (or modify docker-compose.yml)

## Quick Start

### 1. Build and Start Container

```bash
# Build the Docker image (includes SQL Server driver)
docker-compose build

# Start the container
docker-compose up -d

# View logs
docker-compose logs -f
```

### 2. Install Dependencies

```bash
# Enter the container
docker exec -it notifications bash

# Inside container: Install Composer dependencies
composer install

# Exit container
exit
```

### 3. Configure Environment

Create a `.env` file in the project root:

```env
APP_NAME="DCPL Notifications"
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=https://notifications.dcplibrary.org

# Database (your main Laravel database)
DB_CONNECTION=mysql
DB_HOST=your-mysql-host
DB_PORT=3306
DB_DATABASE=notifications
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

# Polaris SQL Server Connection
# IMPORTANT: Use 'dblib' driver (FreeTDS is installed in Docker)
POLARIS_DB_DRIVER=dblib
POLARIS_DB_HOST=your-polaris-server.dcpl.local
POLARIS_DB_PORT=1433
POLARIS_DB_DATABASE=Polaris
POLARIS_DB_USERNAME=polaris-readonly-user
POLARIS_DB_PASSWORD=your-polaris-password
POLARIS_REPORTING_ORG_ID=3

# Shoutbomb FTP (optional)
SHOUTBOMB_ENABLED=true
SHOUTBOMB_FTP_HOST=ftp.shoutbomb.com
SHOUTBOMB_FTP_PORT=21
SHOUTBOMB_FTP_USERNAME=your-ftp-user
SHOUTBOMB_FTP_PASSWORD=your-ftp-password
SHOUTBOMB_FTP_PASSIVE=true

# Email Reports (optional)
EMAIL_REPORTS_ENABLED=false
EMAIL_HOST=imap.example.com
EMAIL_PORT=993
EMAIL_USERNAME=notifications@dcplibrary.org
EMAIL_PASSWORD=your-email-password
EMAIL_ENCRYPTION=ssl
```

### 4. Run Migrations

```bash
docker exec -it notifications php artisan migrate
```

### 5. Test Connections

```bash
# Test Polaris connection (should work now!)
docker exec -it notifications php artisan notices:test-connections --polaris

# Expected output:
# ✅ Polaris connection successful
# 📊 Total notifications in database: [count]
```

### 6. Import Initial Data

```bash
# Import last 7 days of notifications
docker exec -it notifications php artisan notices:import --days=7

# Generate summaries
docker exec -it notifications php artisan notices:aggregate
```

## Dockerfile Explanation

The Dockerfile includes these key additions for SQL Server support:

```dockerfile
# Install FreeTDS for SQL Server connectivity
&& apt-get install -y freetds-dev freetds-bin freetds-common \
&& docker-php-ext-configure pdo_dblib --with-libdir=/lib/x86_64-linux-gnu \
&& docker-php-ext-install pdo_dblib \
```

This installs:
- **freetds-dev**: FreeTDS development libraries
- **freetds-bin**: FreeTDS command-line tools
- **freetds-common**: FreeTDS common files
- **pdo_dblib**: PHP extension for SQL Server via FreeTDS

## Verify Installation

To verify the SQL Server driver is installed in the container:

```bash
# Check PHP modules
docker exec -it notifications php -m | grep pdo

# Should output:
# PDO
# pdo_dblib
# pdo_mysql
```

## Container Management

### Start Container
```bash
docker-compose up -d
```

### Stop Container
```bash
docker-compose down
```

### Rebuild Container (after Dockerfile changes)
```bash
docker-compose build --no-cache
docker-compose up -d
```

### View Logs
```bash
docker-compose logs -f web
```

### Execute Commands in Container
```bash
# Interactive shell
docker exec -it notifications bash

# Run Artisan commands
docker exec -it notifications php artisan list

# Run Composer
docker exec -it notifications composer update
```

## Scheduled Tasks (Cron)

To run scheduled tasks inside the Docker container:

### Option 1: Host Cron

Add to your host machine's crontab:

```bash
# Edit crontab
crontab -e

# Add this line:
* * * * * docker exec -it notifications php artisan schedule:run >> /dev/null 2>&1
```

### Option 2: Container Cron

Modify the Dockerfile to include cron:

```dockerfile
# Install cron
RUN apt-get install -y cron \
&& echo "* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1" | crontab -

# Start cron in CMD
CMD cron && apache2-foreground
```

Then rebuild:
```bash
docker-compose build --no-cache
docker-compose up -d
```

## Troubleshooting

### Driver Still Not Found?

1. **Rebuild the container:**
   ```bash
   docker-compose down
   docker-compose build --no-cache
   docker-compose up -d
   ```

2. **Check if driver is installed:**
   ```bash
   docker exec -it notifications php -m | grep pdo_dblib
   ```

3. **Check FreeTDS version:**
   ```bash
   docker exec -it notifications tsql -C
   ```

4. **Verify .env has correct driver:**
   ```bash
   docker exec -it notifications cat .env | grep POLARIS_DB_DRIVER
   # Should show: POLARIS_DB_DRIVER=dblib
   ```

### Connection Errors

1. **Can't connect to Polaris server:**
   - Verify the Docker container can reach the Polaris server:
     ```bash
     docker exec -it notifications ping your-polaris-server.dcpl.local
     docker exec -it notifications nc -zv your-polaris-server.dcpl.local 1433
     ```
   - Check if firewall allows connections from Docker network
   - Verify network settings in docker-compose.yml

2. **Connection refused:**
   - Verify Polaris SQL Server is running
   - Check port 1433 is accessible
   - Verify credentials in .env file

### Permission Issues

If you encounter permission issues with files:

```bash
# Fix permissions (run on host)
sudo chown -R $USER:$USER .
chmod -R 755 storage bootstrap/cache
```

### View Container Logs

```bash
# Apache error logs
docker exec -it notifications tail -f /var/log/apache2/error.log

# Laravel logs
docker exec -it notifications tail -f /var/www/storage/logs/laravel.log
```

## Production Considerations

### 1. Environment Variables

Never commit `.env` to version control. Use Docker secrets or environment variable injection:

```yaml
# docker-compose.yml
services:
  web:
    environment:
      - POLARIS_DB_DRIVER=${POLARIS_DB_DRIVER}
      - POLARIS_DB_HOST=${POLARIS_DB_HOST}
      # ... etc
```

### 2. Persistent Storage

Ensure important data persists outside container:

```yaml
volumes:
  - .:/var/www
  - ./storage:/var/www/storage  # Persistent logs
```

### 3. HTTPS/SSL

The container is designed to work behind nginx-proxy which handles SSL. Ensure:
- nginx-proxy is configured correctly
- VIRTUAL_HOST environment variable matches your domain
- SSL certificates are configured in nginx-proxy

### 4. Resource Limits

Set appropriate resource limits:

```yaml
services:
  web:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
```

### 5. Health Checks

Add health checks to monitor container:

```yaml
services:
  web:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/api/health"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## Network Configuration

The container uses the external `nginx-proxy` network. If you need a different setup:

### Custom Network

```yaml
networks:
  notifications_network:
    driver: bridge

services:
  web:
    networks:
      - notifications_network
```

### Direct Port Mapping (Development Only)

```yaml
services:
  web:
    ports:
      - "8080:80"  # Access at http://localhost:8080
```

## Updating the Package

```bash
# Pull latest code
git pull origin main

# Rebuild container
docker-compose build --no-cache

# Update dependencies
docker exec -it notifications composer update

# Run migrations
docker exec -it notifications php artisan migrate

# Restart
docker-compose restart
```

## Complete Setup Example

Here's a complete workflow from scratch:

```bash
# 1. Clone repository
git clone https://github.com/dcplibrary/notices.git
cd notifications

# 2. Create .env file
cp .env.example .env
nano .env  # Edit with your settings

# 3. Build and start
docker-compose build
docker-compose up -d

# 4. Install dependencies
docker exec -it notifications composer install

# 5. Generate app key
docker exec -it notifications php artisan key:generate

# 6. Run migrations
docker exec -it notifications php artisan migrate

# 7. Test connections
docker exec -it notifications php artisan notices:test-connections

# 8. Import data
docker exec -it notifications php artisan notices:import --days=7
docker exec -it notifications php artisan notices:aggregate

# 9. Access dashboard
open https://notifications.dcplibrary.org
```

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [FreeTDS Documentation](https://www.freetds.org/)
- [Main Deployment Checklist](DEPLOYMENT_CHECKLIST.md)
- [SQL Server Driver Installation](SQL_SERVER_DRIVER_INSTALLATION.md)

## Support

If you encounter issues:

1. Check container logs: `docker-compose logs -f`
2. Verify driver installation: `docker exec -it notifications php -m | grep pdo`
3. Test Polaris connection: `docker exec -it notifications php artisan notices:test-connections --polaris`
4. Check Laravel logs: `docker exec -it notifications tail -f /var/www/storage/logs/laravel.log`
