#!/usr/bin/env bash
set -euo pipefail
MODE="${1:-worker-only}"
IFACE="${2:-wg0}"

if [[ "$MODE" == "off" ]]; then exit 0; fi

iptables -t mangle -C OUTPUT -m owner --uid-owner autoposter -j MARK --set-mark 51820 2>/dev/null || \
iptables -t mangle -A OUTPUT -m owner --uid-owner autoposter -j MARK --set-mark 51820
ip rule show | grep -q "fwmark 0xca6c lookup 51820" || ip rule add fwmark 51820 table 51820
ip route show table 51820 | grep -q "default dev ${IFACE}" || ip route add default dev "${IFACE}" table 51820

echo "WireGuard policy routing applied (${MODE})"
