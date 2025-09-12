# OpenVPN Manager - VMware Integration Guide

This guide explains how to integrate OpenVPN Manager with VMware virtual machines to provide network connectivity and internet access through OpenVPN tenants.

## Overview

OpenVPN Manager now supports VMware integration through bridge interfaces. Each tenant creates its own bridge interface that can be connected to VMware virtual switches, allowing VMs to access the internet through the OpenVPN tenant's NAT configuration.

## Architecture

```
Windows VM (10.10.0.5) 
    ↓ (VMware Virtual Switch)
OpenVPN Manager Server (10.20.0.1)
    ↓ (Docker Network: net_tenant_1)
OpenVPN Container (10.20.0.2)
    ↓ (NAT/Masquerading)
Internet
```

## Prerequisites

- OpenVPN Manager installed and running
- VMware ESXi/vCenter or VMware Workstation
- Root/sudo access on the OpenVPN Manager server
- Physical network connectivity between VMware and OpenVPN Manager

## Installation

### 1. Setup Bridge Networks

Run the bridge network setup script on your OpenVPN Manager server:

```bash
sudo ./scripts/setup-bridge-networks.sh
```

This script will:
- Enable IP forwarding
- Configure iptables rules for NAT
- Create bridge management tools
- Set up Docker daemon configuration

### 2. Restart Services

```bash
sudo systemctl restart docker
docker compose up -d
```

## VMware Configuration

### 1. Create Virtual Switch

In VMware ESXi/vCenter:
1. Go to **Networking** > **Virtual Switches**
2. Create new vSwitch (e.g., "OpenVPN-Tenant1")
3. Connect to physical NIC (e.g., vmnic0)
4. Set VLAN ID if needed

### 2. Create Port Group

1. Create new Port Group on the vSwitch
2. Name: "OpenVPN-Tenant1-Network"
3. VLAN ID: (same as vSwitch or specific VLAN)

### 3. Connect VM to Port Group

1. Edit VM settings
2. Add/Edit network adapter
3. Connect to the Port Group created above

## VM Network Configuration

### Windows VM Example

For a tenant with subnet `10.20.0.0/26`:

- **IP Address**: `10.20.0.10`
- **Subnet Mask**: `255.255.255.192`
- **Gateway**: `10.20.0.1` (OpenVPN Manager bridge IP)
- **DNS**: `1.1.1.1`, `9.9.9.9` (or tenant's DNS servers)

### Linux VM Example

```bash
# Configure network interface
sudo ip addr add 10.20.0.10/26 dev eth0
sudo ip route add default via 10.20.0.1
echo "nameserver 1.1.1.1" | sudo tee /etc/resolv.conf
echo "nameserver 9.9.9.9" | sudo tee -a /etc/resolv.conf
```

## Management Tools

### Bridge Management

```bash
# List all OpenVPN Manager bridges
openvpn-manager-bridge list

# Create bridge manually
openvpn-manager-bridge create br-tenant-1 10.20.0.0/26

# Delete bridge
openvpn-manager-bridge delete br-tenant-1
```

### Network Monitoring

```bash
# Show monitoring dashboard
./scripts/network-monitor.sh

# Show detailed network information
./scripts/network-monitor.sh detailed

# Continuous monitoring
./scripts/network-monitor.sh -c
```

### VMware Integration Guide

```bash
# Show VMware configuration template
./scripts/vmware-integration-guide.sh vmware

# Show current network configuration
./scripts/vmware-integration-guide.sh network

# Show tenant information
./scripts/vmware-integration-guide.sh tenants

# Show troubleshooting guide
./scripts/vmware-integration-guide.sh troubleshoot
```

## Automatic Bridge Creation

When you create a new tenant through the OpenVPN Manager web interface:

1. A bridge interface is automatically created (e.g., `br-tenant-1`)
2. The bridge is configured with the tenant's subnet
3. Docker network is created with bridge configuration
4. iptables rules are configured for NAT
5. OpenVPN container is started with the new network

## Network Flow

1. **VM Traffic**: VM sends traffic to gateway (bridge IP)
2. **Bridge Routing**: Bridge forwards traffic to Docker network
3. **OpenVPN Processing**: OpenVPN container processes the traffic
4. **NAT Translation**: Traffic is NAT'd to external interface
5. **Internet Access**: Traffic reaches the internet

## Troubleshooting

### Common Issues

#### VM Cannot Reach Gateway
```bash
# Check bridge interface
ip link show br-tenant-1
ip addr show br-tenant-1

# Check iptables rules
iptables -t nat -L OPENVPN_MANAGER_NAT
```

#### VM Cannot Access Internet
```bash
# Verify IP forwarding
sysctl net.ipv4.ip_forward

# Check NAT rules
iptables -t nat -L POSTROUTING

# Test from server
ping 8.8.8.8
```

#### Docker Network Issues
```bash
# List Docker networks
docker network ls

# Inspect network
docker network inspect net_tenant_1

# Check container connectivity
docker exec vpn_tenant_1 ping 10.20.0.1
```

#### VMware Connectivity Issues
- Verify virtual switch configuration
- Check Port Group settings
- Ensure VM is connected to correct Port Group
- Verify physical NIC connectivity

### Performance Optimization

1. **Bridge Interface**: Monitor bridge interface status
2. **Network Traffic**: Use `iftop` to monitor traffic
3. **Container Resources**: Monitor Docker container resources
4. **System Load**: Check system load and memory usage

## Security Considerations

1. **Network Isolation**: Each tenant has its own isolated network
2. **Firewall Rules**: iptables rules provide additional security
3. **Access Control**: Use OpenVPN Manager's user management
4. **Monitoring**: Regular monitoring of network traffic

## Advanced Configuration

### Custom Subnets

You can modify the subnet allocation in `lib/Util.php`:

```php
public static function allocateSubnet(): string
{
    // Custom subnet allocation logic
    return "10.20.0.0/26";
}
```

### Multiple External Interfaces

Configure different external interfaces for different tenants:

```php
// In OpenVPNManager.php
$externalInterface = "eth1"; // Use different interface
DockerCLI::configureBridgeRouting($bridgeName, $subnet, $externalInterface);
```

### VLAN Support

Add VLAN support to bridge interfaces:

```bash
# Create VLAN bridge
ip link add link br-tenant-1 name br-tenant-1.100 type vlan id 100
ip addr add 10.20.0.1/26 dev br-tenant-1.100
ip link set br-tenant-1.100 up
```

## Support

For issues and questions:
1. Check the troubleshooting guide: `./scripts/vmware-integration-guide.sh troubleshoot`
2. Monitor network status: `./scripts/network-monitor.sh`
3. Review logs: `docker logs vpn_tenant_X`
4. Check system resources: `htop`, `iftop`, `iotop`

## Changelog

- **v1.0**: Initial VMware integration support
- **v1.1**: Added bridge management tools
- **v1.2**: Added network monitoring
- **v1.3**: Added automatic bridge creation
- **v1.4**: Added comprehensive troubleshooting tools
