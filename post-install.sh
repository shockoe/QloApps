#!/bin/bash
set -e

# This script is designed to be run from your host machine.
# It executes the necessary post-installation commands inside the running qloapps container.

# Check if dev suffix is provided as an argument
if [ "$1" = "dev" ]; then
    CONTAINER_SUFFIX="-dev"
else
    CONTAINER_SUFFIX=""
fi

CONTAINER_NAME="qloapps-booking-system${CONTAINER_SUFFIX}"
APP_DIR="/home/qloapps/www/hotelcommerce"

# Check if the container is running
if ! docker ps --filter "name=${CONTAINER_NAME}" --format "{{.Names}}" | grep -q "${CONTAINER_NAME}"; then
    echo "Error: The container '${CONTAINER_NAME}' is not running."
    echo "Please start your environment with 'docker-compose up' before running this script."
    exit 1
fi

echo "Performing post-installation security steps on container '${CONTAINER_NAME}'..."

# 1. Delete the /install folder inside the container
echo "Deleting the installation directory..."
docker exec "${CONTAINER_NAME}" rm -rf "${APP_DIR}/install"
echo "Successfully deleted the /install directory."

# 2. Rename the /admin folder inside the container
ADMIN_DIR_PATH="${APP_DIR}/admin"

# Check if the admin directory exists before trying to rename it
if docker exec "${CONTAINER_NAME}" [ -d "${ADMIN_DIR_PATH}" ]; then
    RANDOM_SUFFIX="_shockoe"
    NEW_ADMIN_NAME="admin${RANDOM_SUFFIX}"
    NEW_ADMIN_PATH="${APP_DIR}/${NEW_ADMIN_NAME}"

    echo "Renaming the admin directory..."
    docker exec "${CONTAINER_NAME}" mv "${ADMIN_DIR_PATH}" "${NEW_ADMIN_PATH}"

    echo "--------------------------------------------------"
    echo "Admin directory successfully renamed!"
    echo "Your new back office URL is: http://localhost:8080/${NEW_ADMIN_NAME}"
    echo "--------------------------------------------------"
else
    echo "Admin directory not found. It may have been renamed already."
fi

echo "Post-installation security steps completed."
