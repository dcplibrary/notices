# Docker Quick Start

## 🚀 Get Running in 5 Minutes

```bash
# 1. Build container (includes SQL Server driver)
docker-compose build

# 2. Start container
docker-compose up -d

# 3. Copy and configure .env
cp .env.example .env
nano .env  # Set POLARIS_DB_DRIVER=dblib and your credentials

# 4. Install dependencies
docker exec -it notifications composer install

# 5. Run migrations
docker exec -it notifications php artisan migrate

# 6. Test Polaris connection
docker exec -it notifications php artisan notifications:test-connections --polaris

# ✅ You should see: "Polaris connection successful"
```

## 📋 Essential Commands

```bash
# View logs
docker-compose logs -f

# Enter container
docker exec -it notifications bash

# Import data
docker exec -it notifications php artisan notifications:import-notifications --days=7

# Stop container
docker-compose down

# Rebuild (after code changes)
docker-compose build --no-cache && docker-compose up -d
```

## ✅ What's Pre-Installed

- ✅ PHP 8.3 Apache
- ✅ **FreeTDS SQL Server driver (pdo_dblib)**
- ✅ All PHP extensions (mysql, pgsql, zip, intl)
- ✅ Composer
- ✅ Node.js 20

## 📚 Full Documentation

See **[docs/DOCKER_SETUP.md](docs/DOCKER_SETUP.md)** for complete guide.

## 🐛 Troubleshooting

**Driver still not found?**
```bash
# Verify driver is installed
docker exec -it notifications php -m | grep pdo_dblib

# Should output: pdo_dblib
```

**Can't connect to Polaris?**
```bash
# Check .env file
docker exec -it notifications cat .env | grep POLARIS

# Make sure POLARIS_DB_DRIVER=dblib (not sqlsrv)
```

**Need to rebuild?**
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```
