<?php

namespace App\Services\OnlyOffice;

class JwtSigner
{
    public function __construct(private readonly string $secret) {}

    public function encode(array $payload): string
    {
        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $body = $this->base64UrlEncode((string) json_encode($payload));
        $signature = $this->sign("{$header}.{$body}");

        return "{$header}.{$body}.{$signature}";
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;

        if (! hash_equals($this->sign("{$header}.{$body}"), $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($body), true);

        return is_array($payload) ? $payload : null;
    }

    private function sign(string $input): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $input, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
