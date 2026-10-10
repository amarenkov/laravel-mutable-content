# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed

- `Builder::createLogTriggers()` and `dropMariaDbLogTriggers()` no longer drop the `_log_before_insert` and `_log_before_update` triggers of 0.4 on MariaDB. Upgrade to 0.5.0 first.

## [0.5.0] - 2026-10-09

### Added

- `Database\Log\LogContext`: log data, one JSON object with the author (`user_id`), the comment (`comment`) and any other keys, passed to the triggers through the database session (the `mutable_content.data` setting on PostgreSQL, the `@mutable_content_log_data` variable on MariaDB). `run()` applies data to everything inside, including query builder updates and raw SQL, merging it over the outer run; `set()` / `clear()` hold default data for a request, a job or a command, cleared before every queue job and Octane request. Plain SQL can set the data itself.
- Log table columns: `action`, `data` (JSON), `transaction_id` (the PostgreSQL transaction id; on MariaDB an id of the transaction for writes inside a context) and `compression_status` (`pending`, `compressed`, `error`). `user_id` is generated from `data` and indexed.
- `LogHelper::compress()` and the `mutable-content:compress-log` command replace the fields before and after of uncompressed entries with the changed fields in `fields_changed`, up to a limit per run, skipping entries locked by a parallel run; an update without changes is left as is with the `error` status and a warning in the application log; `LogHelper::prune()` and `mutable-content:prune-log` delete old entries in chunks. The package schedules `compress-log` every minute without overlapping (the lock expires in an hour) (`config/mutable-content.php`: `log.compress.schedule`, a cron expression or null to disable, and `log.compress.limit`; publish tag `mutable-content-config`); `prune-log` is never scheduled by the package.
- `Domain\Log\LogData` with chainable `user()` (an integer or a string id), `comment()`, `data()` and `set()`, accepted by `LogContext::set()` and `run()`; `Domain\Log\Changes`, the changed fields of a log entry (`Changes::between($old, $new)`).
- `Entry::changes()` returning `Changes`, `Entry::comment()`; `LogHelper::describeEvent()`, `LogHelper::decode()`.

### Changed

- `setUpdatedBy()` is replaced by `withLogContext(?string $comment = null)`, returning the model's `Models\Log\ModelLogContext` (`LogData` with `save()` and `delete()`; a given comment is set, null keeps the previous one), kept in the model until the next successful `save()` or `delete()`, which run in a transaction with it.
- `writeLog($message, $userId)` is replaced by `logEvent(string $event, array $data = [])`, returning a `Database\Log\LogEvent` (`LogData` with `write()`). The event is a translation key or a text, stored in `data` over the pending log context of the model and translated when shown (`Entry::describe()`) with scalar values of the data as replacements.
- Log table columns: the comment is stored in `data` without a length limit instead of the `comment` column, `entity_id` and `user_id` are `bigint`, `date` is `timestamptz` on PostgreSQL.
- `Entry::action()` reads the `action` column; `Entry::date` is in the application timezone.
- A save that changes fields and deletes or restores the object is logged as a deletion or restoration with the changed fields.
- The package seeders write in one log context and transaction instead of a transaction per record.
- `LogHelper::describeChanges(string $class, Changes $changes)` takes the changes instead of old and new fields.
- `Schema::createWithLog()` requires `fields`, `updated_at` and `deleted_at` columns only.
- `LogHelper::getLogsTable()` and `LogHelper::query()` use the connection of the model instead of the default one.
- PostgreSQL log triggers no longer update the logged rows a second time, and changes made by other triggers are logged too.
- `Builder::createLogTriggers()` replaces the existing log triggers on MariaDB too, including the `_log_before_insert` and `_log_before_update` triggers of 0.4.

### Removed

- `updated_by_user_id` and `updated_with_comment` columns, the `comment` and `is_deleted` columns of log tables (the `action` column tells deletions and restorations), the `fieldsUpdatedBy()` Blueprint macro, `ModelWithFields::COLUMN_UPDATED_BY_USER_ID`, `COLUMN_UPDATED_WITH_COMMENT` and `COMMENT_MAX_LENGTH`.
- `ModelWithFields::setUpdatedByIfDirty()`: use `withLogContext()`.
- `LogHelper::getAction()`: the action is stored in the log.
- `LogHelper::getChanges()`: use `Changes::between()`.

### Upgrading

Migrations of existing tables are not provided. For every table created with `Schema::createWithLog()`: add the new log table columns, fill `action` from the existing entries (`is_deleted` tells deletions and restorations, then drop it) and `data` from `user_id` and `comment`, make `user_id` generated from `data`, change the column types, drop `updated_by_user_id` and `updated_with_comment`, and recreate the triggers with `Macros\Database\Schema\Builder::createLogTriggers()` (on MariaDB before dropping the columns: it also drops the 0.4 triggers that use them). Replace `fieldsUpdatedBy()` in old migrations, `setUpdatedBy($comment, $userId)` and `setUpdatedByIfDirty()` with `withLogContext($comment)->user($userId)`, and `writeLog($message, $userId)` with `logEvent($message)->user($userId)->write()`.

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

[Unreleased]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/amarenkov/laravel-mutable-content/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/amarenkov/laravel-mutable-content/releases/tag/v0.1.0
