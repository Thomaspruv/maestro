<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_roles', function (Blueprint $table) {
            $table->foreignId('pipeline_role_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pipeline_role_id');
        });
    }
};
