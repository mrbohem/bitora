#!/bin/bash
set -e

echo "==================================="
echo "  Obfuscating Bitora Code"
echo "==================================="
echo ""

SOURCE_DIR="$(pwd)"
OBFUSCATED_DIR="$(pwd)/build/obfuscated"
YAKPRO_DIR="$(pwd)/tools/yakpro-po"

# Clean previous build
echo "Cleaning previous build..."
rm -rf build/obfuscated
mkdir -p build/obfuscated

# Check if yakpro-po exists
if [ ! -d "$YAKPRO_DIR" ]; then
    echo "Yakpro-Po not found. Running setup..."
    bash scripts/setup-obfuscator.sh
fi

echo "Starting obfuscation..."
echo "This may take a few minutes..."
echo ""

# Create temporary output directory
TEMP_OUTPUT="$OBFUSCATED_DIR/app-obfuscated-temp"
rm -rf "$TEMP_OUTPUT"

# Run obfuscation
php $YAKPRO_DIR/yakpro-po.php \
    --config-file $YAKPRO_DIR/yakpro-po.cnf \
    "$SOURCE_DIR/app" \
    -o "$TEMP_OUTPUT"

# Move obfuscated files to correct location
echo "Organizing obfuscated files..."
if [ -d "$TEMP_OUTPUT/obfuscated" ]; then
    mv "$TEMP_OUTPUT/obfuscated" "$OBFUSCATED_DIR/app"
elif [ -d "$TEMP_OUTPUT/yakpro-po" ]; then
    mv "$TEMP_OUTPUT/yakpro-po" "$OBFUSCATED_DIR/app"
else
    mv "$TEMP_OUTPUT" "$OBFUSCATED_DIR/app"
fi

# Cleanup
rm -rf "$TEMP_OUTPUT"

# Verify critical directories exist
echo "Verifying app structure..."
for dir in Providers Models Http Console; do
    if [ ! -d "$OBFUSCATED_DIR/app/$dir" ]; then
        echo "⚠️  Warning: app/$dir is missing after obfuscation"
        echo "Copying from source..."
        cp -r "$SOURCE_DIR/app/$dir" "$OBFUSCATED_DIR/app/"
    fi
done

# Copy non-obfuscated files
echo ""
echo "Copying non-obfuscated files..."

# Copy vendor (don't obfuscate dependencies)
cp -r vendor $OBFUSCATED_DIR/

# Copy config, routes, resources
cp -r config $OBFUSCATED_DIR/
cp -r routes $OBFUSCATED_DIR/
cp -r resources $OBFUSCATED_DIR/
cp -r database $OBFUSCATED_DIR/
cp -r public $OBFUSCATED_DIR/
cp -r bootstrap $OBFUSCATED_DIR/

# Copy root files
cp composer.json $OBFUSCATED_DIR/
cp composer.lock $OBFUSCATED_DIR/
cp artisan $OBFUSCATED_DIR/
cp .env.example $OBFUSCATED_DIR/

# Create storage directories
mkdir -p $OBFUSCATED_DIR/storage/{app,framework,logs}
mkdir -p $OBFUSCATED_DIR/storage/framework/{cache,sessions,views}

echo ""
echo "✅ Obfuscation complete!"
echo "Obfuscated code at: $OBFUSCATED_DIR"
