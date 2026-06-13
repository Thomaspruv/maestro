<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_wizard_drafts MODIFY data JSON NOT NULL DEFAULT (JSON_ARRAY())');
        } else {
            Schema::table('project_wizard_drafts', function (Blueprint $table) {
                $table->json('data')->default('[]')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_wizard_drafts MODIFY data JSON NOT NULL');
        } else {
            Schema::table('project_wizard_drafts', function (Blueprint $table) {
                $table->json('data')->change();
            });
        }
    }
};
