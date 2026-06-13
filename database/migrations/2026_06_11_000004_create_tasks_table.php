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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('module')->nullable();
            $table->string('type');
            $table->string('priority')->default('medium');
            $table->string('status')->default('backlog');
            $table->string('mode');
            $table->string('current_agent')->nullable();
            $table->string('github_branch')->nullable();
            $table->string('github_pr_url')->nullable();
            $table->integer('github_pr_number')->nullable();
            $table->string('pr_status')->default('none');
            $table->decimal('estimated_cost', 8, 4)->nullable();
            $table->decimal('actual_cost', 8, 4)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
