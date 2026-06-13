<?php

use App\Models\User;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()->each(function (User $user): void {
            UserAgentSeeder::seedForUser($user);
        });
    }

    public function down(): void
    {
        //
    }
};
