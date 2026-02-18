<?php

declare(strict_types=1);

namespace App\Clients;

use App\Core\Env;

final class TelegramClient
{
    private string $base;

    public function __construct()
    {
        $this->base = 'https://api.telegram.org/bot' . Env::get('TELEGRAM_BOT_TOKEN', '');
    }

    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    public function publish(string $chatId, array $content): array
    {
        $type = $content['type'] ?? 'text';
        return match ($type) {
            'text' => $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $content['text'] ?? '']),
            'photo' => $this->call('sendPhoto', ['chat_id' => $chatId, 'photo' => $content['media_url'] ?? '', 'caption' => $content['caption'] ?? '']),
            'video' => $this->call('sendVideo', ['chat_id' => $chatId, 'video' => $content['media_url'] ?? '', 'caption' => $content['caption'] ?? '']),
            'album' => $this->call('sendMediaGroup', ['chat_id' => $chatId, 'media' => json_encode($content['media'] ?? [])]),
            'card' => $this->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $content['text'] ?? '',
                'reply_markup' => json_encode(['inline_keyboard' => $content['buttons'] ?? []]),
            ]),
            default => throw new \InvalidArgumentException('Unsupported post type'),
        };
    }

    private function call(string $method, array $payload): array
    {
        $ch = curl_init($this->base . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = (string) curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '') {
            throw new \RuntimeException('Telegram API request failed');
        }
        return json_decode($raw, true) ?? ['ok' => false, 'raw' => $raw];
    }
}
