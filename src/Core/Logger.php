<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public function __construct(private readonly string $file)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARN', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        $record = [
            'ts' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $this->redact($context),
        ];
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($this->file, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }

    private function redact(array $context): array
    {
        $secretKeys = ['token', 'password', 'secret', 'api_key', 'authorization'];
        array_walk_recursive($context, static function (&$value, $key) use ($secretKeys): void {
            if (in_array(strtolower((string) $key), $secretKeys, true)) {
                $value = '***redacted***';
            }
        });
        return $context;
    }
}
