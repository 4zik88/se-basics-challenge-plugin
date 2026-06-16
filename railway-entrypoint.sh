#!/usr/bin/env bash
# Startup wrapper for the WordPress image on Railway.
#
# Apache allows exactly one MPM. mod_php requires prefork. On Railway the
# container has been aborting with "AH00534: More than one MPM loaded", which
# means a second MPM (event/worker) is active at runtime even though the image
# ships with prefork only. Force a clean, single-MPM state right before Apache
# starts, then hand off to the stock WordPress entrypoint so its setup
# (core copy, wp-config generation) still runs.
set -e

echo "[railway-entrypoint] MPMs enabled at start: $(ls /etc/apache2/mods-enabled/ 2>/dev/null | grep -i mpm | tr '\n' ' ')"

# Keep prefork only.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "[railway-entrypoint] MPMs after fix:    $(ls /etc/apache2/mods-enabled/ 2>/dev/null | grep -i mpm | tr '\n' ' ')"

# Hand off to the official WordPress entrypoint (runs WP setup, then exec CMD).
exec docker-entrypoint.sh "$@"
