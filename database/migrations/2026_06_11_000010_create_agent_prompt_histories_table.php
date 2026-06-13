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
        Schema::create('agent_prompt_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_agent_id')->constrained()->cascadeOnDelete();
            $table->text('system_prompt');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_histories');
    }
};
