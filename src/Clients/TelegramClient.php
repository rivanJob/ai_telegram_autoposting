<?php

declare(strict_types=1);

namespace AutoPoster\Clients;

use AutoPoster\Core\Env;
use RuntimeException;

final class TelegramClient
{
    private string $base;

    public function __construct()
    {
        $this->base = 'https://api.telegram.org/bot' . Env::get('TELEGRAM_BOT_TOKEN', '');
    }

    public function publish(array $post): array
    {
        return match ($post['type'] ?? 'text') {
            'photo' => $this->call('sendPhoto', ['chat_id' => $post['channel_id'], 'photo' => $post['media_url'], 'caption' => $post['text'] ?? '']),
            'video' => $this->call('sendVideo', ['chat_id' => $post['channel_id'], 'video' => $post['media_url'], 'caption' => $post['text'] ?? '']),
            'album' => $this->call('sendMediaGroup', ['chat_id' => $post['channel_id'], 'media' => json_encode($post['media'] ?? [])]),
            'card' => $this->call('sendMessage', ['chat_id' => $post['channel_id'], 'text' => $post['text'] ?? '', 'reply_markup' => json_encode(['inline_keyboard' => $post['buttons'] ?? []])]),
            default => $this->call('sendMessage', ['chat_id' => $post['channel_id'], 'text' => $post['text'] ?? '']),
        };
    }

    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    private function call(string $method, array $payload): array
    {
        $ch = curl_init($this->base . '/' . $method);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('Telegram API failure');
        }
        curl_close($ch);
        return json_decode($raw, true) ?? [];
    }
}
