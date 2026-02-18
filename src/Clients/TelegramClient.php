<?php

declare(strict_types=1);

namespace Autoposter\Clients;

use Autoposter\Core\Env;

final class TelegramClient
{
    private function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot' . Env::get('TELEGRAM_BOT_TOKEN') . '/' . $method;
    }

    public function getMe(): array
    {
        return $this->send('getMe', []);
    }

    public function publish(string $channelId, array $content): array
    {
        return match ($content['type'] ?? 'text') {
            'photo' => $this->send('sendPhoto', ['chat_id' => $channelId, 'photo' => $content['media_url'], 'caption' => $content['caption'] ?? '']),
            'video' => $this->send('sendVideo', ['chat_id' => $channelId, 'video' => $content['media_url'], 'caption' => $content['caption'] ?? '']),
            'album' => $this->send('sendMediaGroup', ['chat_id' => $channelId, 'media' => json_encode($content['media'])]),
            'card' => $this->send('sendMessage', [
                'chat_id' => $channelId,
                'text' => $content['text'] ?? '',
                'reply_markup' => json_encode(['inline_keyboard' => $content['buttons'] ?? []]),
            ]),
            default => $this->send('sendMessage', ['chat_id' => $channelId, 'text' => $content['text'] ?? '']),
        };
    }

    private function send(string $method, array $params): array
    {
        $ch = curl_init($this->apiUrl($method));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new \RuntimeException('Telegram request failed');
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($status >= 400 || !is_array($decoded) || !($decoded['ok'] ?? false)) {
            throw new \RuntimeException('Telegram API error');
        }

        return $decoded;
    }
}
