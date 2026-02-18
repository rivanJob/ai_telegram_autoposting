# Принудительный исходящий трафик WireGuard для Grok

Все запросы к Grok должны выходить через `wg0`.

## Режимы
- `off`
- `worker-only` (рекомендуется): воркер работает от пользователя `autoposter`, и только трафик этого пользователя направляется в `wg0`.
- `all-worker-egress`: как worker-only, плюс маршрут по умолчанию через `wg0` для всего исходящего трафика воркера.

## Настройка
```bash
sudo wg-quick up wg0
sudo bash install/wireguard.sh install/config.env
```

Применяемые правила:
```bash
iptables -t mangle -A OUTPUT -m owner --uid-owner $(id -u autoposter) -j MARK --set-mark 51820
ip rule add fwmark 51820 table 51820
ip route add default dev wg0 table 51820
```

## Проверка
```bash
id autoposter
sudo -u autoposter ip route get 1.1.1.1
sudo -u autoposter curl -s https://ifconfig.me
sudo tcpdump -ni wg0 host <grok-api-ip>
```

## Откат
```bash
sudo bash install/unwireguard.sh
sudo wg-quick down wg0
```

## DNS
Используйте DNS-резолвер, доступный через туннель, либо статический маршрут до IP Grok API, чтобы избежать DNS-утечек.
