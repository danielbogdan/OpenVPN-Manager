#!/bin/bash

# OpenVPN Manager - VMware Integration Guide
# This script provides guidance and tools for VMware integration

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${BLUE}OpenVPN Manager - VMware Integration Guide${NC}"
echo "=============================================="

# Function to display network information
show_network_info() {
    echo -e "${CYAN}Current Network Configuration:${NC}"
    echo "--------------------------------"
    
    echo -e "${YELLOW}Physical Interfaces:${NC}"
    ip link show | grep -E "^[0-9]+:" | grep -v lo | while read line; do
        interface=$(echo "$line" | cut -d: -f2 | tr -d ' ')
        status=$(ip link show "$interface" | grep -o "state [A-Z]*" | cut -d' ' -f2)
        echo "  - $interface ($status)"
    done
    
    echo ""
    echo -e "${YELLOW}Bridge Interfaces:${NC}"
    if ip link show | grep -q "br-tenant-"; then
        ip link show | grep "br-tenant-" | while read line; do
            bridge=$(echo "$line" | cut -d: -f2 | tr -d ' ')
            ip=$(ip addr show "$bridge" 2>/dev/null | grep "inet " | awk '{print $2}' || echo "No IP assigned")
            echo "  - $bridge: $ip"
        done
    else
        echo "  No OpenVPN Manager bridges found"
    fi
    
    echo ""
    echo -e "${YELLOW}Docker Networks:${NC}"
    if command -v docker >/dev/null 2>&1; then
        docker network ls | grep "net_tenant_" | while read line; do
            network_id=$(echo "$line" | awk '{print $1}')
            network_name=$(echo "$line" | awk '{print $2}')
            driver=$(echo "$line" | awk '{print $3}')
            echo "  - $network_name ($driver)"
        done
    else
        echo "  Docker not available"
    fi
}

# Function to show tenant information
show_tenant_info() {
    echo -e "${CYAN}Tenant Information:${NC}"
    echo "-------------------"
    
    if [ -f "/var/www/html/config.php" ]; then
        # Try to get tenant info from database
        php -r "
        require '/var/www/html/config.php';
        use App\DB;
        try {
            \$pdo = DB::pdo();
            \$stmt = \$pdo->query('SELECT id, name, public_ip, listen_port, subnet_cidr, status FROM tenants ORDER BY id');
            \$tenants = \$stmt->fetchAll();
            
            if (empty(\$tenants)) {
                echo 'No tenants found. Create a tenant through the web interface first.';
            } else {
                echo 'ID | Name | Public IP | Port | Subnet | Status' . PHP_EOL;
                echo '---|------|-----------|------|--------|-------' . PHP_EOL;
                foreach (\$tenants as \$tenant) {
                    printf('%2d | %-10s | %-9s | %4d | %-12s | %s' . PHP_EOL,
                        \$tenant['id'],
                        \$tenant['name'],
                        \$tenant['public_ip'],
                        \$tenant['listen_port'],
                        \$tenant['subnet_cidr'],
                        \$tenant['status']
                    );
                }
            }
        } catch (Exception \$e) {
            echo 'Database connection failed: ' . \$e->getMessage();
        }
        " 2>/dev/null || echo "Could not connect to database"
    else
        echo "OpenVPN Manager not found in /var/www/html"
    fi
}

# Function to generate VMware configuration
generate_vmware_config() {
    echo -e "${CYAN}VMware Configuration Template:${NC}"
    echo "--------------------------------"
    
    cat << 'EOF'
# VMware ESXi/vCenter Configuration
# =================================

## 1. Create Virtual Switch
# In VMware ESXi/vCenter:
# - Go to Networking > Virtual Switches
# - Create new vSwitch (e.g., "OpenVPN-Tenant1")
# - Connect to physical NIC (e.g., vmnic0)
# - Set VLAN ID if needed

## 2. Create Port Group
# - Create new Port Group on the vSwitch
# - Name: "OpenVPN-Tenant1-Network"
# - VLAN ID: (same as vSwitch or specific VLAN)

## 3. VM Network Configuration
# - Connect Windows VM to the Port Group
# - Configure VM network adapter
# - Set static IP in tenant subnet range

## 4. Windows VM Network Settings
# Example for tenant with subnet 10.20.0.0/26:
# - IP Address: 10.20.0.10
# - Subnet Mask: 255.255.255.192
# - Gateway: 10.20.0.1 (OpenVPN Manager bridge IP)
# - DNS: 1.1.1.1, 9.9.9.9 (or tenant's DNS servers)

## 5. Test Connectivity
# - Ping the gateway: ping 10.20.0.1
# - Test internet access: ping 8.8.8.8
# - Test DNS resolution: nslookup google.com
EOF
}

# Function to show troubleshooting steps
show_troubleshooting() {
    echo -e "${CYAN}Troubleshooting Guide:${NC}"
    echo "---------------------"
    
    cat << 'EOF'
## Common Issues and Solutions

### 1. VM Cannot Reach Gateway
- Check if bridge interface exists: ip link show br-tenant-X
- Verify bridge IP: ip addr show br-tenant-X
- Check iptables rules: iptables -t nat -L OPENVPN_MANAGER_NAT

### 2. VM Cannot Access Internet
- Verify IP forwarding: sysctl net.ipv4.ip_forward
- Check NAT rules: iptables -t nat -L POSTROUTING
- Test from OpenVPN Manager server: ping 8.8.8.8

### 3. Docker Network Issues
- List Docker networks: docker network ls
- Inspect network: docker network inspect net_tenant_X
- Check container connectivity: docker exec vpn_tenant_X ping 10.20.0.1

### 4. VMware Connectivity Issues
- Verify virtual switch configuration
- Check Port Group settings
- Ensure VM is connected to correct Port Group
- Verify physical NIC connectivity

### 5. Performance Issues
- Check bridge interface status: ip link show br-tenant-X
- Monitor network traffic: iftop -i br-tenant-X
- Check Docker container resources: docker stats vpn_tenant_X

## Useful Commands
- List all bridges: openvpn-manager-bridge list
- Create bridge manually: openvpn-manager-bridge create br-tenant-1 10.20.0.0/26
- Delete bridge: openvpn-manager-bridge delete br-tenant-1
- Check iptables: iptables -t nat -L -v
- Monitor logs: docker logs vpn_tenant_X
EOF
}

# Main menu
case "${1:-menu}" in
    "network")
        show_network_info
        ;;
    "tenants")
        show_tenant_info
        ;;
    "vmware")
        generate_vmware_config
        ;;
    "troubleshoot")
        show_troubleshooting
        ;;
    "menu"|*)
        echo -e "${BLUE}Available commands:${NC}"
        echo "  $0 network      - Show current network configuration"
        echo "  $0 tenants      - Show tenant information"
        echo "  $0 vmware       - Generate VMware configuration template"
        echo "  $0 troubleshoot - Show troubleshooting guide"
        echo ""
        echo -e "${YELLOW}Quick Start:${NC}"
        echo "1. Run: $0 network (check current setup)"
        echo "2. Run: $0 tenants (see available tenants)"
        echo "3. Run: $0 vmware (get VMware config template)"
        echo "4. Run: $0 troubleshoot (if you have issues)"
        ;;
esac

echo ""
echo -e "${GREEN}For more information, visit the OpenVPN Manager documentation.${NC}"
