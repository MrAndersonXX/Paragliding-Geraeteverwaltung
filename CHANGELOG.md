# Changelog

All notable changes to this project will be documented in this file.

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
