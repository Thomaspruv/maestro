<?php

use App\Models\User;
use App\Models\PipelineRole;
use Database\Seeders\AgentPromptSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PipelineRole::query()
            ->where('slug', 'discovery')
            ->where('is_builtin', true)
            ->where('prompt_customized', false)
            ->update([
                'system_prompt' => AgentPromptSeeder::for('discovery'),
            ]);
    }

    public function down(): void
    {
        //
    }
};
