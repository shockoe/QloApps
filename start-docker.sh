#!/bin/bash

# QloApps Docker Startup Script
# This script builds and starts the QloApps hotel booking system with MySQL database

echo "🚀 Starting QloApps Hotel Booking System..."
echo "================================================"

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop first."
    exit 1
fi

# Navigate to the script directory
cd "$(dirname "$0")"

# Stop any existing containers first
echo "🛑 Stopping any existing containers..."
docker compose down 2>/dev/null

# Build the image with latest code changes (no cache)
echo "🔨 Building QloApps image with latest code changes..."
echo "   (This may take a few minutes on first run or after major changes)"
docker compose build --no-cache qloapps

if [ $? -ne 0 ]; then
    echo "❌ Docker build failed. Please check the error messages above."
    exit 1
fi

# Start the services
echo "📦 Starting Docker containers..."
docker compose up -d

if [ $? -ne 0 ]; then
    echo "❌ Failed to start containers. Please check the error messages above."
    exit 1
fi

# Wait a moment for services to start
echo "⏳ Waiting for services to initialize..."
sleep 15

# Check status
echo "📊 Checking service status..."
docker compose ps

# Test web service accessibility
echo "🌐 Testing web service accessibility..."
if curl -f -s http://localhost:8080 > /dev/null 2>&1; then
    echo "✅ Web service is responding"
else
    echo "⚠️  Web service may still be starting up"
fi

echo ""
echo "✅ QloApps Services Started!"
echo "================================================"
echo "🌐 Web Interface: http://localhost:8080"
echo "📝 Installation: http://localhost:8080/install/"
echo "🗄️  MySQL Database: localhost:3307"
echo "   - Database: qloapps_hotels"
echo "   - Username: qloapps"
echo "   - Password: qloapps123"
echo "   - Root Password: qloapps_root"
echo "🔧 SSH Access: localhost:2222 (user: qloapps)"
echo ""
echo "📋 Next Steps:"
echo "   1. Go to http://localhost:8080/install/"
echo "   2. Follow the installation wizard"
echo "   3. Use database settings shown above"
echo ""
echo "📝 Useful Commands:"
echo "   - View logs: docker compose logs -f"
echo "   - Stop services: ./stop-docker.sh"
echo "   - Rebuild only: docker compose build --no-cache qloapps"
echo "================================================"