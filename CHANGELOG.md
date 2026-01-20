# Changelog

All notable changes to `laravel-db-encryption` will be documented in this file.

## v1.0.0 `TBD` - Production Release 🚀

### Added

- **Custom Exception Classes** for better error handling and debugging
  - `EncryptionException` for encryption failures
  - `DecryptionException` for decryption failures
  - `InvalidAttributeException` for attribute configuration errors
  - `ModelNotSetException` for missing model errors
- **Facade Support** (`DbEncrypt`) for easy access to encryption methods throughout application
- **Batch Operations** for improved performance
  - `encryptBatch()` method for encrypting multiple values at once
  - `decryptBatch()` method for bulk decryption
- **Additional Security Methods**
  - `verify()` method to compare plaintext with encrypted values without full decryption
  - `isSupported()` method to check OpenSSL availability
  - `getInfo()` method to retrieve encryption configuration details
- **Artisan Commands**
  - `db-encrypt:generate-key` - Generate and set encryption keys
  - `db-encrypt:re-encrypt` - Key rotation with batch processing and dry-run support
  - `db-encrypt:prune` - Remove orphaned encrypted attributes
- **Comprehensive Test Suite**
  - Feature tests for full encryption workflow
  - Tests for `whereEncrypted` scope
  - Tests for batch operations
  - Tests for exception handling
- **GitHub Actions CI/CD** workflow for automated testing across PHP 8.2/8.3
- **SECURITY.md** with detailed security guidelines and vulnerability reporting
- **Enhanced Documentation**
  - Comprehensive README with usage examples
  - Troubleshooting guide
  - Production deployment best practices
  - Performance optimization tips
  - Monitoring and logging instructions

### Changed

- Improved error messages with more context and actionable information
- Optimized database queries with proper composite indexing
- Better logging with configurable levels (0-3)
- Updated PHPDoc blocks for better IDE support and type hints
- Cleaned up unused imports and dependencies
- Laravel 12 full support

### Fixed

- Better handling of null/empty values in encrypted attributes
- Improved exception handling in trait event listeners
- Fixed potential memory issues in batch operations
- Proper cleanup of buffer after save operations

### Security

- Enhanced key derivation with SHA-512
- Added timing-attack resistant comparison (`hash_equals`)
- Improved validation of encrypted data format
- Better isolation between encrypted attributes and table columns
- Comprehensive security documentation

## v0.2-beta `2025-06-17`

First Beta release of the package. 🥳

### Added

- Laravel 11 support.
- Improved encryption key rotation command.
- Configurable encryption cipher via config file.
- Automatic casting of encrypted attributes.
- Expanded test coverage for edge cases.

### Fixed

- Resolved issues with model event listeners not triggering on encrypted attributes.
- Fixed bug with database connection selection during encryption.

### Changed

- Updated documentation for new features and configuration options.
- Refactored encryption trait for better performance and maintainability.

## v0.1.0-alpha `2025-06-14`

Initial Alpha release. The package is functional, with documentation and unit tests included.

### Added

- Complete package scaffolding and setup.
- Database migration for encrypted data storage.
- Encryption table Eloquent model.
- Reusable trait for model property/attribute encryption.
- Core encryption controller.
- Comprehensive documentation, including installation and usage guides.
- Configuration file for package customization.
