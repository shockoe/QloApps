#!/bin/bash

echo "Starting QloApps in Development Mode with Hot Reloading..."
echo "========================================================="
echo ""
echo "This will:"
echo "- Mount your local source code into the container"
echo "- Enable hot reloading (changes reflect immediately)"
echo "- Enable PHP error display and disable opcache"
echo "- Use separate volumes for dev environment"
echo ""

# Stop any existing containers
echo "Stopping existing containers..."
docker-compose -f docker-compose.dev.yml down

# Build and start development containers
echo "Building and starting development containers..."
docker-compose -f docker-compose.dev.yml up --build -d

echo ""
echo "Development environment is starting up..."
echo "Web Interface: http://localhost:8080"
echo "MySQL Port: 3307"
echo ""
echo "To view logs: docker-compose -f docker-compose.dev.yml logs -f"
echo "To stop: docker-compose -f docker-compose.dev.yml down"
echo ""
echo "Your local source code changes will be reflected immediately!"