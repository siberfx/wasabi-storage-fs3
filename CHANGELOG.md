# Changelog

All notable changes to `siberfx/flysystem-wasabi` are documented in this file.

## 0.1.1 - 2026-11-07

- Added `examples/` with a shared Laravel controller and routes, plus Blade, Twig (TwigBridge), Vue 3 and React frontends for form uploads, presigned direct uploads with progress, listing, downloading and deleting.
- README: badges now read the PHP, Laravel and Flysystem versions from Packagist; added a "Frontend examples" section.
- `examples/` is excluded from Composer dist archives.

## 0.1.0 - 2026-11-07

- Requires PHP ^8.3 and Laravel 12 or 13.
- Built on Laravel's S3 driver (Flysystem v3, AWS SDK v3): `temporaryUrl()`, `temporaryUploadUrl()`, streams and visibility all work.
- Regional endpoints (`https://s3.{region}.wasabisys.com`) are worked out from `region`; `endpoint` overrides it.
- Safe defaults: `private` visibility, checksums `when_required`.
- New `use_path_style_endpoint` option for bucket names that contain dots.
- New `php artisan wasabi:check` command to verify credentials, policies and permissions.
- Pest test suite with offline and live integration tests.
