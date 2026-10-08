<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

/** SCHEMA HELPERS — PostgreSQL only.
 *
 * Laravel's schema builder has NO ->check() column modifier: the fluent
 * ColumnDefinition silently swallows ->check('...') as an unused attribute
 * and the grammar never emits it. The correct approach is named table-level
 * CHECK constraints added after CREATE TABLE, which PostgreSQL enforces.
 *
 * These helpers back the PostgreSQL migrations. They are deliberately
 * free of any MongoDB driver logic: the PostgreSQL branch is the only
 * supported one, and any attempt to use this class against a non-PostgreSQL
 * connection is a developer error.
 */
class SchemaBuilder
{
    /** Create a table with the standard 'id' big-incrementing primary key. */
    public static function create(string $name, callable $definition): void
    {
        Schema::create($name, function ($table) use ($definition) {
            $table->bigIncrements('id');
            $definition($table);
        });
    }

    /** Drop a table if it exists. */
    public static function dropIfExists(string $name): void
    {
        Schema::dropIfExists($name);
    }

    /** Add named CHECK constraints to a table, idempotently. */
    public static function addCheckConstraints(string $table, array $checks): void
    {
        SchemaChecks::add($table, $checks);
    }
}
