# WordPress with the NAASE "SE Basics Challenge" plugin baked in.
# Built for Railway (or any container host with a managed MySQL/MariaDB),
# so the plugin survives redeploys without being installed through wp-admin.
FROM wordpress:6-php8.3-apache

# Drop the plugin into the image's bundled WordPress source. The official
# entrypoint copies /usr/src/wordpress into /var/www/html on first boot, so the
# plugin lands in wp-content/plugins alongside core — no manual install needed.
COPY --chown=www-data:www-data naase-challenge \
     /usr/src/wordpress/wp-content/plugins/naase-challenge

# Pretty URLs (/naase-result/{token}/, /naase-leaderboard/) need mod_rewrite.
# It ships enabled in the official image; enable explicitly to be safe.
RUN a2enmod rewrite

# Listen on the port the platform assigns. Railway injects $PORT; the default of
# 80 keeps `docker run -p 80:80 ...` working for a plain local build.
ENV PORT=80
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
