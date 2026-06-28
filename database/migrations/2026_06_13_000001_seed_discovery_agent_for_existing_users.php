<?php

use App\Models\User;
use Database\Seeders\PipelineRoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()->each(function (User $user): void {
            PipelineRoleSeeder::seedForUser($user);
        });
    }

    public function down(): void
    {
        //
    }
};
