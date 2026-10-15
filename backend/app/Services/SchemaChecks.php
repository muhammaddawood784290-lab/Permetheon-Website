<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** SCHEMA HELPERS — PostgreSQL only.
 *
 * Laravel's schema builder has NO ->check() column modifier: the fluent
 * ColumnDefinition silently swallows ->check('...') as an unused attribute
 * and the grammar never emits it. The correct approach is named table-level
 * CHECK constraints added after CREATE TABLE, which PostgreSQL enforces.
 *
 * All invariants the checks used to enforce are enforced here at DDL time
 * (not as a no-op) and are re-asserted by the application layer (request
 * validators + MeetingPolicy) as defense in depth.
 */
class SchemaChecks
{
    /**
     * Add named CHECK constraints to a table. Keys are constraint names,
     * values are the check expressions (raw SQL, no table name prefix).
     *
     * @param  array<string, string>  $checks
     */
    public static function add(string $table, array $checks): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($checks as $name => $expression) {
            self::addIfNotExists($table, $name, $expression);
        }
    }

    /**
     * Add a single named CHECK constraint if it does not already exist.
     */
    public static function addIfNotExists(string $table, string $name, string $expression): void
    {
        if (self::constraintExists($table, $name)) {
            return;
        }

        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})"
        );
    }

    /**
     * True only for connections that support ALTER ... ADD CONSTRAINT ...
     * CHECK. PostgreSQL is the only supported driver, and PostgreSQL does
     * support it, so this is always true.
     */
    public static function supportsChecks(): bool
    {
        return true;
    }

    private static function constraintExists(string $table, string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n FROM pg_constraint
             WHERE conrelid = (SELECT oid FROM pg_class WHERE relname = ? AND relkind = \'r\' AND relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = CURRENT_SCHEMA()))
               AND conname = ?',
            [$table, $name]
        );

        return (int) $row->n > 0;
    }
}
