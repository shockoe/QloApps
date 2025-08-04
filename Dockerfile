FROM ubuntu:22.04
LABEL maintainer="QloApps Support <support@qloapps.com>"
ARG user=qloapps

# PHP file configuration with php version and mysql version
ENV mysql_version=5.7 php_version=8.1 file_uploads=On allow_url_fopen=On memory_limit=512M max_execution_time=500 upload_max_filesize=16M post_max_size=32M max_input_vars=1500

# Update server and install lamp server
RUN apt-get update \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get -y install apache2 \
    && a2enmod rewrite \
    && a2enmod headers \
    && export LANG=en_US.UTF-8 \
    && apt-get update \
    && apt-get install -y php$php_version libapache2-mod-php$php_version php$php_version-bcmath php$php_version-cli php$php_version-curl php$php_version-fpm php$php_version-gd php$php_version-ldap php$php_version-mbstring php$php_version-mysql php$php_version-soap php$php_version-sqlite3 php$php_version-xml php$php_version-zip php$php_version-intl php-imagick \
    && echo "date.timezone = UTC" >> /etc/php/$php_version/apache2/php.ini \
    && sed -i -e 's/memory_limit = .*/memory_limit = '${memory_limit}'/' -e 's/file_uploads = .*/file_uploads = '${file_uploads}'/' -e 's/allow_url_fopen = .*/allow_url_fopen = '${allow_url_fopen}'/' -e 's/max_execution_time = .*/max_execution_time = '${max_execution_time}'/' -e 's/upload_max_filesize = .*/upload_max_filesize = '${upload_max_filesize}'/' -e 's/post_max_size = .*/post_max_size = '${post_max_size}'/' -e 's/max_input_vars = .*/max_input_vars = '${max_input_vars}'/' /etc/php/$php_version/apache2/php.ini \
    && apt-get install -y git nano vim curl openssh-server supervisor \
    && mkdir -p /var/log/supervisor

# Setup non root user
RUN useradd -m -s /bin/bash ${user} \
    && mkdir -p /home/${user}/www/hotelcommerce

# Change file permission and ownership setup
RUN sed -i "s@www-data@${user}@g" /etc/apache2/envvars \
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

# Copy the current project files (with our custom changes including the new availability API)
COPY . /home/${user}/www/hotelcommerce

# Set proper permissions for the copied files and create necessary directories
RUN find /home/${user}/www -type f -exec chmod 644 {} \; \
    && find /home/${user}/www -type d -exec chmod 755 {} \; \
    && chown -R ${user}: /home/${user}/www \
    && mkdir -p /home/${user}/www/hotelcommerce/cache/smarty/compile \
    && mkdir -p /home/${user}/www/hotelcommerce/cache/cachefs \
    && chmod -R 755 /home/${user}/www/hotelcommerce/cache \
    && chmod -R 755 /home/${user}/www/hotelcommerce/config \
    && chmod -R 755 /home/${user}/www/hotelcommerce/log \
    && chmod -R 755 /home/${user}/www/hotelcommerce/upload \
    && chmod -R 755 /home/${user}/www/hotelcommerce/download \
    && chown -R ${user}: /home/${user}/www/hotelcommerce

WORKDIR /home/${user}/www/hotelcommerce
EXPOSE 80 443 22
CMD ["/usr/bin/supervisord"]