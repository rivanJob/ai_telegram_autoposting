#!/usr/bin/env bash
set -euo pipefail
AUTPOSTER_UID="$(id -u autoposter 2>/dev/null || echo '')"
ip rule del fwmark 0xca6c table 51820 2>/dev/null || true
ip route del default table 51820 2>/dev/null || true
if [[ -n "$AUTPOSTER_UID" ]]; then
  iptables -t mangle -D OUTPUT -m owner --uid-owner "$AUTPOSTER_UID" -j MARK --set-mark 0xca6c 2>/dev/null || true
fi
echo "WireGuard policy routing removed"
