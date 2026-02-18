<?php

declare(strict_types=1);

namespace AutoPoster\Clients;

use AutoPoster\Core\Env;
use RuntimeException;

final class GrokClient
{
    public function generate(string $prompt): array
    {
        $payload = [
            'model' => Env::get('GROK_MODEL', 'grok-2-latest'),
            'messages' => [[
                'role' => 'user',
                'content' => "Return JSON only without markdown or prose.\\n" . $prompt,
            ]],
            'temperature' => 0.3,
        ];

        $response = $this->request($payload);
        $content = $response['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode((string)$content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from Grok');
        }

        return ['raw' => $response, 'json' => $decoded, 'raw_text' => $content];
    }

    private function request(array $payload): array
    {
        $ch = curl_init(Env::get('GROK_API_URL'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . Env::get('GROK_API_KEY', ''),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => (int)Env::get('GROK_TIMEOUT', '30'),
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('Grok call failed');
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new RuntimeException('Grok HTTP error ' . $status);
        }
        return json_decode($raw, true) ?? [];
    }
}
