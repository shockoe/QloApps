#!/bin/bash

# QloApps Docker Quick Restart Script
# This script quickly restarts services without rebuilding (faster for minor changes)

echo "🔄 Quick Restart QloApps Services..."
echo "================================================"

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop first."
    exit 1
fi

# Navigate to the script directory
cd "$(dirname "$0")"

# Restart the services
echo "🔄 Restarting Docker containers..."
docker compose restart

if [ $? -ne 0 ]; then
    echo "❌ Failed to restart containers. Please check the error messages above."
    exit 1
fi

# Wait a moment for services to start
echo "⏳ Waiting for services to initialize..."
sleep 10

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
echo "✅ QloApps Services Restarted!"
echo "================================================"
echo "🌐 Web Interface: http://localhost:8080"
echo "📝 Installation: http://localhost:8080/install/"
echo ""
echo "💡 Note: This was a quick restart. For code changes, use:"
echo "   ./start-docker.sh (full rebuild with latest changes)"
echo "================================================"