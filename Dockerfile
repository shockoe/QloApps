#
# BUILDER STAGE
#
FROM ubuntu:22.04 AS builder

LABEL maintainer="QloApps Support <support@qloapps.com>"
ARG user=qloapps

# Copy the application files first to leverage Docker build cache
WORKDIR /home/${user}/www/hotelcommerce
COPY . .

#
# FINAL IMAGE
#
FROM ubuntu:22.04

LABEL maintainer="QloApps Support <support@qloapps.com>"
ARG user=qloapps

# PHP file configuration with php version
ENV php_version=8.1 file_uploads=On allow_url_fopen=On memory_limit=512M max_execution_time=500 upload_max_filesize=16M post_max_size=32M max_input_vars=1500

# Install only necessary runtime dependencies and clean up in the same layer
RUN apt-get update \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php$php_version \
        php$php_version-bcmath \
        php$php_version-cli \
        php$php_version-curl \
        php$php_version-fpm \
        php$php_version-gd \
        php$php_version-ldap \
        php$php_version-mbstring \
        php$php_version-mysql \
        php$php_version-soap \
        php$php_version-sqlite3 \
        php$php_version-xml \
        php$php_version-zip \
        php$php_version-intl \
        php-imagick \
        supervisor \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP
RUN echo "date.timezone = UTC" >> /etc/php/$php_version/apache2/php.ini \
    && sed -i -e 's/memory_limit = .*/memory_limit = '${memory_limit}'/' \
       -e 's/file_uploads = .*/file_uploads = '${file_uploads}'/' \
       -e 's/allow_url_fopen = .*/allow_url_fopen = '${allow_url_fopen}'/' \
       -e 's/max_execution_time = .*/max_execution_time = '${max_execution_time}'/' \
       -e 's/upload_max_filesize = .*/upload_max_filesize = '${upload_max_filesize}'/' \
       -e 's/post_max_size = .*/post_max_size = '${post_max_size}'/' \
       -e 's/max_input_vars = .*/max_input_vars = '${max_input_vars}'/' /etc/php/$php_version/apache2/php.ini

# Setup non-root user
RUN useradd -m -s /bin/bash ${user} \
    && mkdir -p /var/log/supervisor /home/${user}/www/hotelcommerce

# Configure Apache
RUN a2enmod rewrite headers \
    && sed -i "s@www-data@${user}@g" /etc/apache2/envvars \
    && echo ' <Directory /home/> \n\
                Options FollowSymLinks \n\
                Require all granted  \n\
                AllowOverride all \n\
                </Directory>  ' >> /etc/apache2/apache2.conf \
    && sed -i "s@/var/www/html@/home/${user}/www/hotelcommerce@g" /etc/apache2/sites-enabled/000-default.conf

# Copy supervisord configuration and credentials script
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/credentials.sh /etc/credentials.sh
RUN chmod a+x /etc/credentials.sh

# Copy application files from the builder stage
COPY --from=builder /home/${user}/www/hotelcommerce /home/${user}/www/hotelcommerce

# Set initial ownership. Runtime permissions are handled in credentials.sh
RUN chown -R ${user}:${user} /home/${user}/www

WORKDIR /home/${user}/www/hotelcommerce
EXPOSE 80

CMD ["/usr/bin/supervisord"]