<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

/**
 * Self-contained RFC 6238 TOTP (time-based one-time password) generator/verifier
 * for MFA (FR-IAM-005, NFR-SE-009). No external dependency so the domain stays
 * provider-neutral; the shared secret is stored encrypted on the authenticator
 * (NFR-SE-015). Uses SHA-1 / 6 digits / 30-second steps (RFC 6238 defaults,
 * compatible with standard authenticator apps).
 */
final class TotpService
{
    private const int DIGITS = 6;

    private const int PERIOD = 30;

    /** Generate a random base32 secret (160-bit). */
    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $bytes = random_bytes(20);
        foreach (str_split($bytes) as $byte) {
            $secret .= $alphabet[ord($byte) & 31];
        }

        return $secret;
    }

    /** The current TOTP code for a secret. */
    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);

        return $this->hotp($secret, $counter);
    }

    /**
     * Verify a code, allowing a ±1 step window for clock drift (RFC 6238 §5.2).
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->hotp($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** otpauth:// URI for provisioning in an authenticator app. */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    private function hotp(string $base32Secret, int $counter): string
    {
        $key = $this->base32Decode($base32Secret);
        $binCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = rtrim(strtoupper($secret), '=');
        $bits = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $bytes;
    }
}
