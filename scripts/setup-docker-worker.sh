#!/bin/bash
#
# TrendRadar Docker Worker Setup Script
# 
# This script creates the trendradarsrv account and grants it the necessary
# permissions to run Docker commands and write to the workspace folder.
# 
# Requirements:
# - Must be run as root
# - Docker must be installed
#
# Usage:
#   sudo ./setup-docker-worker.sh
#

set -e

# Configuration
SERVICE_USER="trendradarsrv"
WORKSPACE_PATH="/srv/trendradar"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    echo -e "${RED}Error: This script must be run as root${NC}"
    echo "Usage: sudo $0"
    exit 1
fi

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed${NC}"
    echo "Please install Docker first: https://docs.docker.com/engine/install/"
    exit 1
fi

# Check if docker group exists
if ! getent group docker > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker group does not exist${NC}"
    echo "Please ensure Docker is properly installed"
    exit 1
fi

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}TrendRadar Docker Worker Setup${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Create service user if it doesn't exist
if id "$SERVICE_USER" &>/dev/null; then
    echo -e "${YELLOW}User '$SERVICE_USER' already exists${NC}"
else
    echo -e "Creating user '$SERVICE_USER'..."
    useradd -m -s /bin/bash "$SERVICE_USER"
    echo -e "${GREEN}User '$SERVICE_USER' created successfully${NC}"
fi

# Add user to docker group
echo "Adding '$SERVICE_USER' to docker group..."
usermod -aG docker "$SERVICE_USER"
echo -e "${GREEN}User '$SERVICE_USER' added to docker group${NC}"

# Create workspace directory
echo "Creating workspace directory at '$WORKSPACE_PATH'..."
mkdir -p "$WORKSPACE_PATH"

# Set ownership and permissions for workspace
echo "Setting permissions for workspace..."
chown "$SERVICE_USER":"$SERVICE_USER" "$WORKSPACE_PATH"
chmod 755 "$WORKSPACE_PATH"
echo -e "${GREEN}Workspace directory created and configured${NC}"

# Verify setup
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Verification${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

echo "User: $SERVICE_USER"
echo "Groups: $(groups $SERVICE_USER)"
echo "Workspace: $WORKSPACE_PATH"
echo "Workspace owner: $(stat -c '%U:%G' $WORKSPACE_PATH)"
echo "Workspace permissions: $(stat -c '%a' $WORKSPACE_PATH)"

# Verify docker access
echo ""
echo "Testing Docker access for $SERVICE_USER..."
if su - "$SERVICE_USER" -c "docker ps" &>/dev/null; then
    echo -e "${GREEN}Docker access: OK${NC}"
else
    echo -e "${YELLOW}Docker access: May require logout/login to take effect${NC}"
    echo "If Docker access fails, try: newgrp docker"
fi

# Set password for the service user
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Set Password${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Please set a password for the '$SERVICE_USER' account."
echo "This password will be used for SSH connections from TrendRadarConsole."
echo ""
passwd "$SERVICE_USER"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "The Docker worker is now configured with:"
echo "  - User: $SERVICE_USER"
echo "  - Workspace: $WORKSPACE_PATH"
echo "  - Docker group membership for container management"
echo ""
echo "Next steps:"
echo "  1. Note the SSH connection details (host, port 22, user: $SERVICE_USER)"
echo "  2. Configure these settings in TrendRadarConsole Docker deployment page"
echo "  3. Use 'Test Connection' to verify the setup"
echo ""
