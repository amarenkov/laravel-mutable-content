# amarenkov/laravel-mutable-content

[![tests](https://github.com/amarenkov/laravel-mutable-content/actions/workflows/tests.yml/badge.svg)](https://github.com/amarenkov/laravel-mutable-content/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/amarenkov/laravel-mutable-content)](https://packagist.org/packages/amarenkov/laravel-mutable-content)

Laravel models whose set of fields is not fixed by the database schema. Values live in a single
JSON column (`jsonb` on PostgreSQL), and field definitions come from two sources: PHP attributes in code and records
in reference tables that an administrator edits by hand.

One field definition feeds everything at once: Eloquent, validation rules, the admin panel
([`-filament`](https://github.com/amarenkov/laravel-mutable-content-filament)) and the OpenAPI
docs ([`-scramble`](https://github.com/amarenkov/laravel-mutable-content-scramble)). Add a field,
and it shows up in forms, tables, validation and API docs.

## What it solves

1. **Users create and change object fields.** New fields are added to a class in the admin panel,
   without migrations or a release.
2. **Users create and change lists of values (LOV).** Statuses, categories and other reference
   lists are maintained in the admin panel, next to the system lists declared in code.
3. **Every content change is logged.** The log is written by database triggers, not by the
   application, so changes made around it, even with plain SQL, still leave a trace with the old
   and new values.
4. **Code sets the minimum, users extend it.** Fields and lists declared in code cannot be
   deleted in the admin panel, and their "required" and "immutable" flags can only be switched
   on there, not off.
5. **Data is not lost on write.** The fields column cannot be overwritten as a whole, keys that
   are not declared as fields are kept, and concurrent saves of different fields do not overwrite
   each other.
6. **Who and why.** Every change in the log carries its author and comment, and an event without
   field changes can be logged too (`writeLog()`).

## Features

- **Field types.** `string`, `text`, `bool`, `int`, `float`, `date`, `address`, `icon`, `lov`
  (a list of values), `lov_item` (an item of a list), `object` (a reference to an object of
  another class), `weight`, `length`, `area`, `volume`, `density`, `surface_density` and
  `system`. The type drives the validation rule, the form component and the table column.
- **Value objects for measurements.** `Weight`, `Length`, `Area`, `Volume`, `Density` and
  `SurfaceDensity` store values in base units (kg, m, m², m³, kg/m³, kg/m²), support
  arithmetic, tolerant comparison and formatting. Assign one directly
  (`$item->weight = Weight::fromGrams(500)`) and read it back with `$item->getWeight('weight')`.
  The display unit is a per-field setting.
- **Fields of LOV items.** A field is bound to a class or to one list, so the items of a list
  (material types, for example) get fields of their own.
- **Transparent access.** `$project->code` reads the value from JSON as if it were a regular
  column: `getAttribute`, `setAttribute` and `fill` are intercepted.
- **Indexable JSON fields.** The `fieldExtract()` macro creates a stored generated column from a
  field for indexes, unique constraints and Eloquent relations.
- **Restore instead of duplicate.** Creating a record with the unique fields of a soft-deleted one
  restores that record with its id and history (`uniqueFieldSets()`).
- **Change log tables.** `Schema::createWithLog()` creates a table together with its log table
  in the `logs` schema and the triggers. `LogHelper` and `Models\Log\Entry` read the log back in a
  human-readable form.
- **References by code.** An `object` field can store an object code instead of its id
  (`link_by_code`), optionally accepting codes that do not exist yet (`allow_unlisted_codes`).

## Requirements

- PHP 8.4
- Laravel 13
- PostgreSQL or MariaDB 10.7+ (connection driver `mariadb`).

On MariaDB a table name with a schema, such as `projects.projects`, becomes
`projects__projects` in one database: models, `Schema::createWithLog()` and `LogHelper` translate
it, so the same models and migrations work on both. `Schema::createSchema()` does nothing there.
Use the model's `getTable()` instead of a literal name with a schema in raw queries.

## Installation

```bash
composer require amarenkov/laravel-mutable-content

php artisan vendor:publish --tag=mutable-content-migrations
php artisan migrate

php artisan db:seed --class="Amarenkov\MutableContent\Database\Seeders\LovsSeeder"
php artisan db:seed --class="Amarenkov\MutableContent\Database\Seeders\FieldsSeeder"
```

Run the seeders in this order: lists of values first, then fields.

## Quick start

A model:

```php
use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Code as FieldCode;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;

#[Table('projects.projects')]
#[ClassLabel('Project')]
#[FieldCode]
class Project extends ModelWithFields
{
    #[CFAType(FieldType::TYPE_INT), CFALabel('Priority'), CFAIsRequired]
    const FIELD_PRIORITY = 'priority';

    // required: the base class sets it to false
    protected static array|bool|null $fieldDefinitions = null;
}
```

Register it in your service provider:

```php
$this->app->make(MutableClassRegistry::class)->add(Project::class);
```

A migration:

```php
Schema::createSchema('projects');

Schema::createWithLog('projects.projects', function (Blueprint $table) {
    $table->fieldsBase();        // id, JSON fields, created_at
    $table->fieldsUpdatedAt();   // updated_at
    $table->softDeletes();       // deleted_at
    $table->fieldsUpdatedBy();   // updated_by_user_id, updated_with_comment

    $table->fieldExtract('code')->type('varchar(50)');
    $table->unique('code');
});
```

Usage:

```php
$project = new Project();
$project->mergeWithFields(['code' => 'PRJ-1', 'priority' => 10]);
$project->setUpdatedByIfDirty('import', $user->id); // goes to the change log
$project->save();

$project->priority; // 10, read from JSON like a regular attribute
```

Validation rules are built from the same definition:

```php
use Amarenkov\MutableContent\Helpers\RuleHelper;

$request->validate(
    RuleHelper::getValidationRules(Project::class) +
    ['tasks' => 'array|required'] +
    RuleHelper::getValidationRules(Task::class, 'tasks.*')
);
```

Type settings, such as `link_by_code` and `allow_unlisted_codes` of an `object` field, are set
in the admin panel or in code with the `TypeSettings` attribute:

```php
#[CFAType(Type::TYPE_OBJECT), CFAObjectClass(Team::class),
  CFATypeSettings([TypeSettings::LINK_BY_CODE => true, TypeSettings::ALLOW_UNLISTED_CODES => true])]
const FIELD_TEAM_CODE = 'team_code';
```

## Translations

Labels in attributes are passed through `__()` when read, so they can be translation keys or
plain text. The package labels are in English and translated in `lang/ru.json`; messages are in
`lang/{en,ru}/*.php` under the `mutable-content` namespace. Publish them to override:

```bash
php artisan vendor:publish --tag=mutable-content-lang
```

Labels from code are written to the database by the seeders in the current locale; after that
the database label wins and is edited in the admin panel.

## Caveats

- Every `ModelWithFields` subclass **must** redeclare
  `protected static array|bool|null $fieldDefinitions = null;`, otherwise a `LogicException` is
  thrown.
- Call `setUpdatedByIfDirty()` before every `save()` (or `setUpdatedBy()` before `delete()`):
  the log trigger takes the author and comment from service columns and clears them.
- Change fields with `setField()`, attribute assignment, `fill()` with field codes as keys or
  `mergeWithFields()`: `$model->fields` is read-only, and changing fields of an object loaded
  without the `fields` column throws a `LogicException`.
- Concurrent changes of the same field: the last save wins.
- Class labels in the registry must be unique.
- Create entity tables with `Schema::createWithLog()` only: it requires all five service columns
  and fails if one is missing.
- Field definitions and lists of values are cached per process. The package flushes the cache
  when fields or lists change and before every queue job and Octane request.

## Companion packages

- [`amarenkov/laravel-mutable-content-filament`](https://github.com/amarenkov/laravel-mutable-content-filament):
  a Filament 5 admin panel with forms, tables and screens for managing fields and lists of values.
- [`amarenkov/laravel-mutable-content-scramble`](https://github.com/amarenkov/laravel-mutable-content-scramble):
  OpenAPI docs with field labels and LOV enums in Scramble.

## License

MIT. See [LICENSE](LICENSE).
