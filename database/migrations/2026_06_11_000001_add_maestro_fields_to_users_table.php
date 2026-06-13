<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->text('claude_api_key')->nullable()->after('password');
            $table->decimal('monthly_budget', 8, 2)->nullable()->after('claude_api_key');
            $table->json('notification_preferences')->nullable()->after('monthly_budget');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid',
                'claude_api_key',
                'monthly_budget',
                'notification_preferences',
            ]);
        });
    }
};
