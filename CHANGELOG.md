# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Added a permanent administrator audit log for business and technical changes, including the modifying user, timestamp, filters, and field-level change details.

### Changed
- Simplified inspection planning to use the purchase date, actual inspection date, and next planned inspection date.
- The inspection interval can be changed permanently when recording an actual inspection.
- Removed the separate inspection-start date from the active workflow.

## [0.3.1] - 2026-09-19

### Added
- Added equipment-type usage counts and edit/delete actions.
- Prevented deletion of equipment types still assigned to devices.

## [0.3.0] - 2026-09-19

### Changed
- Unified equipment category and equipment type into one extensible `Gerätetyp` list.
- Added equipment-type management for extending the dropdown without code changes.
- Added live filtering and clickable column sorting to the equipment list.

## [0.2.9] - 2026-09-19

### Fixed
- Unicode emoji codepoints are now converted to real UTF-8 emoji characters before rendering.
- The user form is hidden by default and opens through `Neuer Benutzer` or `Edit`.

## [0.2.8] - 2026-09-19

### Changed
- Replaced the emoji name dropdown with a visual, categorized emoji grid.
- Emoji names are now available as hover tooltips while selection is made by appearance.

## [0.2.7] - 2026-09-19

### Changed
- Replaced the fixed emoji list with the official current Unicode emoji catalog.
- Emoji selection is grouped by Unicode group and subgroup and refreshed during image builds.

## [0.2.6] - 2026-09-19

### Changed
- Reduced Docker Compose to the required PHP-FPM and Nginx services.
- Removed unused MariaDB, Redis, database mounts and custom network configuration from the MVP deployment.

## [0.2.5] - 2026-09-19

### Changed
- Replaced the top navigation with a shared responsive sidebar.
- Ensured all pages show the complete navigation, including Benutzer.

## [0.2.4] - 2026-09-19

### Added
- Added user management with first name, last name, email address and WhatsApp-style emoticon selection.
- Equipment can be assigned to users through a dropdown.
- Equipment list now shows the assigned emoticon, equipment type, manufacturer and equipment name in the first columns.
- Added a CLI reminder command that sends due-inspection emails to the assigned user's email address.

## [0.2.3] - 2026-09-19

### Added
- Added a per-device "Prüfung eintragen" action that stores the last inspection date.
- Next inspection dates are calculated automatically from the last inspection and interval in days.

## [0.2.2] - 2026-09-19

### Fixed
- PHP-FPM now uses the required non-root `www-data` pool workers instead of an invalid root pool configuration.
- Ignored generated MariaDB and Redis runtime files from the repository.

## [0.2.1] - 2026-09-19

### Changed
- Docker Compose now uses project-relative volume paths and relies on Docker's native multi-architecture image selection for Apple Silicon and Synology.
- Added macOS Docker Desktop setup instructions.

## [0.2.0] - 2026-09-19

### Changed
- `.env` is no longer required at runtime; application, SMTP and calendar settings are entered in the web interface and stored persistently.
- Split the dashboard, equipment, document categories, settings and calendar areas into separate pages.
- Docker Compose now uses fixed defaults and no longer depends on Compose environment interpolation.

## [0.1.9] - 2026-09-19

### Fixed
- PHP-FPM workers now run as root for the private Synology bind-mount deployment, avoiding DSM ACL mismatches with the `www-data` UID.
- Added a startup write test for the persistent JSON storage.

## [0.1.8] - 2026-09-19

### Fixed
- Storage startup permissions now grant recursive read/write access for the mounted Synology data directory.
- Added a no-cache rebuild and host permission repair procedure for existing deployments.

## [0.1.7] - 2026-09-19

### Changed
- Changed the published web port from `8080` to `8282`.

## [0.1.6] - 2026-09-19

### Fixed
- The PHP container now creates and prepares the mounted `storage` directories with write permissions at startup.
- Documented the required container recreation step after deployment updates.

## [0.1.5] - 2026-09-19

### Added
- Added `mysql/` and `redis/` volume source directories with `.gitkeep` files.
- Documented all absolute host volume directories required by Docker Compose.

## [0.1.4] - 2026-09-19

### Fixed
- PHP-FPM now preserves Docker environment variables for `getenv()` during web requests.

## [0.1.3] - 2026-09-19

### Fixed
- Removed the Laravel-only `env()` call from the standalone PHP configuration.
- Composer dependencies are installed during the PHP image build.
- Added `.gitkeep` files so empty deployment directories are preserved during copying.
- Removed the obsolete duplicate `.docker` configuration tree.

## [0.1.2] - 2026-09-19

### Changed
- All Docker Compose bind mounts now use absolute paths below `/volume1/docker/glider-tracker` for Synology compatibility.

## [0.1.1] - 2026-09-19

### Fixed
- Docker Compose now uses visible `docker/` paths instead of hidden `.docker/` paths.
- Synology File Station deployments no longer fail because the `.docker` directory was omitted during copying.

## [0.1.0] - 2026-09-19

### Added
- Initial project structure for the Glider Equipment Tracker
- Docker Compose setup for Synology DiskStation 920+
- PHP-FPM app container with Nginx reverse-proxy setup
- MariaDB persistence layer with initialization SQL
- Redis service preparation for caching and queue usage
- Basic CRUD-oriented project structure for equipment tracking
- Email configuration template for SMTP and from-address handling
- iCal / shared calendar configuration placeholders
- Device categories for wings, rescue devices, harnesses, helmets and generic equipment
- Document category support and upload-ready architecture
- Reminder and inspection interval model for periodic checks
- README with deployment and operational guidance
- Version file and changelog

### Changed
- Planned as a minimal but production-oriented foundation for a Synology Docker deployment

### Fixed
- N/A

## [Unreleased]

### Planned
- Full equipment CRUD interface
- User login and role management
- Mail template engine for checklist reminders
- Calendar event synchronization and parsing
- Inspection history and overdue analytics
- Document upload management and category creation UI
- CSV/PDF export and reporting
