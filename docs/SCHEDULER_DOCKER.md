# Scheduler Docker Setup (Laravel `schedule:work`)

**Package:** `dcplibrary/notices`  
**Last Updated:** November 25, 2025

This document explains how to run the Laravel scheduler for this package using a dedicated Docker container.

The goal is:

- Run all scheduled jobs (import, sync-from-logs, aggregation, etc.) without relying on the host machine's cron.
- Keep all time-based behavior inside Docker, with a single long-running scheduler worker.

---

## 1. Scheduler Service in `docker-compose.yml`

Example service definition:

```yaml
your_app_services...

scheduler:
  container_name: notices-scheduler
  build:
    context: .
    dockerfile: .docker/Dockerfile
  environment:
    TZ: America/Chicago
  volumes:
    - .:/var/www
    - /usr/share/zoneinfo/America/Chicago:/etc/localtime:ro
    - /etc/timezone:/etc/timezone:ro
  networks:
    - notices-net
  depends_on:
    - db
  restart: always
  command: php /var/www/artisan schedule:work
```

### Requirements

- The Dockerfile used for this service **must** set `WORKDIR /var/www` and have PHP + composer dependencies installed.
- The `.` volume mount must include:
  - The Laravel app code
  - `.env` file (so scheduler can read DB, Polaris, Shoutbomb config)
- The `db` service (and any other dependencies) must be reachable on the `notices-net` network.

---

## 2. Build and Start the Scheduler

From the project root:

```bash
# Build the scheduler image
 docker compose build scheduler

# Start scheduler in the background
 docker compose up -d scheduler
```

Check status:

```bash
 docker compose ps scheduler
```

You should see the container `UP` with the command `php /var/www/artisan schedule:work`.

---

## 3. Verify Scheduled Tasks Are Registered

Exec into the scheduler container and list the schedule:

```bash
 docker compose exec scheduler php artisan schedule:list
```

You should see entries for all the tasks registered in `NoticesServiceProvider::registerScheduledTasks()`, e.g.

- `notices:import-ftp-files ...`
- `notices:import-email-reports ...`
- `notices:import-polaris --days=1`
- `notices:sync-from-logs --days=1` (21:45)
- `notices:aggregate --yesterday` (22:00)

If they do not show up:

1. Ensure the package service provider is loaded (autoload/composer dump-autoload).
2. Clear caches inside the app container if needed:

   ```bash
   docker compose exec scheduler php artisan config:clear
   docker compose exec scheduler php artisan cache:clear
   ```

3. Restart the scheduler container:

   ```bash
   docker compose restart scheduler
   ```

---

## 4. Confirm Jobs Are Running

Tail the scheduler logs:

```bash
 docker compose logs -f scheduler
```

On the minute marks you should see messages like:

- `Running scheduled command: "notices:import-ftp-files ..."`
- `Running scheduled command: "notices:sync-from-logs --days=1"`
- `Running scheduled command: "notices:aggregate --yesterday"`

To test a command immediately without waiting for its time window:

```bash
 docker compose exec scheduler php artisan notices:sync-from-logs --days=1
```

If that runs successfully and `notifications` / `notification_events` get rows, the scheduler will be able to run it as part of the normal schedule.

---

## 5. Timezone Configuration

This package’s schedule in `NoticesServiceProvider` assumes **America/Chicago**.

Make sure:

1. Container has the correct timezone environment:

   ```yaml
   environment:
     TZ: America/Chicago
   volumes:
     - /usr/share/zoneinfo/America/Chicago:/etc/localtime:ro
     - /etc/timezone:/etc/timezone:ro
   ```

2. Laravel is configured with the same timezone in `.env`:

   ```env
   APP_TIMEZONE=America/Chicago
   ```

3. In `config/app.php` the `timezone` entry should read:

   ```php
   'timezone' => env('APP_TIMEZONE', 'UTC'),
   ```

With this, calls like `dailyAt('21:45')` in the scheduler will line up with local time.

---

## 6. When You Change the Schedule

If you edit any schedule logic (e.g. `NoticesServiceProvider::registerScheduledTasks()`):

1. Rebuild or at least restart the scheduler container so the new code is picked up:

   ```bash
   docker compose restart scheduler
   ```

2. Re-run `schedule:list` inside the container to confirm the new entries.

---

## 7. Minimal Health Checklist

Consider the scheduler “healthy” when:

- `docker compose ps scheduler` shows the container `UP`.
- `php artisan schedule:list` inside the container lists the expected `notices:*` commands with correct times.
- `docker compose logs -f scheduler` shows jobs firing at expected times.
- You can see data changing as a result of those jobs, e.g.:
  - `notification_logs` receiving new imports.
  - `notifications` / `notification_events` being populated by `notices:sync-from-logs`.
  - `daily_notification_summary` rows being updated by `notices:aggregate`.

If any of those conditions fail, check:

- Logs in `storage/logs/laravel.log` (via the app container or scheduler container).
- Database connectivity (DB container status, credentials in `.env`).
- That migrations have been run (`php artisan migrate`).

---

## 8. Quick Commands Reference

```bash
# Build and start scheduler
 docker compose build scheduler
 docker compose up -d scheduler

# See current scheduled tasks
 docker compose exec scheduler php artisan schedule:list

# Watch scheduler logs
 docker compose logs -f scheduler

# Run a scheduled command manually
 docker compose exec scheduler php artisan notices:sync-from-logs --days=1

# Restart scheduler after code changes
 docker compose restart scheduler
```