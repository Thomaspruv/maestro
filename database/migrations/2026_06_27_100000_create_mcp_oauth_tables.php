<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 64)->unique();
            $table->string('client_name')->nullable();
            $table->json('redirect_uris');
            $table->timestamps();
        });

        Schema::create('mcp_oauth_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 10)->default('S256');
            $table->string('redirect_uri', 512);
            $table->string('scope')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mcp_oauth_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_token', 64)->unique();
            $table->string('refresh_token', 64)->nullable()->unique();
            $table->string('scope')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_access_tokens');
        Schema::dropIfExists('mcp_oauth_authorization_codes');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
