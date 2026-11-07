# Changelog

All notable changes to `siberfx/flysystem-wasabi` are documented in this file.

## v1.0.0 - 2026-11-07

- Requires PHP ^8.3 and Laravel 12 or 13.
- Built on Laravel's S3 driver (Flysystem v3, AWS SDK v3): `temporaryUrl()`, `temporaryUploadUrl()`, streams and visibility all work.
- Regional endpoints (`https://s3.{region}.wasabisys.com`) are worked out from `region`; `endpoint` overrides it.
- Safe defaults: `private` visibility, checksums `when_required`.
- New `use_path_style_endpoint` option for bucket names that contain dots.
- New `php artisan wasabi:check` command to verify credentials, policies and permissions.
- Pest test suite with offline and live integration tests.
