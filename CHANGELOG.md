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
