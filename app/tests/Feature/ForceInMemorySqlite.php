<?php

namespace Tests\Feature\Concerns;

use RuntimeException;

/**
 * Forces the test process to use an in-memory SQLite database and guards
 * against accidentally running against a cached production/staging config.
 *
 * Usage:
 *   protected function setUp(): void
 *   {
 *       $this->guardAgainstUnsafeCachedConfig();
 *       $this->forceInMemorySqliteEnvironment();
 *       parent::setUp();
 *       …
 *   }
 */
trait ForcesInMemorySqlite
{
    protected function forceInMemorySqliteEnvironment(): void
    {
        $forced = [
            'APP_ENV'       => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE'   => ':memory:',
            'DB_URL'        => '',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function guardAgainstUnsafeCachedConfig(): void
    {
        $cachedConfigPath = dirname(__DIR__, 3) . '/bootstrap/cache/config.php';

        if (!is_file($cachedConfigPath)) {
            return;
        }

        $cachedConfig      = require $cachedConfigPath;
        $defaultConnection = $cachedConfig['database']['default'] ?? null;
        $sqliteDatabase    = $cachedConfig['database']['connections']['sqlite']['database'] ?? null;

        if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException(
                'Unsafe cached DB config detected for tests. Clear config cache before running tests.'
            );
        }
    }
}