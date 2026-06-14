<?php

namespace App\Support;

use RuntimeException;

class ProtectDevDatabase
{
    /** @var list<string> */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
    ];

    /**
     * @return list<string>
     */
    public static function protectedDatabaseNames(): array
    {
        $names = array_filter(array_map(
            trim(...),
            explode(',', (string) env('MAESTRO_PROTECTED_DB_NAMES', 'maestro')),
        ));

        return $names !== [] ? $names : ['maestro'];
    }

    /**
     * Connexion « dev » à protéger (MySQL/PostgreSQL local nommé maestro, etc.).
     */
    public static function isProtectedDevConnection(?string $connection = null): bool
    {
        if (! app()->environment('local', 'testing')) {
            return false;
        }

        $connection ??= (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver === 'sqlite' && in_array($database, [':memory:', ''], true)) {
            return false;
        }

        return in_array($database, self::protectedDatabaseNames(), true);
    }

    /**
     * Force SQLite in-memory pour la suite de tests PHPUnit/Pest.
     */
    public static function enforceTestingConnection(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver === 'sqlite' && $database === ':memory:') {
            return;
        }

        throw new RuntimeException(
            'Les tests Maestro doivent utiliser SQLite en mémoire, pas la base dev « '
            .$database.' » ('.$driver.'). Lancez : php artisan test'
        );
    }

    public static function guardArtisanCommand(string $command): void
    {
        if (! in_array($command, self::DESTRUCTIVE_COMMANDS, true)) {
            return;
        }

        if (! self::isProtectedDevConnection()) {
            return;
        }

        $database = config('database.connections.'.config('database.default').'.database');

        throw new RuntimeException(
            "Commande « {$command} » interdite sur la base dev « {$database} ». "
            .'Utilisez php artisan migrate (incrémental) ou php artisan maestro:restore-thomas.'
        );
    }

    /**
     * Variables d'environnement à injecter avant d'exécuter Pest/PHPUnit dans un clone.
     *
     * @return array<string, string>
     */
    public static function testingProcessEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];
    }
}
