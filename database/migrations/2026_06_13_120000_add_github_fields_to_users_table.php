<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('github_token')->nullable()->after('claude_api_key');
            $table->string('github_username')->nullable()->after('github_token');
            $table->timestamp('github_connected_at')->nullable()->after('github_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'github_token',
                'github_username',
                'github_connected_at',
            ]);
        });
    }
};
