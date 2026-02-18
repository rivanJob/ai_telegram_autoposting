#!/usr/bin/env bash
set -euo pipefail
CONFIG_FILE="${1:-install/config.env}"
source "$CONFIG_FILE"

AUTPOSTER_UID=$(id -u autoposter)
iptables -t mangle -C OUTPUT -m owner --uid-owner "$AUTPOSTER_UID" -j MARK --set-mark 51820 2>/dev/null || \
iptables -t mangle -A OUTPUT -m owner --uid-owner "$AUTPOSTER_UID" -j MARK --set-mark 51820

ip rule show | grep -q "fwmark 0xca6c lookup 51820" || ip rule add fwmark 51820 table 51820
ip route show table 51820 | grep -q "default dev ${WG_INTERFACE}" || ip route add default dev "${WG_INTERFACE}" table 51820

echo "WireGuard policy routing enabled for autoposter user"
