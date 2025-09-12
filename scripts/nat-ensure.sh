#!/usr/bin/env bash
set -euo pipefail
SUBNET="${1:-}"
IFACE="${2:-eth0}"

if [[ -z "$SUBNET" ]]; then
  echo "Usage: nat-ensure.sh <SUBNET> [IFACE]" >&2
  exit 1
fi

# Enable IP forwarding
sysctl -w net.ipv4.ip_forward=1 >/dev/null 2>&1 || true
# allow forwarding
iptables -C FORWARD -s "$SUBNET" -j ACCEPT 2>/dev/null || iptables -A FORWARD -s "$SUBNET" -j ACCEPT
iptables -C FORWARD -d "$SUBNET" -j ACCEPT 2>/dev/null || iptables -A FORWARD -d "$SUBNET" -j ACCEPT

# Ensure MASQUERADE rule exists
if ! iptables -t nat -C POSTROUTING -s "$SUBNET" -o "$IFACE" -j MASQUERADE 2>/dev/null; then
  iptables -t nat -A POSTROUTING -s "$SUBNET" -o "$IFACE" -j MASQUERADE
fi
