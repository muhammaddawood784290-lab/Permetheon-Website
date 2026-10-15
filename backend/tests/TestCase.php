<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * TEST-DB GUARD — runs before RefreshDatabase's migrate:fresh.
     * Aborts the suite if (a) the resolved test database is not name-isolated
     * (_test/_e2e, or sqlite :memory:), or (b) runtime backend/.env points at
     * THIS database — the 2026-10-08 incident where shared naming let the
     * suite wipe live admin/business data.
     */
    protected function setUpTraits()
    {
        $default = (string) config('database.default');
        $driver  = (string) config("database.connections.{$default}.driver");
        $resolved = (string) config("database.connections.{$default}.database");

        $isSqliteMemory = $driver === 'sqlite' && ($resolved === '' || $resolved === ':memory:');

        if (! $isSqliteMemory && ! preg_match('/(_test|_e2e)$/', $resolved)) {
            throw new \RuntimeException(
                "TEST-DB GUARD: refusing to run tests against '{$resolved}'. "
                .'phpunit.xml must target a database ending in _test or _e2e.'
            );
        }

        $envPath = dirname(__DIR__).'/.env'; // backend/.env (tests/ lives in backend/)
        if (! $isSqliteMemory && is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^DB_DATABASE=(.*)$/', trim($line), $m)) {
                    $runtime = trim(trim($m[1]), "\"'");
                    if ($runtime !== '' && $runtime === $resolved) {
                        throw new \RuntimeException(
                            "TEST-DB GUARD: backend/.env DB_DATABASE='{$runtime}' equals the test database. "
                            .'Runtime and tests share one database; RefreshDatabase would wipe runtime data. '
                            .'Fix backend/.env first.'
                        );
                    }
                    break;
                }
            }
        }

        return parent::setUpTraits();
    }
}
