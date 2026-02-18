#!/usr/bin/env bash
set -euo pipefail
MODE="${1:-worker-only}"
WG_INTERFACE="${WG_INTERFACE:-wg0}"
AUTPOSTER_UID="$(id -u autoposter)"

if ! ip link show "$WG_INTERFACE" >/dev/null 2>&1; then
  echo "WireGuard interface $WG_INTERFACE not found"
  exit 1
fi

ip rule add fwmark 0xca6c table 51820 2>/dev/null || true
ip route add default dev "$WG_INTERFACE" table 51820 2>/dev/null || true

if [[ "$MODE" == "worker-only" || "$MODE" == "all-worker-egress" ]]; then
  iptables -t mangle -A OUTPUT -m owner --uid-owner "$AUTPOSTER_UID" -j MARK --set-mark 0xca6c 2>/dev/null || true
fi

echo "WireGuard policy routing applied for mode=$MODE"
