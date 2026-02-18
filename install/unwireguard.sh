#!/usr/bin/env bash
set -euo pipefail
AUTPOSTER_UID=$(id -u autoposter)
iptables -t mangle -D OUTPUT -m owner --uid-owner "$AUTPOSTER_UID" -j MARK --set-mark 51820 2>/dev/null || true
ip rule del fwmark 51820 table 51820 2>/dev/null || true
ip route flush table 51820 2>/dev/null || true
echo "WireGuard policy routing removed"
