#!/bin/bash

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Bitora Installation Script${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Detect if running as root (for conditional sudo usage)
if [ "$EUID" -eq 0 ]; then
    SUDO_CMD=""
else
    SUDO_CMD="sudo"
fi

# Detect OS
OS="$(uname -s)"
case "${OS}" in
    Linux*)     MACHINE=Linux;;
    Darwin*)    MACHINE=Mac;;
    *)          MACHINE="UNKNOWN:${OS}"
esac

echo -e "${YELLOW}Detected OS: ${MACHINE}${NC}"
echo ""

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Return success only if the given TCP port is already occupied.
port_in_use() {
    local port="$1"

    if command_exists python3; then
        python3 - "$port" <<'PY' >/dev/null 2>&1
import socket
import sys

port = int(sys.argv[1])
sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
sock.settimeout(1)
try:
    sock.bind(("0.0.0.0", port))
except OSError:
    sys.exit(0)
finally:
    sock.close()

sys.exit(1)
PY
        return $?
    fi

    if command_exists lsof; then
        lsof -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
        return $?
    fi

    if command_exists ss; then
        ss -ltn 2>/dev/null | awk '{print $4}' | grep -Eq ":${port}$"
        return $?
    fi

    return 1
}

choose_available_port() {
    local preferred_port="$1"
    local candidate_port="$preferred_port"

    while port_in_use "$candidate_port"; do
        echo -e "${YELLOW}Port ${candidate_port} is already in use. Trying next port...${NC}"
        candidate_port=$((candidate_port + 1))
    done

    BITORA_PORT="$candidate_port"
    echo -e "${GREEN}Using Bitora panel port: ${BITORA_PORT}${NC}"
}

# Check and install Docker
echo -e "${YELLOW}Checking Docker installation...${NC}"
if command_exists docker; then
    echo -e "${GREEN}✓ Docker is already installed${NC}"
    docker --version
else
    echo -e "${YELLOW}Docker not found. Installing...${NC}"

    if [ "$MACHINE" = "Linux" ]; then
        # Install Docker on Linux
        curl -fsSL https://get.docker.com -o get-docker.sh
        $SUDO_CMD sh get-docker.sh
        if [ -n "$SUDO_CMD" ]; then
            $SUDO_CMD usermod -aG docker $USER
        fi
        rm get-docker.sh
        echo -e "${GREEN}✓ Docker installed successfully${NC}"
    elif [ "$MACHINE" = "Mac" ]; then
        echo -e "${RED}Please install Docker Desktop for Mac from: https://www.docker.com/products/docker-desktop${NC}"
        exit 1
    else
        echo -e "${RED}Unsupported OS. Please install Docker manually.${NC}"
        exit 1
    fi
fi

echo ""

# Check and install Docker Compose
echo -e "${YELLOW}Checking Docker Compose installation...${NC}"
if command_exists docker-compose || docker compose version >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Docker Compose is already installed${NC}"
    docker compose version || docker-compose --version
else
    echo -e "${YELLOW}Docker Compose not found. Installing...${NC}"

    if [ "$MACHINE" = "Linux" ]; then
        $SUDO_CMD curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        $SUDO_CMD chmod +x /usr/local/bin/docker-compose
        echo -e "${GREEN}✓ Docker Compose installed successfully${NC}"
    else
        echo -e "${RED}Please install Docker Compose manually${NC}"
        exit 1
    fi
fi

echo ""
echo -e "${YELLOW}Setting up Bitora...${NC}"

# Create installation directory
INSTALL_DIR="$HOME/bitora"
mkdir -p $INSTALL_DIR

# Download Traefik configuration
echo -e "${YELLOW}Setting up Traefik reverse proxy...${NC}"
mkdir -p $INSTALL_DIR/traefik

cat > $INSTALL_DIR/traefik/docker-compose.yml <<'EOF'
services:
  traefik:
    image: traefik:latest
    container_name: traefik
    restart: unless-stopped
    environment:
      - DOCKER_API_VERSION=1.40
    command:
      - "--api.dashboard=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--providers.docker.network=traefik-network"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
    ports:
      - "80:80"
      - "443:443"
      - "9090:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    networks:
      - traefik-network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.dashboard.rule=Host(`traefik.localhost`)"
      - "traefik.http.routers.dashboard.service=api@internal"
      - "traefik.http.routers.dashboard.entrypoints=web"

networks:
  traefik-network:
    name: traefik-network
    driver: bridge
    enable_ipv6: true
EOF

# Start Traefik
cd $INSTALL_DIR/traefik
docker compose up -d

echo -e "${GREEN}✓ Traefik started successfully${NC}"
echo ""

# Pull and start Bitora panel
echo -e "${YELLOW}Setting up Bitora panel...${NC}"
BITORA_IMAGE="${BITORA_IMAGE:-mrbohem7/bitora:latest}"
BITORA_PORT="${BITORA_PORT:-8080}"
choose_available_port "$BITORA_PORT"

echo -e "${YELLOW}Pulling Bitora image: ${BITORA_IMAGE}${NC}"
docker pull $BITORA_IMAGE

echo -e "${YELLOW}Starting Bitora panel container...${NC}"

# Create data directory with proper structure
mkdir -p $INSTALL_DIR/data/logs
mkdir -p $INSTALL_DIR/data/framework/{cache,sessions,views}
mkdir -p $INSTALL_DIR/data/app
touch $INSTALL_DIR/data/logs/laravel.log
touch $INSTALL_DIR/data/logs/worker.log

docker run -d \
  --name bitora-panel \
  --restart unless-stopped \
  --network traefik-network \
  -e DOCKER_API_VERSION=1.40 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v $INSTALL_DIR/data:/var/www/html/storage \
  -p ${BITORA_PORT}:80 \
  $BITORA_IMAGE

echo -e "${GREEN}✓ Bitora panel started successfully${NC}"
echo ""

# Wait for panel to be ready
echo -e "${YELLOW}Waiting for panel to initialize...${NC}"
sleep 5

# Generate app key
echo -e "${YELLOW}Generating application key...${NC}"
docker exec bitora-panel php artisan key:generate --force

# Run migrations
echo -e "${YELLOW}Running database migrations...${NC}"
docker exec bitora-panel php artisan migrate --force

# Fix permissions for storage and cache
echo -e "${YELLOW}Setting up permissions...${NC}"
docker exec bitora-panel chown -R www-data:www-data /var/www/html/storage
docker exec bitora-panel chmod -R 775 /var/www/html/storage
docker exec bitora-panel chown -R www-data:www-data /var/www/html/bootstrap/cache
docker exec bitora-panel chmod -R 775 /var/www/html/bootstrap/cache

# Set host permissions (82 = www-data UID in Alpine)
$SUDO_CMD chown -R 82:82 $INSTALL_DIR/data
$SUDO_CMD chmod -R 775 $INSTALL_DIR/data

echo -e "${GREEN}✓ Bitora setup complete${NC}"
echo ""

# Get server IP
if [ "$MACHINE" = "Linux" ]; then
    SERVER_IP=$(hostname -I | awk '{print $1}')
else
    SERVER_IP=$(ipconfig getifaddr en0 2>/dev/null || echo "localhost")
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   Installation Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo ""
echo -e "1. Access Bitora panel at: ${GREEN}http://${SERVER_IP}:${BITORA_PORT}${NC}"
echo -e "2. Create admin user:"
echo -e "   ${GREEN}docker exec -it bitora-panel php artisan admin:create${NC}"
echo ""
echo -e "${YELLOW}Deployed projects will be accessible at:${NC}"
echo -e "   - Project ports: ${GREEN}http://${SERVER_IP}:8081, :8082, :8083...${NC}"
echo ""
echo -e "${YELLOW}Traefik dashboard (optional): ${GREEN}http://${SERVER_IP}:9090${NC}"
echo ""

# Create helper script for admin user creation
cat > $INSTALL_DIR/create-admin.sh <<'EOF'
#!/bin/bash
docker exec -it bitora-panel php artisan admin:create
EOF

chmod +x $INSTALL_DIR/create-admin.sh

echo -e "${YELLOW}Installation directory: ${GREEN}${INSTALL_DIR}${NC}"
echo ""
