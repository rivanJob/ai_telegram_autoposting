<?php

declare(strict_types=1);

namespace AutoPoster\Security;

final class Totp
{
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $timeSlice = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::at($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function at(string $secret, int $timeSlice): string
    {
        $key = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $bits .= str_pad(decbin(strpos($alphabet, $secret[$i]) ?: 0), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $binary .= chr(bindec(substr($bits, $i, 8)));
        }
        return $binary;
    }
}
