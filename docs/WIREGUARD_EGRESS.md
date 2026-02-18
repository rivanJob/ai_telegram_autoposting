# WireGuard Egress Enforcement for Grok

All Grok requests must leave via `wg0`.

## Modes
- `off`
- `worker-only` (recommended): worker runs as `autoposter` and only this user is policy-routed to wg0.
- `all-worker-egress`: same as worker-only plus set default route for all worker traffic.

## Setup
```bash
sudo wg-quick up wg0
sudo bash install/wireguard.sh install/config.env
```

Rules applied:
```bash
iptables -t mangle -A OUTPUT -m owner --uid-owner $(id -u autoposter) -j MARK --set-mark 51820
ip rule add fwmark 51820 table 51820
ip route add default dev wg0 table 51820
```

## Verify
```bash
id autoposter
sudo -u autoposter ip route get 1.1.1.1
sudo -u autoposter curl -s https://ifconfig.me
sudo tcpdump -ni wg0 host <grok-api-ip>
```

## Rollback
```bash
sudo bash install/unwireguard.sh
sudo wg-quick down wg0
```

## DNS
Prefer resolver reachable over tunnel or static Grok API IP route to avoid DNS leaks.
