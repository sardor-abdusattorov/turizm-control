<?php

use App\Services\OnlyOffice\JwtSigner;

it('round-trips a payload through encode and decode', function (): void {
    $signer = new JwtSigner('test-secret');

    $payload = ['key' => 'abc123', 'status' => 2, 'nested' => ['a' => 1]];
    $token = $signer->encode($payload);

    expect($signer->decode($token))->toBe($payload);
});

it('rejects a token signed with a different secret', function (): void {
    $signed = (new JwtSigner('secret-a'))->encode(['x' => 1]);

    expect((new JwtSigner('secret-b'))->decode($signed))->toBeNull();
});

it('rejects a tampered payload', function (): void {
    $signer = new JwtSigner('test-secret');
    $token = $signer->encode(['amount' => 100]);

    [$header, , $signature] = explode('.', $token);
    $forgedBody = rtrim(strtr(base64_encode(json_encode(['amount' => 999999])), '+/', '-_'), '=');
    $forged = "{$header}.{$forgedBody}.{$signature}";

    expect($signer->decode($forged))->toBeNull();
});

it('returns null for a malformed token', function (): void {
    $signer = new JwtSigner('test-secret');

    expect($signer->decode('not-a-jwt'))->toBeNull();
    expect($signer->decode('only.two'))->toBeNull();
});
