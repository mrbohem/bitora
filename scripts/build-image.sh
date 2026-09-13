#!/bin/bash
set -e

echo "==================================="
echo "  Building Bitora Docker Image"
echo "==================================="
echo ""

# Configuration
DOCKER_USERNAME="${DOCKER_USERNAME:-mrbohem7}"
IMAGE_NAME="bitora"
VERSION="${2:-latest}"
FULL_IMAGE_NAME="$DOCKER_USERNAME/$IMAGE_NAME:$VERSION"
LOCAL_BUILD="false"
CLEAN_BUILDER="false"

for arg in "$@"; do
  if [[ "$arg" == "--local" ]]; then
    LOCAL_BUILD="true"
  elif [[ "$arg" == "--clean" ]]; then
    CLEAN_BUILDER="true"
  fi
done

if [[ "$CLEAN_BUILDER" == "true" ]]; then
  echo "Cleaning up stale buildx builder instance..."
  docker buildx rm -f bitora-builder 2>/dev/null || true
  docker builder prune -f 2>/dev/null || true
fi

# Step 1: Create build directory
echo "Step 1: Preparing build..."
BUILD_DIR="build/deployment"
rm -rf $BUILD_DIR
mkdir -p $BUILD_DIR

# Copy application files (no obfuscation - yakpro-po incompatible with Laravel)
echo "Copying application files..."
cp -r app $BUILD_DIR/
cp -r vendor $BUILD_DIR/
cp -r config $BUILD_DIR/
cp -r routes $BUILD_DIR/
cp -r resources $BUILD_DIR/
cp -r database $BUILD_DIR/
cp -r public $BUILD_DIR/
rm -f $BUILD_DIR/public/hot
cp -r bootstrap $BUILD_DIR/
cp composer.json composer.lock artisan .env.example $BUILD_DIR/
cp package.json package-lock.json vite.config.js $BUILD_DIR/ 2>/dev/null || true

# Create storage directories
mkdir -p $BUILD_DIR/storage/{app,framework,logs}
mkdir -p $BUILD_DIR/storage/framework/{cache,sessions,views}

echo ""

# Step 2: Build Docker image
echo "Step 2: Building Docker image..."
cd $BUILD_DIR

# Copy Docker configs
cp ../../Dockerfile.panel ./Dockerfile
cp -r ../../docker ./

# Create .env for production
cat > .env <<EOF
APP_NAME=Bitora
APP_ENV=production
APP_DEBUG=false
APP_KEY=
# ASSET_URL=/bitora

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

LOG_CHANNEL=stack
LOG_LEVEL=error
EOF

ensure_builder() {
  if ! docker buildx inspect bitora-builder >/dev/null 2>&1; then
    docker buildx create --use --name bitora-builder || true
  else
    docker buildx use bitora-builder || true
  fi
}

# Build image for local development without pushing to registry.
if [[ "$LOCAL_BUILD" == "true" ]]; then
  echo "Building local image using --load without registry push..."
  ensure_builder
  docker buildx build \
    --platform linux/amd64 \
    --provenance=false \
    -t $IMAGE_NAME:latest \
    -f Dockerfile \
    --load \
    .

  cd ../..

  echo ""
  echo "✅ Local Docker image built successfully!"
  echo "Image: $IMAGE_NAME:latest"
  echo "Platforms: linux/amd64"
  echo ""
  echo "Run locally with: docker run -d -p 8080:80 $IMAGE_NAME:latest"
  exit 0
fi

# Build multi-platform image using buildx
echo "Building for multiple platforms (linux/amd64, linux/arm64)..."
ensure_builder

# Build and push multi-platform image
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  --provenance=false \
  -t $FULL_IMAGE_NAME \
  -f Dockerfile \
  --push \
  .

cd ../..

echo ""
echo "✅ Multi-platform Docker image built and pushed successfully!"
echo "Image: $FULL_IMAGE_NAME"
echo "Platforms: linux/amd64, linux/arm64"
echo ""
echo "Next steps:"
echo "1. Test on server: Run install.sh"
echo "2. Or test locally: docker run -d -p 8080:80 $FULL_IMAGE_NAME"
