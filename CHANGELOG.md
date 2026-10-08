# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-10-08

### Added

- `mutable-content-migrations` publish tag for the package migrations.

### Changed

- Log tables have an `id` primary key, and log entries with the same timestamp are ordered by it (`LogHelper::query()` selects it as `log_id`). `Schema::renameWithLog()` renames its sequence and key too. Existing installations must publish and run the new migration: `php artisan vendor:publish --tag=mutable-content-migrations && php artisan migrate`.
- Clearer exception messages when `Schema::createWithLog()` misses service columns and when a model does not redeclare `$fieldDefinitions`.

### Fixed

- `label()`, `code()` and reading `$model->label` / `$model->code` no longer fail on models without these fields.
- `getAttribute('fields->code')` and nested `fields->a->b` paths read values from the `fields` column, so Filament resources with a JSON route key resolve parent records.

## [0.1.0] - 2026-10-08

Initial release.

[Unreleased]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/amarenkov/laravel-mutable-content/releases/tag/v0.1.0
