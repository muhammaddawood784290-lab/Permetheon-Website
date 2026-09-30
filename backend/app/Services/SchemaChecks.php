<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SCHEMA HELPERS — dialect-safe CHECK constraints.
 *
 * Laravel's schema builder has NO ->check() column modifier: the fluent
 * ColumnDefinition silently swallows ->check('...') as an unused attribute
 * and the grammar never emits it. The correct approach is named table-level
 * CHECK constraints added after CREATE TABLE, which MySQL 8.0.16+ and
 * MariaDB 10.2.1+ enforce.
 *
 * This project targets MySQL/MariaDB exclusively; the test suite additionally
 * asserts the same invariants at the application layer as defense in depth.
 */
class SchemaChecks
{
    /**
     * Add named CHECK constraints to a table. Keys are constraint names,
     * values are the check expressions (raw SQL, no table name prefix).
     * No-ops on connections that cannot add named CHECK constraints.
     *
     * @param  array<string, string>  $checks
     */
    public static function add(string $table, array $checks): void
    {
        if (! self::supportsChecks()) {
            return;
        }

        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($checks as $name => $expression) {
            if (self::constraintExists($table, $name)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})"
            );
        }
    }

    /** True only for connections that support ALTER ... ADD CONSTRAINT ... CHECK. */
    public static function supportsChecks(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private static function constraintExists(string $table, string $name): bool
    {
        $database = DB::connection()->getDatabaseName();

        $row = DB::selectOne(
            'SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = \'CHECK\'',
            [$database, $table, $name]
        );

        return ($row->n ?? 0) > 0;
    }
}
