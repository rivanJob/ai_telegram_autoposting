# WireGuard Egress Enforcement (wg0)

All Grok API traffic must leave via `wg0`.

## Modes
- `off`
- `worker-only-egress-via-wg0` (recommended; maps to `worker-only` in scripts)
- `all-worker-egress-via-wg0` (maps to `all-worker-egress`)

## Setup
```bash
sudo wg-quick up wg0
sudo WG_INTERFACE=wg0 ./install/wireguard.sh worker-only
```

Routing policy used:
1. mark packets from linux user `autoposter` with fwmark `0xca6c`
2. `ip rule add fwmark 0xca6c table 51820`
3. `ip route add default dev wg0 table 51820`

## Verify
```bash
id autoposter
ip rule show | rg 51820
ip route show table 51820
sudo -u autoposter curl -s https://ifconfig.me
sudo -u autoposter ip route get 1.1.1.1
sudo tcpdump -i wg0 host <grok_api_ip>
```

## Rollback
```bash
sudo ./install/unwireguard.sh
sudo wg-quick down wg0
```

## DNS notes
If Grok hostname resolves through default resolver, ensure DNS itself is trusted or route DNS through wg0 resolver via `systemd-resolved` split DNS.
