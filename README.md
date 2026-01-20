<p align="center">
    <a href="https://github.com/wazzac/laravel-db-encryption/actions"><img alt="GitHub Actions" src="https://github.com/wazzac/laravel-db-encryption/workflows/Tests/badge.svg"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/laravel-db-encryption"></a>
    <a href="https://github.com/wazzac/laravel-db-encryption"><img alt="GitHub version" src="https://img.shields.io/github/v/tag/wazzac/laravel-db-encryption?label=version&sort=semver"></a>
    <a href="https://coff.ee/wazzac"><img alt="Buy me a coffee" src="https://img.shields.io/badge/Buy%20me%20a%20coffee-☕-yellow?style=flat&logo=buy-me-a-coffee&logoColor=white"></a>
</p>

# Laravel DB Encryption

A production-ready Laravel package for **secure, transparent encryption** of sensitive model attributes. Store encrypted data in a dedicated table while keeping your main tables clean and performant.

## 🔐 Why This Package?

- **Transparent Encryption**: Automatic encryption/decryption via Eloquent trait
- **Separate Storage**: Encrypted data stored in dedicated `encrypted_attributes` table
- **Searchable**: Filter encrypted data using SHA-256 hash indexes
- **Zero Configuration**: Works out of the box with sensible defaults
- **Production Ready**: Comprehensive error handling, logging, and security features
- **Performance**: Batch operations and optimized queries
- **Compliance Ready**: Helps meet GDPR, HIPAA, PCI DSS requirements

## 📋 Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 12.x
- **OpenSSL**: PHP OpenSSL extension

## 🚀 Installation

### 1. Install via Composer

```bash
composer require wazza/laravel-db-encryption
```

### 2. Publish Configuration & Migrations

```bash
# Publish config file
php artisan vendor:publish --provider="Wazza\DbEncrypt\Providers\DbEncryptServiceProvider" --tag="db-encrypt-config"

# Publish migrations
php artisan vendor:publish --provider="Wazza\DbEncrypt\Providers\DbEncryptServiceProvider" --tag="db-encrypt-migrations"
```

### 3. Generate Encryption Key

```bash
php artisan db-encrypt:generate-key
```

This adds `DB_ENCRYPT_KEY` to your `.env` file.

### 4. Run Migrations

```bash
php artisan migrate
```

## 📖 Usage

### Basic Setup

Add the `HasEncryptedAttributes` trait to your model and define which attributes should be encrypted:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Wazza\DbEncrypt\Traits\HasEncryptedAttributes;

class User extends Model
{
    use HasEncryptedAttributes;

    protected $fillable = ['name', 'email', 'phone'];

    /**
     * Attributes that should be encrypted.
     * These MUST NOT exist as columns in your table!
     */
    public array $encryptedProperties = [
        'social_security_number',
        'credit_card_number',
        'medical_record',
    ];
}
```

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
echo $user->social_security_number; // '123-45-6789' (automatically decrypted)

// Update encrypted attributes
$user->social_security_number = '987-65-4321';
$user->save();

// Retrieve and use
$user = User::find(1);
echo $user->social_security_number; // Automatically decrypted!
```

### Searching Encrypted Data

Use the `whereEncrypted` scope to search encrypted attributes:

```php
// Find users by encrypted SSN
$users = User::whereEncrypted('social_security_number', '123-45-6789')->get();

// Find specific user
$user = User::whereEncrypted('credit_card_number', '4111-1111-1111-1111')->first();

// Combine with other queries
$users = User::where('email', 'like', '%@example.com%')
    ->whereEncrypted('social_security_number', '123-45-6789')
    ->get();
```

### Using the Facade

For direct encryption/decryption operations:

```php
use Wazza\DbEncrypt\Facades\DbEncrypt;

// Encrypt a value
$encrypted = DbEncrypt::encrypt('sensitive data');

// Decrypt a value
$decrypted = DbEncrypt::decrypt($encrypted);

// Generate search hash
$hash = DbEncrypt::hash('search value');

// Batch operations
$encrypted = DbEncrypt::encryptBatch([
    'ssn' => '123-45-6789',
    'cc' => '4111-1111-1111-1111',
]);

// Verify without decrypting
$isMatch = DbEncrypt::verify('original text', $encryptedValue);
```

## 🛠️ Advanced Features

### Artisan Commands

#### Generate Encryption Key

```bash
# Generate and set new key in .env
php artisan db-encrypt:generate-key

# Just display the key without saving
php artisan db-encrypt:generate-key --show
```

#### Re-encrypt Data (Key Rotation)

```bash
# Re-encrypt all encrypted attributes
php artisan db-encrypt:re-encrypt

# Dry run to see what would happen
php artisan db-encrypt:re-encrypt --dry-run

# Re-encrypt specific table only
php artisan db-encrypt:re-encrypt --table=users

# Process in smaller batches
php artisan db-encrypt:re-encrypt --batch=50
```

#### Prune Orphaned Records

```bash
# Remove encrypted attributes for deleted models
php artisan db-encrypt:prune

# Dry run
php artisan db-encrypt:prune --dry-run

# Prune specific table
php artisan db-encrypt:prune --table=users
```

### Configuration

Edit `config/db-encrypt.php`:

```php
return [
    // Logging configuration
    'logging' => [
        'level' => env('DB_ENCRYPT_LOG_LEVEL', 0), // 0=None, 1=High, 2=Mid, 3=Low
        'indicator' => env('DB_ENCRYPT_LOG_INDICATOR', 'db-encrypt'),
    ],

    // Encryption key
    'key' => env('DB_ENCRYPT_KEY'),

    // Database configuration
    'db' => [
        'primary_key_format' => env('DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT', 'int'), // 'int' or 'uuid'
    ],
];
```

### Custom Exceptions

The package provides specific exceptions for better error handling:

```php
use Wazza\DbEncrypt\Exceptions\EncryptionException;
use Wazza\DbEncrypt\Exceptions\DecryptionException;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;
use Wazza\DbEncrypt\Exceptions\ModelNotSetException;

try {
    $encrypted = DbEncrypt::encrypt($value);
} catch (EncryptionException $e) {
    // Handle encryption failure
    Log::error('Encryption failed: ' . $e->getMessage());
}
```

## 🔒 Security Best Practices

### 1. Key Management

- **Never** commit encryption keys to version control
- Use different keys for each environment
- Rotate keys periodically using `db-encrypt:re-encrypt`
- Consider using a Key Management Service (KMS) in production

### 2. What to Encrypt

✅ **Good candidates:**
- Social Security Numbers
- Credit card numbers
- Passwords (though hashing is usually better)
- Medical records
- Personal identification numbers
- Private notes

❌ **Bad candidates:**
- Primary keys or foreign keys
- Data you need to sort by
- Data used in mathematical operations
- High-cardinality lookup values

### 3. Performance Considerations

- Encryption adds overhead—only encrypt truly sensitive data
- Use indexes on your main tables for performance
- Consider caching decrypted data for read-heavy operations
- Use batch operations when encrypting/decrypting multiple values

### 4. Backup Strategy

- Always backup before key rotation
- Test key rotation in staging first
- Keep old keys until you verify re-encryption succeeded

See [SECURITY.md](SECURITY.md) for comprehensive security guidelines.

## 🧪 Testing

```bash
# Run all tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage

# Run specific test file
./vendor/bin/pest tests/Unit/EncryptorTest.php
```

## 🐛 Troubleshooting

### "Encryption key is not set"

**Solution:** Run `php artisan db-encrypt:generate-key` or manually set `DB_ENCRYPT_KEY` in `.env`

### "Cannot encrypt attribute 'name' because it exists as a column"

**Solution:** Encrypted properties must NOT exist as database columns. Remove the column or choose a different property name.

### "Decryption failed"

**Possible causes:**
- Wrong encryption key
- Corrupted data
- Key was rotated but data wasn't re-encrypted

**Solution:**
1. Verify `DB_ENCRYPT_KEY` is correct
2. Check logs for detailed error messages
3. Restore from backup if data is corrupted

### Performance issues

**Solutions:**
- Ensure indexes are created on `encrypted_attributes` table (done automatically)
- Reduce logging level in production (`DB_ENCRYPT_LOG_LEVEL=0`)
- Use batch operations for multiple encryptions
- Consider caching frequently accessed encrypted data

### Search not finding records

**Check:**
- Ensure you're using `whereEncrypted()` scope
- Verify the exact value (encryption is case-sensitive)
- Check that hash_index was generated correctly

## 📊 Monitoring

### View Package Logs

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log | grep db-encrypt

# Search for errors
grep "db-encrypt.*error" storage/logs/laravel.log
```

### Check Encryption Status

```php
use Wazza\DbEncrypt\Helper\Encryptor;

// Get encryption info
$info = Encryptor::getInfo();
/*
[
    'method' => 'AES-256-CBC',
    'hash_algorithm' => 'sha512',
    'search_hash_algorithm' => 'sha256',
    'iv_length' => 16,
    'supported' => true,
    'key_configured' => true,
]
*/

// Check if encryption is supported
if (!Encryptor::isSupported()) {
    throw new Exception('OpenSSL not available');
}
```

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`./vendor/bin/pest`)
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
