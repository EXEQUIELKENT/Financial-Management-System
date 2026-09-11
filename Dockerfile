# Financial Management System - production image.
# Plain PHP + Apache serving the application from the document root.
#
# This build deliberately depends on nothing but the base image: no apt layer, no
# Composer, no second image pull, no package registry. The application has no
# third-party runtime dependencies, so each of those was a network round-trip that
# could fail the build without buying anything. composer.json stays in the repository
# as an accurate manifest of the PHP version and extensions required, but nothing in
# the image needs to run Composer to produce it.

FROM php:8.2-apache

# APP_ENV=production keeps PHP errors out of responses (see config/config.php).
# PORT is the port Apache binds to; the entrypoint rewrites the Apache config from it.
ENV APP_ENV=production \
    PORT=80

# pdo_mysql is the only database driver the app uses (it is PDO throughout), and it
# pulls in pdo itself. opcache is the one meaningful performance win. Both are installed
# via mlocati/docker-php-extension-installer, which fetches prebuilt binaries instead of
# compiling from source — compiling opcache's JIT support from scratch was taking over
# 20 minutes in HostForge's build environment and timing out the deployment.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN set -eux; \
install-php-extensions pdo_mysql opcache; \
a2enmod rewrite headers remoteip expires

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html/

RUN set -eux; \
    mkdir -p /var/www/sessions; \
    chown -R www-data:www-data /var/www/html /var/www/sessions; \
    chmod 1733 /var/www/sessions

EXPOSE 80

# Liveness only: /health answers 200 while the database is still coming up and reports
# the database state in its body, so a database blip cannot crash-loop the container.
# The probe uses PHP's own sockets, which is why this image needs no curl.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php /var/www/html/scripts/healthcheck.php /health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
