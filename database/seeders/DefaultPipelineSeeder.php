<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DefaultPipelineSeeder extends Seeder
{
    /**
     * @return array<string, list<string>>
     */
    public static function pipelines(): array
    {
        return config('maestro.default_pipelines', []);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function gateConfig(): array
    {
        return config('maestro.default_gate_config', []);
    }

    /**
     * @return array<string, string>
     */
    public static function defaultModes(): array
    {
        return config('maestro.default_modes', []);
    }

    /**
     * @return array<string, string>
     */
    public static function defaultModels(): array
    {
        return config('maestro.default_models', []);
    }

    /**
     * Les pipelines par défaut sont définis dans config/maestro.php.
     */
    public function run(): void
    {
        //
    }
}
