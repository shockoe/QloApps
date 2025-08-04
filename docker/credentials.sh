#!/bin/bash
set -eu

# Set the password for the application user.
# Note: In a production environment, consider more secure methods for user management.
if [ -n "${USER_PASSWORD-}" ]; then
    echo -e "$USER_PASSWORD\n$USER_PASSWORD" | passwd "$user"
fi

# Set ownership of Prestashop directories to the web user.
# This is necessary for the application to write to these directories at runtime.
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/config
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/log
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/img
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/mails
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/modules
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/themes
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/translations
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/upload
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/download
chown -R "$user":"$user" /home/"$user"/www/hotelcommerce/cache

# Stop this script from running again via supervisor.
supervisorctl stop update_credentials && supervisorctl remove update_credentials