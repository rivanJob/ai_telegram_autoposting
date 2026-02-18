#!/usr/bin/env bash
set -euo pipefail
IFACE="${1:-wg0}"
iptables -t mangle -D OUTPUT -m owner --uid-owner autoposter -j MARK --set-mark 51820 2>/dev/null || true
ip rule del fwmark 51820 table 51820 2>/dev/null || true
ip route del default dev "${IFACE}" table 51820 2>/dev/null || true
echo "WireGuard policy routing removed"
