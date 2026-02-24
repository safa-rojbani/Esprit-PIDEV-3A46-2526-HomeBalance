<?php

namespace App\Service\Security;

final class TokenCipherService
{
    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(16);
        $key = hash('sha256', $this->appSecret, true);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($cipher)) {
            throw new \RuntimeException('Unable to encrypt token.');
        }

        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if (!is_string($raw) || strlen($raw) < 17) {
            throw new \RuntimeException('Invalid encrypted token payload.');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = hash('sha256', $this->appSecret, true);

        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($plain)) {
            throw new \RuntimeException('Unable to decrypt token.');
        }

        return $plain;
    }
}

