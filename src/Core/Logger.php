<?php

declare(strict_types=1);

namespace AutoPoster\Core;

final class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $record = [
            'ts' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::redact($context),
        ];

        error_log(json_encode($record, JSON_UNESCAPED_SLASHES));
    }

    private static function redact(array $context): array
    {
        $redacted = [];
        foreach ($context as $k => $v) {
            $lk = strtolower((string)$k);
            if (str_contains($lk, 'token') || str_contains($lk, 'key') || str_contains($lk, 'pass') || str_contains($lk, 'secret')) {
                $redacted[$k] = '[REDACTED]';
                continue;
            }
            $redacted[$k] = $v;
        }
        return $redacted;
    }
}
