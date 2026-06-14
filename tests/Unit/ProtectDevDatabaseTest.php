<?php

namespace Tests\Unit;

use App\Support\ProtectDevDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProtectDevDatabaseTest extends TestCase
{
    #[Test]
    public function it_blocks_destructive_artisan_commands_on_protected_local_database(): void
    {
        app()->detectEnvironment(fn () => 'local');

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'maestro',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('migrate:fresh');

        ProtectDevDatabase::guardArtisanCommand('migrate:fresh');
    }

    #[Test]
    public function it_blocks_fresh_during_tests_on_protected_database(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'maestro',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('migrate:fresh');

        ProtectDevDatabase::guardArtisanCommand('migrate:fresh');
    }

    #[Test]
    public function it_allows_destructive_commands_on_sqlite_memory(): void
    {
        app()->detectEnvironment(fn () => 'local');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        ProtectDevDatabase::guardArtisanCommand('migrate:fresh');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_enforces_sqlite_in_memory_during_tests(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'maestro',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQLite en mémoire');

        ProtectDevDatabase::enforceTestingConnection();
    }
}
