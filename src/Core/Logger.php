<?php

declare(strict_types=1);

namespace Autoposter\Core;

final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $safeContext = $this->redactSecrets($context);
        $entry = [
            'ts' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $safeContext,
        ];
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($this->path, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }

    private function redactSecrets(array $context): array
    {
        $secretKeys = ['token', 'password', 'key', 'secret', 'authorization'];
        array_walk_recursive($context, static function (&$value, $key) use ($secretKeys): void {
            foreach ($secretKeys as $secretKey) {
                if (stripos((string) $key, $secretKey) !== false) {
                    $value = '[REDACTED]';
                    break;
                }
            }
        });

        return $context;
    }
}
