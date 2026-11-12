# Docker Setup Guide (Dev and Prod Examples)

This package is usually run inside a host Laravel application.  
The files in `docs/deployment/examples/` are copy-ready templates you can use as a starting point.

## Included templates

- `examples/docker-compose.dev.example.yml`
- `examples/docker-compose.prod.example.yml`
- `examples/Dockerfile.dev.example`
- `examples/Dockerfile.prod.example`
- `examples/000-default.nginx-proxy.example.conf`
- `examples/my.cnf.example`
- `examples/env.docker.example`

## Why these templates

These examples include practical performance defaults:

- Redis for cache and queues
- dedicated scheduler and queue worker containers
- OPcache tuned separately for dev and prod
- MySQL slow query logging for tuning
- health checks for better startup behavior

## Quick start (dev)

No reverse proxy is required for local development.

1. Copy files into your host app:

```bash
mkdir -p .docker/mysql/logs
cp docs/deployment/examples/docker-compose.dev.example.yml docker-compose.yml
cp docs/deployment/examples/Dockerfile.dev.example .docker/Dockerfile
cp docs/deployment/examples/my.cnf.example .docker/mysql/my.cnf
cp docs/deployment/examples/env.docker.example .env.docker
```

2. Merge `.env.docker` values into your `.env` (proxy variables can be ignored locally).
3. Build and start:

```bash
docker compose up -d --build
```

## Quick start (prod-like)

1. Use the prod templates:

```bash
mkdir -p .docker/mysql/logs
mkdir -p .docker/sites-enabled
cp docs/deployment/examples/docker-compose.prod.example.yml docker-compose.yml
cp docs/deployment/examples/Dockerfile.prod.example .docker/Dockerfile
cp docs/deployment/examples/000-default.nginx-proxy.example.conf .docker/sites-enabled/000-default.conf
cp docs/deployment/examples/my.cnf.example .docker/mysql/my.cnf
```

2. Build and push your app image.
3. Set `NOTICES_APP_IMAGE` in deployment environment.
4. Deploy with `docker compose up -d`.

## Required environment values

At minimum:

- `APP_KEY`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST`, `REDIS_PORT`

For package functionality, also provide your notices-related values (`POLARIS_FTP_*`, `MICROSOFT_GRAPH_*`, etc.).

## Using nginx-proxy + Let's Encrypt

If your stack uses `jwilder/nginx-proxy` and Let's Encrypt companion:

- keep Apache listening on port `80` inside the app container
- use `VIRTUAL_HOST`, `VIRTUAL_PORT`, `VIRTUAL_PROTO`, `LETSENCRYPT_HOST`, and `LETSENCRYPT_EMAIL`
- join the external `nginx-proxy` Docker network
- mount your Apache vhost config into `/etc/apache2/sites-enabled`

The prod compose example and Apache vhost template are already wired for this pattern.

### Dynamic proxy behavior contract (important)

`jwilder/nginx-proxy` discovers backends dynamically from Docker metadata on
the shared network. To preserve that behavior:

1. **Do not** create static host mappings inside nginx-proxy for app containers.
2. Put only the **web** container on the `nginx-proxy` network.
3. Set `VIRTUAL_HOST` on the **web** container (not scheduler/worker/db/redis).
4. Use `expose: 80` on web; do not publish app container `80/443` ports.
5. Keep nginx-proxy mounted to Docker socket (`/var/run/docker.sock:/tmp/docker.sock:ro`).

If your proxy stack already runs from a separate Compose file (including legacy
`version: '2'`), keep it as-is. The app compose template only needs to join the
same external network name (`nginx-proxy`) and set the environment variables above.

## Performance defaults you should keep

1. **Redis cache + queue**
   - `CACHE_DRIVER=redis`
   - `QUEUE_CONNECTION=redis`

2. **Dedicated queue worker**
   - Run `php artisan queue:work` in a separate container.

3. **OPcache enabled**
   - Dev: `opcache.validate_timestamps=1`
   - Prod: `opcache.validate_timestamps=0`

4. **MySQL slow query log**
   - Enable in `my.cnf`:
     - `slow_query_log=1`
     - `long_query_time=0.2`

5. **Health checks**
   - Add container health checks for web, db, and redis.

## Optional tooling (recommended)

- **Blackfire** (profiling in staging/prod-like)
- **pt-query-digest** (analyze MySQL slow logs)
- **Horizon** (if you want Laravel queue UI and metrics)
- **k6** (load testing)

### Analyze slow queries quickly

If your DB container writes slow logs to `./.docker/mysql/logs/slow.log`, you can inspect top offenders with:

```bash
pt-query-digest ./.docker/mysql/logs/slow.log
```

## Notes

- These are templates, not strict requirements.
- Use bind mounts in dev only. For prod, prefer immutable images.
- Keep Xdebug disabled unless actively debugging.
