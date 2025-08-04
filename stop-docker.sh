#!/bin/bash

# QloApps Docker Shutdown Script
# This script stops the QloApps hotel booking system and MySQL database

echo "🛑 Stopping QloApps Hotel Booking System..."
echo "================================================"

# Navigate to the script directory
cd "$(dirname "$0")"

# Stop the services
echo "📦 Stopping Docker containers..."
docker compose down

echo ""
echo "✅ QloApps Services Stopped!"
echo "================================================"
echo "📊 All containers and networks have been removed."
echo "💾 Data volumes are preserved for next startup."
echo ""
echo "🚀 To start services again: ./start-docker.sh"
echo "🔍 To view remaining containers: docker ps -a"
echo "================================================"