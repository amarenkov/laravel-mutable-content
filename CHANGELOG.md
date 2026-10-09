# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-10-09

### Added

- MariaDB 10.7+ support (connection driver `mariadb`): schema macros, change log triggers, generated columns from `fieldExtract()`, saving only the changed fields and case-insensitive object search. A table name with a schema becomes a `schema__table` name in one database.
- `Helpers\DatabaseHelper`: database driver, table and log table names for the connection, case-insensitive `whereLike()` / `orWhereLike()` on a column or a `fields->code` path.

### Removed

- The `2026_10_09_000000_add_id_to_log_tables` upgrade migration: the package migrations now only create tables from scratch, and `Schema::createWithLog()` creates log tables with an `id`. Installations older than 0.2.0 must upgrade to 0.3.x and run its migrations first.

## [0.3.1] - 2026-10-08

### Fixed

- Saving a model writes only the changed fields on PostgreSQL (`fields - removed || changed`), so fields written by another process between loading and saving the model are kept.

## [0.3.0] - 2026-10-08

### Changed

- The `fields` column is read-only from outside: `$model->fields` is a read-only array object, and assigning `fields` or a `fields->...` path, or passing them to `fill()`, `update()` or `create()`, throws a `LogicException`. Change fields with `setField()`, attribute assignment, `fill()` with field codes as keys or `mergeWithFields()`.
- `fill()` and `mergeWithFields()` replace whole field values instead of merging nested arrays recursively, so lists and nested keys can be replaced and removed.
- `fill()` throws a `LogicException` when it would change the key of a saved object.
- Package models no longer declare `id` and `fields` as fillable.

### Removed

- `ModelWithFields::arrayMergeRecursiveDistinct()`.

### Fixed

- Changing fields of an object loaded without the `fields` column throws a `LogicException` instead of overwriting the stored fields.

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

[Unreleased]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/amarenkov/laravel-mutable-content/releases/tag/v0.1.0
