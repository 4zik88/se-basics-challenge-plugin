# WordPress with the NAASE "SE Basics Challenge" plugin baked in.
# Built for Railway (or any container host with a managed MySQL/MariaDB),
# so the plugin survives redeploys without being installed through wp-admin.
FROM wordpress:6-php8.3-apache

# Drop the plugin into the image's bundled WordPress source. The official
# entrypoint copies /usr/src/wordpress into /var/www/html on first boot, so the
# plugin lands in wp-content/plugins alongside core — no manual install needed.
COPY --chown=www-data:www-data naase-challenge \
     /usr/src/wordpress/wp-content/plugins/naase-challenge

# Force a single MPM. mod_php needs prefork; if the build ends up with both
# prefork and event/worker enabled, Apache aborts with "More than one MPM
# loaded". Disabling the others explicitly keeps the start deterministic.
# Also enable mod_rewrite for the plugin's pretty URLs
# (/naase-result/{token}/, /naase-leaderboard/).
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true; \
    a2enmod mpm_prefork rewrite

# Listen on the port the platform assigns. Railway injects $PORT; the default of
# 80 keeps `docker run -p 80:80 ...` working for a plain local build.
ENV PORT=80
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
