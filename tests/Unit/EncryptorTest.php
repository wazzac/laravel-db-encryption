<?php

use Wazza\DbEncrypt\Helper\Encryptor;
use PHPUnit\Framework\Assert;

it('correctly hashes the value', function () {
    $value = "test_value";
    $hashed = Encryptor::hash($value);

    // Verify if it returns a valid sha256 hash (64 characters hex)
    Assert::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashed);
});

it('correctly encrypts and decrypts data', function () {
    $value = "test_data";
    $encrypted = Encryptor::encrypt($value);
    $decrypted = Encryptor::decrypt($encrypted);

    // Verify if the decrypted value is same as original
    Assert::assertEquals($value, $decrypted);
});

it('returns different ciphertext for same plaintext (random IV)', function () {
    $value = "repeatable";
    $encrypted1 = Encryptor::encrypt($value);
    $encrypted2 = Encryptor::encrypt($value);
    Assert::assertNotEquals($encrypted1, $encrypted2, 'Ciphertext should differ due to random IV');
});

it('throws on null input for encrypt', function () {
    $this->expectException(InvalidArgumentException::class);
    Encryptor::encrypt(null);
});

it('throws on null input for decrypt', function () {
    $this->expectException(InvalidArgumentException::class);
    Encryptor::decrypt(null);
});

it('throws on invalid base64 for decrypt', function () {
    $this->expectException(RuntimeException::class);
    Encryptor::decrypt('not_base64!');
});

it('throws on missing key in config', function () {
    // Temporarily override config
    $original = config('db-encrypt.key');
    config(['db-encrypt.key' => null]);
    try {
        $this->expectException(RuntimeException::class);
        Encryptor::encrypt('test');
    } finally {
        config(['db-encrypt.key' => $original]);
    }
});
