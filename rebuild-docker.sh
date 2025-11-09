#!/bin/bash

# Rebuild Docker Container with SQL Server Driver Fix
# This script rebuilds the Docker container with pdo_dblib and FTP extensions

echo "========================================"
echo "Step 1: Pulling latest changes..."
echo "========================================"
git pull origin claude/fix-polaris-driver-error-011CUwgvH9XFV2y7E7j6PUyP

echo ""
echo "========================================"
echo "Step 2: Stopping container..."
echo "========================================"
docker-compose down

echo ""
echo "========================================"
echo "Step 3: Building with clean Dockerfile..."
echo "========================================"
docker-compose build --no-cache

echo ""
echo "========================================"
echo "Step 4: Starting container..."
echo "========================================"
docker-compose up -d

echo ""
echo "========================================"
echo "Step 5: Verifying extensions..."
echo "========================================"
docker exec -it notifications php -m | grep -E "pdo_dblib|ftp"

echo ""
echo "========================================"
echo "Step 6: Testing connections..."
echo "========================================"
docker exec -it notifications php artisan notifications:test-connections

echo ""
echo "========================================"
echo "Done! Check results above."
echo "========================================"
