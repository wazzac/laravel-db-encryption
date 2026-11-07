<?php

use Wazza\DbEncrypt\Facades\DbEncrypt;
use Wazza\DbEncrypt\Helper\Encryptor;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;
use Wazza\DbEncrypt\Exceptions\EncryptionException;
use Wazza\DbEncrypt\Exceptions\DecryptionException;
use PHPUnit\Framework\Assert;

it('correctly hashes the value', function () {
    $value = "test_value";
    $hashed = Encryptor::hash($value);

    // Verify if it returns a valid 64 character hex hash, which fits the hash_index column
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
    $this->expectException(InvalidAttributeException::class);
    Encryptor::encrypt(null);
});

it('throws on null input for decrypt', function () {
    $this->expectException(InvalidAttributeException::class);
    Encryptor::decrypt(null);
});

it('throws on invalid base64 for decrypt', function () {
    $this->expectException(DecryptionException::class);
    Encryptor::decrypt('not_base64!');
});

it('throws on missing key in config', function () {
    // Temporarily override config
    $original = config('db-encrypt.key');
    config(['db-encrypt.key' => null]);
    try {
        $this->expectException(EncryptionException::class);
        Encryptor::encrypt('test');
    } finally {
        config(['db-encrypt.key' => $original]);
    }
});

it('writes the versioned authenticated format', function () {
    $encrypted = Encryptor::encrypt('secret');

    expect($encrypted)->toStartWith('v2:');
    expect(Encryptor::isLegacyPayload($encrypted))->toBeFalse();
});

it('round trips empty strings and multibyte values', function (string $value) {
    expect(Encryptor::decrypt(Encryptor::encrypt($value)))->toBe($value);
})->with(['', 'naïve 日本語 🔐', "line\nbreak\0null"]);

it('uses a 256 bit derived key rather than a truncated hex digest', function () {
    $encrypted = Encryptor::encrypt('secret');
    $raw = base64_decode(substr($encrypted, 3), true);

    // Decrypting with the v1.0 key material (32 hex characters, so 128 bits of key) must fail
    $weakKey = substr(hash('sha512', config('db-encrypt.key')), 0, 32);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $weakKey, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

    expect($plain)->toBeFalse();
});

it('rejects tampered ciphertext', function () {
    $raw = base64_decode(substr(Encryptor::encrypt('secret'), 3), true);
    $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

    Encryptor::decrypt('v2:' . base64_encode($raw));
})->throws(DecryptionException::class);

it('rejects a value decrypted under a different context', function () {
    $encrypted = Encryptor::encrypt('secret', null, 'row-1');

    expect(Encryptor::decrypt($encrypted, null, 'row-1'))->toBe('secret');

    Encryptor::decrypt($encrypted, null, 'row-2');
})->throws(DecryptionException::class);

it('rejects a wrong manual key', function () {
    $encrypted = Encryptor::encrypt('secret', 'tenant-1');

    expect(Encryptor::decrypt($encrypted, 'tenant-1'))->toBe('secret');

    Encryptor::decrypt($encrypted, 'tenant-2');
})->throws(DecryptionException::class);

it('throws a decryption exception on truncated payloads instead of an openssl warning', function (string $payload) {
    Encryptor::decrypt($payload);
})->throws(DecryptionException::class)->with([
    'short v2' => ['v2:' . base64_encode('short')],
    'empty v2' => ['v2:'],
    'bad base64 v2' => ['v2:not_base64!'],
    'short legacy' => [base64_encode('short')],
    'iv only legacy' => [base64_encode(str_repeat('a', 16))],
]);

it('decrypts values encrypted with a previous key and writes with the current key', function () {
    $old = Encryptor::encrypt('secret');

    config(['db-encrypt.previous_keys' => [config('db-encrypt.key')], 'db-encrypt.key' => 'a-brand-new-key']);
    expect(Encryptor::decrypt($old))->toBe('secret');

    $new = Encryptor::encrypt('secret');

    config(['db-encrypt.previous_keys' => []]);
    expect(Encryptor::decrypt($new))->toBe('secret');

    Encryptor::decrypt($old);
})->throws(DecryptionException::class);

it('reads and searches a frozen vector produced by the real v1.0.0 code', function () {
    config(['db-encrypt.key' => 'fake-key-for-v1-test-vector']);

    $payload = 'jk0+9d55NbpuTbtAU1HgXcpyoE2ZRO/LJDFgXsnf55OgJCEPFEecMpMfsOPBiG6t';
    $manual = 'COQf3eUWvebNWNCqkXghDca6Y4YMFGVub7pQxPNs9Rs=';
    $hash = '484f29a3a1df7fbe4a95d645f8cee03d86238f3d5ebd083014eabe5a218e0570';

    expect(Encryptor::decrypt($payload))->toBe('123-45-6789 naïve');
    expect(Encryptor::decrypt($manual, 'tenant-1'))->toBe('tenant secret');
    expect(Encryptor::hashCandidates('123-45-6789 naïve'))->toContain($hash);
});

it('reads v1.0 payloads, including with a previous key', function () {
    $legacy = legacyEncrypt('old secret');

    expect(Encryptor::isLegacyPayload($legacy))->toBeTrue();
    expect(Encryptor::decrypt($legacy))->toBe('old secret');

    config(['db-encrypt.previous_keys' => [config('db-encrypt.key')], 'db-encrypt.key' => 'a-brand-new-key']);
    expect(Encryptor::decrypt($legacy))->toBe('old secret');
});

it('refuses v1.0 payloads when legacy support is off', function () {
    $legacy = legacyEncrypt('old secret');
    config(['db-encrypt.legacy_support' => false]);

    Encryptor::decrypt($legacy);
})->throws(DecryptionException::class, 'legacy');

it('keys the search hash so it cannot be computed without the key', function () {
    $hash = Encryptor::hash('123-45-6789');

    expect($hash)->not->toBe(legacyHash('123-45-6789'));

    config(['db-encrypt.key' => 'a-different-key']);
    expect(Encryptor::hash('123-45-6789'))->not->toBe($hash);
});

it('separates search hashes by context and ignores surrounding whitespace', function () {
    expect(Encryptor::hash('value', 'a'))->not->toBe(Encryptor::hash('value', 'b'));
    expect(Encryptor::hash(' value ', 'a'))->toBe(Encryptor::hash('value', 'a'));
});

it('requires a key to hash', function () {
    config(['db-encrypt.key' => null]);

    Encryptor::hash('value');
})->throws(EncryptionException::class);

it('offers hash candidates for the current key, previous keys and the legacy hash', function () {
    $oldHash = Encryptor::hash('value');

    config(['db-encrypt.previous_keys' => [config('db-encrypt.key')], 'db-encrypt.key' => 'a-brand-new-key']);

    expect(Encryptor::hashCandidates('value'))->toBe([Encryptor::hash('value'), $oldHash, legacyHash('value')]);

    config(['db-encrypt.legacy_support' => false]);
    expect(Encryptor::hashCandidates('value'))->toBe([Encryptor::hash('value'), $oldHash]);
});

it('verifies values without throwing', function () {
    $encrypted = Encryptor::encrypt('secret', 'tenant', 'context');

    expect(Encryptor::verify('secret', $encrypted, 'tenant', 'context'))->toBeTrue();
    expect(Encryptor::verify('other', $encrypted, 'tenant', 'context'))->toBeFalse();
    expect(Encryptor::verify('secret', $encrypted))->toBeFalse();
    expect(Encryptor::verify('secret', 'garbage'))->toBeFalse();
});

it('batches values and passes null and empty through', function () {
    $encrypted = Encryptor::encryptBatch(['a' => 'one', 'b' => null, 'c' => '']);

    expect($encrypted['a'])->toStartWith('v2:');
    expect($encrypted['b'])->toBeNull();
    expect($encrypted['c'])->toBe('');
    expect(Encryptor::decryptBatch($encrypted))->toBe(['a' => 'one', 'b' => null, 'c' => '']);
});

it('reports support and configuration without exposing keys', function () {
    config(['db-encrypt.previous_keys' => ['an-old-key']]);

    $info = Encryptor::getInfo();

    expect(Encryptor::isSupported())->toBeTrue();
    expect($info)->toMatchArray([
        'method' => 'aes-256-gcm',
        'supported' => true,
        'key_configured' => true,
        'previous_keys' => 1,
        'legacy_support' => true,
    ]);
    expect(json_encode($info))->not->toContain(config('db-encrypt.key'))->not->toContain('an-old-key');
});

it('exposes the encryptor through the facade', function () {
    $encrypted = DbEncrypt::encrypt('secret');

    expect(DbEncrypt::decrypt($encrypted))->toBe('secret');
    expect(DbEncrypt::verify('secret', $encrypted))->toBeTrue();
    expect(DbEncrypt::hash('secret'))->toBe(Encryptor::hash('secret'));
});
