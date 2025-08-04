#!/bin/bash

echo "Stopping QloApps Development Environment..."
echo "==========================================="

docker-compose -f docker-compose.dev.yml down

echo ""
echo "Development environment stopped."
echo "To restart: ./start-dev.sh"