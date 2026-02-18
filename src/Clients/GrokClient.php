<?php

declare(strict_types=1);

namespace App\Clients;

use App\Core\Env;

final class GrokClient
{
    public function generate(string $prompt): array
    {
        $payload = [
            'model' => Env::get('GROK_MODEL', 'grok-2-latest'),
            'messages' => [[
                'role' => 'system',
                'content' => 'Return ONLY valid JSON. No markdown. No prose.',
            ], [
                'role' => 'user',
                'content' => $prompt,
            ]],
            'temperature' => 0.4,
        ];

        $ch = curl_init(Env::get('GROK_API_URL', ''));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . Env::get('GROK_API_KEY', ''),
            ],
            CURLOPT_TIMEOUT => Env::int('GROK_TIMEOUT', 45),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = (string) curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new \RuntimeException('Grok request failed');
        }

        $parsed = json_decode($raw, true);
        $content = $parsed['choices'][0]['message']['content'] ?? $raw;
        $contentJson = json_decode((string) $content, true);
        if (!is_array($contentJson)) {
            throw new \RuntimeException('Invalid JSON response from model');
        }

        return ['raw' => $raw, 'json' => $contentJson];
    }
}
