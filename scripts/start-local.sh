#!/bin/bash
set -e

echo "Building local Bitora image..."
./scripts/build-image.sh --local

echo ""
echo "Starting Bitora locally..."

# Start Traefik, which creates the shared network when it is missing.
docker compose -f docker/traefik/docker-compose.yml up -d

# Stop and remove existing container
docker stop bitora-panel 2>/dev/null || true
docker rm -f bitora-panel 2>/dev/null || true

# Start fresh container with all required mounts
docker run -d \
  --name bitora-panel \
  --restart unless-stopped \
  --network traefik-network \
  -e DOCKER_API_VERSION=1.40 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -p 8080:80 \
  bitora:latest

sleep 3

# Setup
docker exec bitora-panel php artisan key:generate --force
docker exec bitora-panel php artisan migrate --force

echo ""
echo "✅ Bitora started successfully!"
echo ""
echo "Access: http://localhost:8080"
echo ""
