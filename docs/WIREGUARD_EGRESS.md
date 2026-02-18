# WireGuard egress control for Grok (mandatory)

All Grok requests must egress through `wg0`.

## Modes
- `off`
- `worker-only-egress-via-wg0` (recommended)
- `all-worker-egress-via-wg0`

## Apply routing
```bash
sudo bash install/wireguard.sh worker-only wg0
```

What it does:
1. Marks packets from linux user `autoposter` with fwmark `51820`.
2. Adds policy rule `fwmark 51820 lookup table 51820`.
3. Routes table `51820` default via `wg0`.

## Verification checklist
```bash
id autoposter
sudo ip rule show
sudo ip route show table 51820
sudo -u autoposter ip route get 1.1.1.1
sudo tcpdump -ni wg0 host api.x.ai
sudo -u autoposter curl https://ifconfig.me
```

## Rollback
```bash
sudo bash install/unwireguard.sh wg0
```

If DNS leaks are a concern, force resolver on wg-specific DNS in `/etc/wireguard/wg0.conf` and restart `wg-quick@wg0`.
