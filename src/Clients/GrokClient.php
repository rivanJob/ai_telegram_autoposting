<?php

declare(strict_types=1);

namespace Autoposter\Clients;

use Autoposter\Core\Env;

final class GrokClient
{
    public function generate(string $prompt): array
    {
        $payload = [
            'model' => Env::get('GROK_MODEL', 'grok-2-latest'),
            'messages' => [[
                'role' => 'system',
                'content' => 'Return only strict JSON object. No markdown. No extra keys.',
            ], [
                'role' => 'user',
                'content' => $prompt,
            ]],
            'temperature' => 0.7,
        ];

        $response = $this->request($payload);
        $content = $response['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON from Grok');
        }

        return ['raw' => $content, 'json' => $decoded, 'response' => $response];
    }

    public function ping(): bool
    {
        try {
            $this->generate('{"type":"text","text":"ping"}');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function request(array $payload): array
    {
        $ch = curl_init(Env::get('GROK_API_URL'));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . Env::get('GROK_API_KEY', ''),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Env::int('GROK_TIMEOUT', 45),
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new \RuntimeException('Grok request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException('Grok API HTTP ' . $status);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Grok response payload');
        }

        return $decoded;
    }
}
