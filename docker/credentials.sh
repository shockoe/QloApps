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

# Function to create security index.php files for runtime-created directories
create_security_files() {
    local base_dir="/home/$user/www/hotelcommerce"
    
    # Define directories that need security index.php files
    local security_dirs=(
        "cache/smarty/compile"
        "cache/smarty/cache"
        # Add any other runtime-created directories that need security files here
    )
    
    for dir in "${security_dirs[@]}"; do
        local target_dir="$base_dir/$dir"
        local security_file="$target_dir/index.php"
        
        # Create directory if it doesn't exist
        mkdir -p "$target_dir"
        
        # Create security file if missing
        if [[ ! -f "$security_file" ]]; then
            # Calculate correct redirect depth based on directory nesting
            local depth=$(echo "$dir" | tr -cd '/' | wc -c)
            local redirect_path=""
            for ((i=0; i<=depth; i++)); do
                redirect_path="../$redirect_path"
            done
            
            # Create security file with proper redirect and PrestaShop license header
            cat > "$security_file" << EOF
<?php
/*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

header('Location: $redirect_path');
exit;
EOF
            
            # Set proper ownership and permissions
            chown "$user":"$user" "$security_file"
            chmod 644 "$security_file"
            
            echo "Created security file: $security_file"
        fi
    done
}

# Create missing security index.php files
create_security_files


# Stop this script from running again via supervisor.
supervisorctl stop update_credentials && supervisorctl remove update_credentials