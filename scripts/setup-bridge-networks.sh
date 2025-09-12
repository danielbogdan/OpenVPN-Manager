#!/bin/bash

# OpenVPN Manager - Bridge Network Setup Script
# This script sets up the necessary network configuration for VMware integration

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
EXTERNAL_INTERFACE=${EXTERNAL_INTERFACE:-eth0}
ENABLE_IP_FORWARDING=${ENABLE_IP_FORWARDING:-true}

echo -e "${BLUE}OpenVPN Manager - Bridge Network Setup${NC}"
echo "=============================================="

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}This script must be run as root (use sudo)${NC}"
   exit 1
fi

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check required commands
echo -e "${YELLOW}Checking required commands...${NC}"
for cmd in ip iptables docker; do
    if ! command_exists $cmd; then
        echo -e "${RED}Error: $cmd is not installed${NC}"
        exit 1
    fi
done
echo -e "${GREEN}✓ All required commands found${NC}"

# Enable IP forwarding
if [ "$ENABLE_IP_FORWARDING" = "true" ]; then
    echo -e "${YELLOW}Enabling IP forwarding...${NC}"
    echo 'net.ipv4.ip_forward=1' >> /etc/sysctl.conf
    echo 'net.ipv6.conf.all.forwarding=1' >> /etc/sysctl.conf
    sysctl -p
    echo -e "${GREEN}✓ IP forwarding enabled${NC}"
fi

# Check if external interface exists
if ! ip link show "$EXTERNAL_INTERFACE" >/dev/null 2>&1; then
    echo -e "${RED}Error: External interface $EXTERNAL_INTERFACE not found${NC}"
    echo "Available interfaces:"
    ip link show | grep -E "^[0-9]+:" | cut -d: -f2 | tr -d ' '
    exit 1
fi

echo -e "${GREEN}✓ External interface $EXTERNAL_INTERFACE found${NC}"

# Create iptables rules for NAT
echo -e "${YELLOW}Setting up iptables rules...${NC}"

# Create a custom chain for OpenVPN Manager rules
iptables -t nat -N OPENVPN_MANAGER_NAT 2>/dev/null || true
iptables -t filter -N OPENVPN_MANAGER_FORWARD 2>/dev/null || true

# Add rules to the custom chains
iptables -t nat -A OPENVPN_MANAGER_NAT -s 10.20.0.0/16 -o "$EXTERNAL_INTERFACE" -j MASQUERADE
iptables -t nat -A OPENVPN_MANAGER_NAT -s 10.21.0.0/16 -o "$EXTERNAL_INTERFACE" -j MASQUERADE
iptables -t nat -A OPENVPN_MANAGER_NAT -s 10.22.0.0/16 -o "$EXTERNAL_INTERFACE" -j MASQUERADE
iptables -t nat -A OPENVPN_MANAGER_NAT -s 10.23.0.0/16 -o "$EXTERNAL_INTERFACE" -j MASQUERADE
iptables -t nat -A OPENVPN_MANAGER_NAT -s 10.24.0.0/16 -o "$EXTERNAL_INTERFACE" -j MASQUERADE

# Add forward rules
iptables -t filter -A OPENVPN_MANAGER_FORWARD -i br-tenant-+ -o "$EXTERNAL_INTERFACE" -j ACCEPT
iptables -t filter -A OPENVPN_MANAGER_FORWARD -i "$EXTERNAL_INTERFACE" -o br-tenant-+ -m state --state RELATED,ESTABLISHED -j ACCEPT

# Insert rules into main chains
iptables -t nat -I POSTROUTING 1 -j OPENVPN_MANAGER_NAT
iptables -t filter -I FORWARD 1 -j OPENVPN_MANAGER_FORWARD

echo -e "${GREEN}✓ Iptables rules configured${NC}"

# Create systemd service to restore iptables rules on boot
echo -e "${YELLOW}Creating iptables restore service...${NC}"

cat > /etc/systemd/system/openvpn-manager-iptables.service << EOF
[Unit]
Description=OpenVPN Manager iptables rules
After=network.target

[Service]
Type=oneshot
ExecStart=/bin/bash -c 'iptables-restore < /etc/iptables/openvpn-manager.rules'
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

# Save current iptables rules
mkdir -p /etc/iptables
iptables-save > /etc/iptables/openvpn-manager.rules

# Enable the service
systemctl enable openvpn-manager-iptables.service

echo -e "${GREEN}✓ Iptables restore service created and enabled${NC}"

# Create bridge management script
echo -e "${YELLOW}Creating bridge management script...${NC}"

cat > /usr/local/bin/openvpn-manager-bridge << 'EOF'
#!/bin/bash

# OpenVPN Manager Bridge Management Script

case "$1" in
    create)
        if [ -z "$2" ] || [ -z "$3" ]; then
            echo "Usage: $0 create <bridge-name> <subnet>"
            exit 1
        fi
        
        BRIDGE_NAME="$2"
        SUBNET="$3"
        GATEWAY=$(echo "$SUBNET" | cut -d'/' -f1)
        CIDR=$(echo "$SUBNET" | cut -d'/' -f2)
        
        ip link add name "$BRIDGE_NAME" type bridge
        ip addr add "$GATEWAY/$CIDR" dev "$BRIDGE_NAME"
        ip link set "$BRIDGE_NAME" up
        
        echo "Bridge $BRIDGE_NAME created with subnet $SUBNET"
        ;;
        
    delete)
        if [ -z "$2" ]; then
            echo "Usage: $0 delete <bridge-name>"
            exit 1
        fi
        
        BRIDGE_NAME="$2"
        
        ip link set "$BRIDGE_NAME" down
        ip link delete "$BRIDGE_NAME"
        
        echo "Bridge $BRIDGE_NAME deleted"
        ;;
        
    list)
        echo "OpenVPN Manager Bridges:"
        ip link show | grep -E "br-tenant-[0-9]+" | cut -d: -f2 | tr -d ' '
        ;;
        
    *)
        echo "Usage: $0 {create|delete|list}"
        echo "  create <bridge-name> <subnet>  - Create a bridge interface"
        echo "  delete <bridge-name>           - Delete a bridge interface"
        echo "  list                           - List all OpenVPN Manager bridges"
        exit 1
        ;;
esac
EOF

chmod +x /usr/local/bin/openvpn-manager-bridge

echo -e "${GREEN}✓ Bridge management script created${NC}"

# Create Docker daemon configuration for bridge networks
echo -e "${YELLOW}Configuring Docker for bridge networks...${NC}"

mkdir -p /etc/docker
cat > /etc/docker/daemon.json << EOF
{
    "bridge": "docker0",
    "iptables": true,
    "ip-forward": true,
    "ip-masq": true,
    "userland-proxy": false
}
EOF

echo -e "${GREEN}✓ Docker configuration updated${NC}"

echo ""
echo -e "${GREEN}=============================================${NC}"
echo -e "${GREEN}OpenVPN Manager Bridge Setup Complete!${NC}"
echo -e "${GREEN}=============================================${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "1. Restart Docker service: systemctl restart docker"
echo "2. Start OpenVPN Manager: docker compose up -d"
echo "3. Create tenants through the web interface"
echo ""
echo -e "${BLUE}Bridge Management:${NC}"
echo "- List bridges: openvpn-manager-bridge list"
echo "- Create bridge: openvpn-manager-bridge create br-tenant-1 10.20.0.0/26"
echo "- Delete bridge: openvpn-manager-bridge delete br-tenant-1"
echo ""
echo -e "${BLUE}VMware Integration:${NC}"
echo "1. Create a VMware virtual switch"
echo "2. Connect it to the same physical NIC as your OpenVPN Manager server"
echo "3. Connect your Windows VM to the virtual switch"
echo "4. Configure the VM with an IP in the tenant's subnet range"
echo ""
echo -e "${YELLOW}Note: Bridge interfaces will be created automatically when tenants are created.${NC}"
