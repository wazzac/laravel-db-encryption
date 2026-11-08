# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

The security of this package is taken very seriously. If you discover a security vulnerability, please follow these steps:

### 1. **DO NOT** Open a Public Issue

Please do not report security vulnerabilities through public GitHub issues. This could put all users at risk.

### 2. Email Security Report

Send a detailed report to: **warren.coetzee@gmail.com**

Include the following information:
- Type of vulnerability
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### 3. Response Timeline

- **Initial Response**: Within 48 hours, you'll receive acknowledgment of your report
- **Status Update**: Within 7 days, we'll provide a detailed response including:
  - Confirmation of the vulnerability
  - Planned fix timeline
  - Any workarounds or mitigations
- **Resolution**: We aim to release security patches within 30 days for critical vulnerabilities

### 4. Disclosure Policy

- We follow a coordinated disclosure approach
- We request that you give us reasonable time to fix the vulnerability before public disclosure
- We will credit you in the security advisory (unless you prefer to remain anonymous)
- Once patched, we'll publish a security advisory on GitHub

## Security Best Practices

When using this package:

### 1. Key Management

- **Never** commit your `DB_ENCRYPT_KEY` to version control
- Store encryption keys in environment variables (`.env` file)
- Use different keys for development, staging, and production
- Rotate encryption keys periodically: run `db-encrypt:generate-key --rotate`, then `db-encrypt:re-encrypt`, then remove `DB_ENCRYPT_PREVIOUS_KEYS`
- Consider using a key management service (KMS) for production environments
- After upgrading from v1.0, run `db-encrypt:re-encrypt` and set `DB_ENCRYPT_LEGACY_SUPPORT=false`

### 2. Backup Strategy

- **Always** backup your data before key rotation
- Test key rotation in a staging environment first
- Keep old encryption keys secure until you're certain all data has been re-encrypted

### 3. Encrypted Attributes

- Only encrypt truly sensitive data (encryption has performance overhead)
- Never encrypt primary keys or foreign keys
- Don't encrypt attributes you need to sort by or perform mathematical operations on
- Use the `whereEncrypted` scope for searching encrypted data
- Add sensitive attributes to the model's `$hidden` array, because decrypted values are otherwise included in `toArray()` and JSON

### 4. Production Deployment

- Set logging level to 0 or 1 in production (`DB_ENCRYPT_LOG_LEVEL=0`)
- Monitor your `encrypted_attributes` table size
- Set up regular pruning with `db-encrypt:prune` to remove orphaned records
- Ensure OpenSSL extension is properly configured

### 5. Access Control

- Restrict database access to encrypted_attributes table
- Implement application-level access controls
- Log access to sensitive encrypted data
- Regular audit of who can access encryption keys

### 6. Compliance

This package can help meet compliance requirements for:
- **GDPR**: Right to erasure, data minimization
- **HIPAA**: Protected Health Information (PHI) encryption
- **PCI DSS**: Cardholder data encryption
- **CCPA**: Consumer data protection

However, proper key management and access controls are your responsibility.

## Known Security Considerations

### 1. Hash Index

- The `hash_index` column stores a keyed HMAC-SHA256 for searchability, scoped to the table and attribute
- Without the key, the hash can't be computed or brute-forced, but it does show which rows hold the same value for an attribute
- If few values are possible (e.g., gender: M/F), the groups of matching rows can reveal the values through frequency alone
- Consider not encrypting low-cardinality fields with this package, or accept that equality is visible
- Hashes written by v1.0 are unkeyed SHA-256 and can be brute-forced from a database copy until `db-encrypt:re-encrypt` replaces them. Older backups still hold them

### 2. Encryption Format

- Values use AES-256-GCM, with keys derived by HKDF-SHA256, and are bound to their table, record and attribute as authenticated data
- This detects tampering and stops a ciphertext being copied to another record. It doesn't stop an old ciphertext for the same attribute of the same record being restored
- While `DB_ENCRYPT_LEGACY_SUPPORT` is on, v1.0 AES-256-CBC payloads are accepted, and these have no authentication. Turn it off once `db-encrypt:status` reports no legacy values

### 3. Side-Channel Attacks

- Timing attacks are mitigated by using `hash_equals()` in verification
- Ensure your server is properly secured against other side-channel attacks

### 4. Key Compromise

- If your encryption key is compromised, **all** encrypted data is vulnerable
- Immediately rotate keys if you suspect compromise: run `db-encrypt:generate-key --rotate`, then `db-encrypt:re-encrypt`, then remove `DB_ENCRYPT_PREVIOUS_KEYS`
- Rotation doesn't protect existing backups, which can still be read with the old key
- Consider implementing monitoring for unusual access patterns

### 5. Decrypted Data

- Encryption protects data at rest in the `encrypted_attributes` table. Once a model is loaded, its values are plain attributes in memory
- Anything that can read your application's memory, environment or `.env` file can read the data

## Security Updates

Subscribe to security updates:
- Watch this repository for security advisories
- Star the repository to be notified of releases
- Join our security mailing list (coming soon)

## Bug Bounty

We currently don't have a formal bug bounty program, but we deeply appreciate security researchers who help keep our users safe. Significant vulnerabilities may be eligible for acknowledgment in our Hall of Fame and swag.

## Contact

For non-security-related issues, please use GitHub Issues.

For general questions: warren.coetzee@gmail.com
Website: https://www.wazzac.dev

---

**Thank you for helping keep Laravel DB Encryption and our users safe!**
