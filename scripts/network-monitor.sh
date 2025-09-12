#!/bin/bash

# OpenVPN Manager - Network Monitoring Script
# Monitors bridge interfaces, Docker networks, and tenant connectivity

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
REFRESH_INTERVAL=${REFRESH_INTERVAL:-5}
CONTINUOUS_MODE=${CONTINUOUS_MODE:-false}

# Function to get bridge status
get_bridge_status() {
    local bridge_name="$1"
    
    if ! ip link show "$bridge_name" >/dev/null 2>&1; then
        echo -e "${RED}DOWN${NC}"
        return 1
    fi
    
    local status=$(ip link show "$bridge_name" | grep -o "state [A-Z]*" | cut -d' ' -f2)
    local ip=$(ip addr show "$bridge_name" 2>/dev/null | grep "inet " | awk '{print $2}' || echo "No IP")
    
    if [ "$status" = "UP" ]; then
        echo -e "${GREEN}UP${NC} ($ip)"
    else
        echo -e "${YELLOW}$status${NC} ($ip)"
    fi
}

# Function to get Docker network status
get_docker_network_status() {
    local network_name="$1"
    
    if ! docker network inspect "$network_name" >/dev/null 2>&1; then
        echo -e "${RED}NOT FOUND${NC}"
        return 1
    fi
    
    local driver=$(docker network inspect "$network_name" --format '{{.Driver}}')
    local subnet=$(docker network inspect "$network_name" --format '{{range .IPAM.Config}}{{.Subnet}}{{end}}')
    
    echo -e "${GREEN}ACTIVE${NC} ($driver, $subnet)"
}

# Function to get container status
get_container_status() {
    local container_name="$1"
    
    if ! docker ps -a --format "table {{.Names}}" | grep -q "^$container_name$"; then
        echo -e "${RED}NOT FOUND${NC}"
        return 1
    fi
    
    local status=$(docker ps --format "table {{.Names}}\t{{.Status}}" | grep "^$container_name" | awk '{print $2}')
    
    if [ -z "$status" ]; then
        echo -e "${YELLOW}STOPPED${NC}"
    else
        echo -e "${GREEN}$status${NC}"
    fi
}

# Function to test connectivity
test_connectivity() {
    local gateway="$1"
    local name="$2"
    
    if ping -c 1 -W 1 "$gateway" >/dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
}

# Function to get tenant information
get_tenant_info() {
    if [ -f "/var/www/html/config.php" ]; then
        php -r "
        require '/var/www/html/config.php';
        use App\DB;
        try {
            \$pdo = DB::pdo();
            \$stmt = \$pdo->query('SELECT id, name, public_ip, listen_port, subnet_cidr, status, docker_container, docker_network FROM tenants ORDER BY id');
            \$tenants = \$stmt->fetchAll();
            
            foreach (\$tenants as \$tenant) {
                echo \$tenant['id'] . '|' . \$tenant['name'] . '|' . \$tenant['public_ip'] . '|' . \$tenant['listen_port'] . '|' . \$tenant['subnet_cidr'] . '|' . \$tenant['status'] . '|' . \$tenant['docker_container'] . '|' . \$tenant['docker_network'] . PHP_EOL;
            }
        } catch (Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
        }
        " 2>/dev/null
    fi
}

# Function to display monitoring dashboard
show_dashboard() {
    clear
    echo -e "${BLUE}OpenVPN Manager - Network Monitor${NC}"
    echo "======================================"
    echo "Last updated: $(date)"
    echo ""
    
    # Get tenant information
    local tenant_info=$(get_tenant_info)
    
    if [ -z "$tenant_info" ] || echo "$tenant_info" | grep -q "ERROR"; then
        echo -e "${RED}Error: Could not retrieve tenant information${NC}"
        echo "$tenant_info"
        return 1
    fi
    
    # Display header
    printf "%-3s %-15s %-12s %-8s %-15s %-8s %-12s %-12s %-8s %-8s\n" \
        "ID" "Name" "Subnet" "Gateway" "Bridge" "Docker" "Container" "Connectivity" "Status" "Port"
    echo "--------------------------------------------------------------------------------------------------------"
    
    # Process each tenant
    echo "$tenant_info" | while IFS='|' read -r id name public_ip port subnet status container network; do
        if [ -z "$id" ]; then continue; fi
        
        # Extract gateway from subnet
        local gateway=$(echo "$subnet" | cut -d'/' -f1)
        local bridge_name="br-tenant-$id"
        
        # Get statuses
        local bridge_status=$(get_bridge_status "$bridge_name")
        local docker_status=$(get_docker_network_status "$network")
        local container_status=$(get_container_status "$container")
        local connectivity=$(test_connectivity "$gateway" "$name")
        
        # Display row
        printf "%-3s %-15s %-12s %-8s %-15s %-8s %-12s %-12s %-8s %-8s\n" \
            "$id" \
            "$name" \
            "$subnet" \
            "$gateway" \
            "$bridge_status" \
            "$docker_status" \
            "$container_status" \
            "$connectivity" \
            "$status" \
            "$port"
    done
    
    echo ""
    echo -e "${CYAN}Legend:${NC}"
    echo -e "  ${GREEN}✓${NC} = Working  ${RED}✗${NC} = Failed  ${YELLOW}WARN${NC} = Warning"
    echo ""
    echo -e "${YELLOW}Press Ctrl+C to exit${NC}"
}

# Function to show detailed information
show_detailed_info() {
    echo -e "${BLUE}Detailed Network Information${NC}"
    echo "=============================="
    echo ""
    
    # System information
    echo -e "${CYAN}System Information:${NC}"
    echo "  Hostname: $(hostname)"
    echo "  Uptime: $(uptime -p)"
    echo "  Load: $(uptime | awk -F'load average:' '{print $2}')"
    echo ""
    
    # Network interfaces
    echo -e "${CYAN}Network Interfaces:${NC}"
    ip link show | grep -E "^[0-9]+:" | while read line; do
        interface=$(echo "$line" | cut -d: -f2 | tr -d ' ')
        status=$(ip link show "$interface" | grep -o "state [A-Z]*" | cut -d' ' -f2)
        ip=$(ip addr show "$interface" 2>/dev/null | grep "inet " | awk '{print $2}' || echo "No IP")
        echo "  $interface: $status ($ip)"
    done
    echo ""
    
    # Docker information
    if command -v docker >/dev/null 2>&1; then
        echo -e "${CYAN}Docker Information:${NC}"
        echo "  Version: $(docker --version)"
        echo "  Containers: $(docker ps -q | wc -l) running, $(docker ps -aq | wc -l) total"
        echo "  Networks: $(docker network ls -q | wc -l) total"
        echo "  Volumes: $(docker volume ls -q | wc -l) total"
        echo ""
    fi
    
    # Iptables information
    echo -e "${CYAN}Iptables Rules:${NC}"
    echo "  NAT rules: $(iptables -t nat -L | grep -c "MASQUERADE")"
    echo "  Forward rules: $(iptables -L FORWARD | grep -c "ACCEPT")"
    echo ""
}

# Function to show help
show_help() {
    echo -e "${BLUE}OpenVPN Manager - Network Monitor${NC}"
    echo "======================================"
    echo ""
    echo "Usage: $0 [OPTIONS] [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  dashboard     Show monitoring dashboard (default)"
    echo "  detailed      Show detailed network information"
    echo "  help          Show this help message"
    echo ""
    echo "Options:"
    echo "  -r, --refresh SECONDS    Refresh interval for continuous mode (default: 5)"
    echo "  -c, --continuous         Run in continuous mode"
    echo "  -h, --help               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                      # Show dashboard once"
    echo "  $0 -c                   # Show dashboard continuously"
    echo "  $0 -c -r 10             # Show dashboard continuously with 10s refresh"
    echo "  $0 detailed             # Show detailed information"
    echo ""
    echo "Environment Variables:"
    echo "  REFRESH_INTERVAL        Refresh interval in seconds (default: 5)"
    echo "  CONTINUOUS_MODE         Set to 'true' for continuous mode"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -r|--refresh)
            REFRESH_INTERVAL="$2"
            shift 2
            ;;
        -c|--continuous)
            CONTINUOUS_MODE=true
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        dashboard)
            COMMAND="dashboard"
            shift
            ;;
        detailed)
            COMMAND="detailed"
            shift
            ;;
        help)
            show_help
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Set default command
COMMAND=${COMMAND:-dashboard}

# Main execution
case "$COMMAND" in
    "dashboard")
        if [ "$CONTINUOUS_MODE" = "true" ]; then
            while true; do
                show_dashboard
                sleep "$REFRESH_INTERVAL"
            done
        else
            show_dashboard
        fi
        ;;
    "detailed")
        show_detailed_info
        ;;
    *)
        show_help
        exit 1
        ;;
esac
