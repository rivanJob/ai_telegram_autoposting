<?php

declare(strict_types=1);

namespace Autoposter\Services;

use ParagonIE\ConstantTime\Base32;

final class TotpService
{
    public function generateSecret(): string
    {
        return rtrim(Base32::encodeUpper(random_bytes(20)), '=');
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $timestamp = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->codeAt($secret, $timestamp + $i), preg_replace('/\s+/', '', $code))) {
                return true;
            }
        }
        return false;
    }

    public function codeAt(string $secret, int $counter): string
    {
        $key = Base32::decodeUpper($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function otpauthUri(string $issuer, string $email, string $secret): string
    {
        return sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30', rawurlencode($issuer), rawurlencode($email), $secret, rawurlencode($issuer));
    }
}
