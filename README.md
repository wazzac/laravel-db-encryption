<p align="center">
    <a href="https://github.com/wazzac/laravel-db-encryption/actions"><img alt="GitHub Actions" src="https://github.com/wazzac/laravel-db-encryption/workflows/Tests/badge.svg"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption"><img alt="GitHub version" src="https://img.shields.io/github/v/tag/wazzac/laravel-db-encryption?label=version&sort=semver"></a>
    <a href="https://coff.ee/wazzac"><img alt="Buy me a coffee" src="https://img.shields.io/badge/Buy%20me%20a%20coffee-☕-yellow?style=flat&logo=buy-me-a-coffee&logoColor=white"></a>
</p>

# Laravel DB Encryption

A production-ready Laravel package for **secure, transparent encryption** of sensitive model attributes. Encrypted values live in a dedicated table, so your main tables stay clean and never hold the sensitive data.

## 🔐 Why This Package?

- **Transparent encryption**: an Eloquent trait encrypts on save and decrypts on retrieve
- **Separate storage**: values are stored in a dedicated `encrypted_attributes` table, not on the model's table
- **Authenticated**: AES-256-GCM detects tampering, and each value is bound to its row, so a ciphertext copied onto another record won't decrypt
- **Searchable**: exact-match search through keyed HMAC-SHA256 hash indexes
- **Key rotation**: read with old keys while re-encrypting with a new one, with no downtime
- **Compliance support**: can help with GDPR, HIPAA and PCI DSS requirements. Key management and access control remain your responsibility

## 📋 Requirements

- **PHP**: 8.2 or higher (8.3 or higher for Laravel 13)
- **Laravel**: 12.x or 13.x
- **OpenSSL**: PHP OpenSSL extension

## 🚀 Installation

### 1. Install via Composer

```bash
composer require wazza/laravel-db-encryption
```

### 2. Generate Encryption Key

```bash
php artisan db-encrypt:generate-key
```

This adds `DB_ENCRYPT_KEY` to your `.env` file. Back this key up somewhere safe: without it, encrypted data can't be recovered.

### 3. Set the Primary Key Format (UUID or ULID models only)

The `object_id` column type is chosen when the migration runs, and applies to every encrypted model. If your models use UUIDs or ULIDs, set this **before migrating**:

```dotenv
DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT=uuid
```

### 4. Run Migrations

```bash
php artisan migrate
```

The package loads its migration automatically. Publishing is optional, for when you want to customize the config or migration:

```bash
php artisan vendor:publish --tag="db-encrypt-config"
php artisan vendor:publish --tag="db-encrypt-migrations"
```

## 📖 Usage

### Basic Setup

Add the `HasEncryptedAttributes` trait to your model and list the attributes to encrypt:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Wazza\DbEncrypt\Traits\HasEncryptedAttributes;

class User extends Model
{
    use HasEncryptedAttributes;

    // Encrypted attributes must be listed here to be mass assignable.
    protected $fillable = ['name', 'email', 'social_security_number', 'credit_card_number'];

    // Decrypted values are normal attributes, so hide them from arrays and JSON.
    protected $hidden = ['social_security_number', 'credit_card_number'];

    /**
     * Attributes that should be encrypted.
     * These MUST NOT exist as columns in your table!
     */
    public array $encryptedProperties = [
        'social_security_number',
        'credit_card_number',
    ];
}
```

> **Decrypted values appear in `toArray()` and JSON unless you hide them.** Add sensitive attributes to `$hidden`, as above, so they can't leak through an API response.

### Working with Encrypted Attributes

```php
// Create a user with encrypted attributes
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'social_security_number' => '123-45-6789',
    'credit_card_number' => '4111-1111-1111-1111',
]);

// Encrypted attributes work like normal properties
echo $user->social_security_number; // '123-45-6789'

// Update encrypted attributes
$user->social_security_number = '987-65-4321';
$user->save();

// Retrieve and use
$user = User::find(1);
echo $user->social_security_number; // Automatically decrypted

// Setting null or an empty string removes the stored value
$user->social_security_number = null;
$user->save();
```

Dirty tracking works as usual: a retrieved model isn't dirty, and only values that changed are re-encrypted on save.

### Casts, Mutators and Accessors

Encrypted attributes support Eloquent casts, mutators and accessors. The raw attribute value is what gets encrypted, so a mutator runs once when you set the value, and an accessor's output is never stored.

```php
protected function casts(): array
{
    return [
        'medical_history' => 'array',
        'age' => 'integer',
        'is_verified' => 'boolean',
    ];
}
```

Values are stored as strings, so an attribute holding an array or object needs a cast. Without one, saving throws `InvalidAttributeException`.

### Searching Encrypted Data

```php
// Find users by encrypted SSN
$users = User::whereEncrypted('social_security_number', '123-45-6789')->get();

// Match any of several values
$users = User::whereEncryptedIn('social_security_number', ['123-45-6789', '987-65-4321'])->get();

// Combine with other queries
$users = User::where('email', 'like', '%@example.com')
    ->whereEncrypted('social_security_number', '123-45-6789')
    ->get();

// OR conditions
$users = User::where('name', 'John Doe')
    ->orWhereEncrypted('social_security_number', '123-45-6789')
    ->get();
```

Search is **exact-match and case-sensitive**, ignoring only surrounding whitespace. Partial matches, sorting and range queries aren't possible. The attribute must be listed in `$encryptedProperties`, otherwise the scope throws `InvalidAttributeException`. The model must use the same database connection as the `encrypted_attributes` table.

### Deleting

Deleting a model removes its encrypted rows. With `SoftDeletes`, the rows stay through a soft delete so the model can be restored, and are removed by `forceDelete()`.

Bulk query deletes such as `User::where(...)->delete()` don't fire model events, so they leave rows behind. Use `db-encrypt:prune` to clean those up.

### Using the Facade

For direct encryption and decryption of your own values:

```php
use Wazza\DbEncrypt\Facades\DbEncrypt;

// Encrypt and decrypt a value
$encrypted = DbEncrypt::encrypt('sensitive data');
$decrypted = DbEncrypt::decrypt($encrypted);

// Bind a value to a context. Decrypting requires the same context.
$encrypted = DbEncrypt::encrypt('sensitive data', null, 'invoice:42');
$decrypted = DbEncrypt::decrypt($encrypted, null, 'invoice:42');

// Generate a keyed search hash, optionally scoped to a context
$hash = DbEncrypt::hash('search value');
$hash = DbEncrypt::hash('search value', 'my-scope');

// Batch operations
$encrypted = DbEncrypt::encryptBatch([
    'ssn' => '123-45-6789',
    'cc' => '4111-1111-1111-1111',
]);
$decrypted = DbEncrypt::decryptBatch($encrypted);

// Compare in constant time. This decrypts internally, and returns false on any failure.
$isMatch = DbEncrypt::verify('original text', $encrypted['ssn']);
```

### What Bypasses Encryption

The trait works through model events, so anything that skips them skips encryption:

- `saveQuietly()`, `updateQuietly()` and `Model::withoutEvents()`
- Query builder writes such as `insert()`, `upsert()` and `update()`

Because encrypted attributes have no column, writing one this way fails with a database error rather than storing plaintext. Models retrieved inside `withoutEvents()` aren't decrypted.

If a model's encrypted values must be saved atomically with the model itself, use `saveOrFail()` or your own `DB::transaction()`. A plain `save()` writes the model first and the encrypted values straight after.

### Performance

Each retrieved model runs one query against `encrypted_attributes`, however many encrypted attributes it has. Loading 1,000 models therefore runs 1,000 extra queries. When you don't need the encrypted values, skip them:

```php
$names = User::withoutDecryption(fn () => User::all()->pluck('name'));
```

Saving a model loaded this way leaves its encrypted rows untouched.

## 🛠️ Advanced Features

### Artisan Commands

#### Generate Encryption Key

```bash
# Generate and set a new key in .env
php artisan db-encrypt:generate-key

# Just display a key without saving it
php artisan db-encrypt:generate-key --show

# Replace the key, keeping the old one as a previous key (see Key Rotation)
php artisan db-encrypt:generate-key --rotate

# Replace the key without asking. Existing data becomes unreadable!
php artisan db-encrypt:generate-key --force
```

#### Re-encrypt Data

Rewrites stored values with the current key and format.

```bash
# Re-encrypt all encrypted attributes
php artisan db-encrypt:re-encrypt

# Check every value can be decrypted, without changing anything
php artisan db-encrypt:re-encrypt --dry-run

# Re-encrypt one table or model only
php artisan db-encrypt:re-encrypt --table=users
php artisan db-encrypt:re-encrypt --model="App\Models\User"

# Process in smaller batches, skipping the prompt
php artisan db-encrypt:re-encrypt --batch=50 --force
```

#### Prune Orphaned Records

Removes encrypted rows whose parent record no longer exists. Tables that can't be read are skipped and their rows are kept.

```bash
php artisan db-encrypt:prune

# Dry run
php artisan db-encrypt:prune --dry-run

# Prune one table. Use --key when its primary key isn't `id`
php artisan db-encrypt:prune --table=users
php artisan db-encrypt:prune --table=products --key=sku

# Prune one model, using its table, primary key and connection
php artisan db-encrypt:prune --model="App\Models\User"
```

Without `--model` or `--key`, the parent key column is assumed to be `id`. A table with a different primary key is skipped with a warning.

#### Rename a Table

Stored values are bound to their table name, so renaming a table takes more than updating `object_type`. Run this alongside your rename migration:

```bash
php artisan db-encrypt:rename-table old_table new_table
```

#### Check Status

Shows the configuration and how many values each attribute has, including any still in the legacy format. It never shows keys or values.

```bash
php artisan db-encrypt:status
```

### Key Rotation

```bash
# 1. Back up your database and your current key

# 2. Generate a new key. The old one moves to DB_ENCRYPT_PREVIOUS_KEYS.
#    Reads and searches keep working throughout.
php artisan db-encrypt:generate-key --rotate

# 3. If you cache your config, refresh it
php artisan config:cache

# 4. Rewrite the data with the new key
php artisan db-encrypt:re-encrypt

# 5. Confirm this succeeded, then remove DB_ENCRYPT_PREVIOUS_KEYS from .env
```

The command is safe to run again if it's interrupted. Rotation protects data from a compromised key going forward, but old backups can still be read with the old key.

### Upgrading from v1.0

v1.1.0 replaces the v1.0 encryption format and search hash, because the old ones had security weaknesses (see the [changelog](CHANGELOG.md)). Existing data keeps working without any changes: old values can still be read and searched, and new values use the new format.

To finish the upgrade:

```bash
# 1. Back up your database, then rewrite existing values in the new format
php artisan db-encrypt:re-encrypt

# 2. Confirm no legacy values remain
php artisan db-encrypt:status
```

```dotenv
# 3. Turn off legacy support
DB_ENCRYPT_LEGACY_SUPPORT=false
```

Keep legacy support off once you've upgraded: while it's on, someone with write access to your database can submit unauthenticated payloads.

> **Rolling back:** v1.0 can't read values written by v1.1. Take a backup before upgrading if you might need to roll back.

### Configuration

Publish `config/db-encrypt.php` to edit it, or use these environment variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_ENCRYPT_KEY` | none | **Required.** The encryption key |
| `DB_ENCRYPT_PREVIOUS_KEYS` | empty | Comma-separated old keys, used only for reading and search during rotation |
| `DB_ENCRYPT_LEGACY_SUPPORT` | `true` | Accept data written by v1.0 |
| `DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT` | `int` | `int`, or `uuid` for UUIDs and ULIDs. Read when the migration runs |
| `DB_ENCRYPT_LOG_LEVEL` | `0` | `0` none, `1` high, `2` mid, `3` low |
| `DB_ENCRYPT_LOG_INDICATOR` | `db-encrypt` | Prefix on log lines, for filtering |

### Exceptions

| Exception | Thrown when |
| --- | --- |
| `EncryptionException` | The key is missing, or a value can't be encrypted or stored |
| `DecryptionException` | A value can't be decrypted: wrong key, corruption or tampering |
| `InvalidAttributeException` | An encrypted attribute is also a column, isn't listed in `$encryptedProperties`, can't be stored as a string, or is too large for the column |
| `ModelNotSetException` | The controller is used without a model, or with an unsaved one |

```php
use Wazza\DbEncrypt\Exceptions\EncryptionException;
use Wazza\DbEncrypt\Facades\DbEncrypt;

try {
    $encrypted = DbEncrypt::encrypt($value);
} catch (EncryptionException $e) {
    // Handle encryption failure
    Log::error('Encryption failed: ' . $e->getMessage());
}
```

A value that can't be decrypted throws `DecryptionException` when the model is retrieved, so one bad row fails the whole query that loaded it.

## 🔒 Security Best Practices

### 1. Key Management

- **Never** commit encryption keys to version control
- Use a different key for each environment
- Rotate keys periodically (see [Key Rotation](#key-rotation))
- Consider a key management service (KMS) in production

### 2. What to Encrypt

✅ **Good candidates:**
- Government ID numbers, such as Social Security numbers
- Medical records
- Bank account details
- Private notes

❌ **Bad candidates:**
- Passwords: hash these instead, with Laravel's `Hash` facade
- Primary keys or foreign keys
- Data you need to sort, filter by range, or calculate with

⚠️ **Take care with search on low-entropy values.** Anyone with your key can guess values with few possibilities, such as yes/no fields. Without the key, the search hash still shows which rows hold the same value for an attribute.

If you store card numbers, encryption alone doesn't make you PCI DSS compliant. Prefer a payment provider's tokens.

### 3. Values Are Encrypted at Rest Only

Once decrypted, values are ordinary attributes in memory. Keep them out of logs and responses, use `$hidden`, and restrict who can reach the data through your application.

### 4. Size Limits

On MySQL and MariaDB, `encrypted_value` is a `TEXT` column holding 65,535 bytes after encryption and encoding, which is about 49 KB of plaintext. Larger values throw `InvalidAttributeException` rather than being truncated.

### 5. Backup Strategy

- Always back up before key rotation
- Test key rotation in staging first
- Keep old keys until you've verified re-encryption succeeded

See [SECURITY.md](SECURITY.md) for more security guidance.

## 🧪 Testing

```bash
# Run all tests
composer test

# Run a specific test file
./vendor/bin/pest tests/Unit/EncryptorTest.php

# Run with coverage (needs a coverage driver such as Xdebug or PCOV)
composer test-coverage
```

## 🐛 Troubleshooting

### "Encryption key is not set"

Run `php artisan db-encrypt:generate-key`, or set `DB_ENCRYPT_KEY` in `.env`. If you cache your config, run `php artisan config:cache` afterwards.

### "Cannot encrypt attribute 'name' because it already exists as a column"

Encrypted attributes must not exist as database columns. To move existing data out of a column, read each value, drop the column, then save the values through the model.

### "Failed to decrypt encrypted attributes" or "Failed to decrypt the data"

**Possible causes:**
- The wrong encryption key, or a rotated key that isn't in `DB_ENCRYPT_PREVIOUS_KEYS`
- Corrupted or tampered data
- `object_type`, `object_id` or `attribute` changed directly in the database. Use `db-encrypt:rename-table` to rename tables

**To investigate:**
1. Verify `DB_ENCRYPT_KEY` and `DB_ENCRYPT_PREVIOUS_KEYS`
2. Run `php artisan db-encrypt:re-encrypt --dry-run` to list the affected rows
3. Restore from backup if the data is corrupted

### "The encrypted data uses the legacy v1.0 format"

`DB_ENCRYPT_LEGACY_SUPPORT` is off but old values remain. Turn it on, run `db-encrypt:re-encrypt`, then turn it off again.

### Encrypted attributes are saved as null or ignored

Mass assignment dropped them. Add the attributes to `$fillable`. A non-empty `$guarded` list such as `['id']` isn't enough, because Laravel then rejects attributes that have no matching column.

### Performance issues

- Skip decryption when you don't need the values, with `withoutDecryption()`
- Set `DB_ENCRYPT_LOG_LEVEL=0` in production
- Only encrypt attributes that are truly sensitive

### Search not finding records

- Use the `whereEncrypted()` scope
- Check the exact value, since search is case-sensitive
- If you changed `DB_ENCRYPT_KEY` without `--rotate`, old values can't be found. Add the old key to `DB_ENCRYPT_PREVIOUS_KEYS`

## 📊 Monitoring

### View Package Logs

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log | grep db-encrypt

# Search for errors
grep "DB Encrypt: Failed" storage/logs/laravel.log
```

Failures to encrypt or decrypt a model's attributes are always logged. `DB_ENCRYPT_LOG_LEVEL` controls everything else. Plaintext values are never logged.

### Check Encryption Status

Run `php artisan db-encrypt:status`, or check from code:

```php
use Wazza\DbEncrypt\Helper\Encryptor;

$info = Encryptor::getInfo();
/*
[
    'method' => 'aes-256-gcm',
    'hash_algorithm' => 'hkdf-sha256',
    'search_hash_algorithm' => 'hmac-sha256',
    'iv_length' => 12,
    'supported' => true,
    'key_configured' => true,
    'previous_keys' => 0,
    'legacy_support' => true,
]
*/

// Check if encryption is supported
if (!Encryptor::isSupported()) {
    throw new Exception('OpenSSL or AES-256-GCM is not available');
}
```

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## 📜 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 👨‍💻 Author

**Warren Coetzee**
- Website: [wazzac.dev](https://www.wazzac.dev)
- Email: warren.coetzee@gmail.com
- GitHub: [@wazzac](https://github.com/wazzac)

## 🙏 Acknowledgments

- Built for the Laravel community
- Inspired by the need for simple, secure database encryption
- Thanks to all contributors and users

## ☕ Support

If this package helps you, consider [buying me a coffee](https://coff.ee/wazzac)!

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently.

## 🛡️ Security

Please review our [security policy](SECURITY.md) for reporting security vulnerabilities.

---

**Made with ❤️ for the Laravel community**
