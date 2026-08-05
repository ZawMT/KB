#!/bin/sh
#
# Container entrypoint for the Laravel app image.
# Runs on every container start, before the main process (php-fpm, a queue
# worker, or the scheduler) takes over.

set -e

# ---------------------------------------------------------------------------
# Writable directories
# ---------------------------------------------------------------------------
# The image ships these, but a mounted volume can shadow them with an empty
# directory. Recreating them is cheap and avoids a first-request crash.
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# ---------------------------------------------------------------------------
# Caches
# ---------------------------------------------------------------------------
# Built at container START, not at image BUILD time — and this distinction
# matters. `config:cache` freezes the values of every env() call into a single
# PHP file. Doing that during the build would bake in whatever environment the
# build machine had (usually nothing), and the running container would then use
# those empty values instead of its real DB credentials.
#
# Same image, different environment (staging vs production) is only possible
# because this happens here.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ---------------------------------------------------------------------------
# What deliberately does NOT happen here
# ---------------------------------------------------------------------------
# Migrations. It is tempting to add `php artisan migrate --force` above, and it
# works fine with exactly one container. With two or more replicas starting
# together, they race: several processes run the same migration at once and the
# schema ends up half-applied or the deploy fails outright. Worse, every
# restart of any container would attempt them.
#
# Migrations are a deploy step, run once, as a separate one-shot command:
#   docker compose run --rm app php artisan migrate --force
#
# `storage:link` is likewise absent — with uploads on S3 there is nothing to
# link, and on a container filesystem the symlink would not survive a restart.

# Hand control to the container's main process (CMD, or the `command:` in
# compose). `exec` replaces this shell, so the app becomes PID 1 and receives
# SIGTERM directly — which is what lets a queue worker shut down gracefully
# instead of being killed mid-job.
exec "$@"
