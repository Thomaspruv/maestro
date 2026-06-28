<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_agents')) {
            return;
        }

        Schema::rename('user_agents', 'pipeline_roles');

        Schema::table('project_agents', function (Blueprint $table) {
            $table->dropForeign(['user_agent_id']);
        });

        Schema::rename('project_agents', 'project_roles');

        Schema::table('project_roles', function (Blueprint $table) {
            $table->renameColumn('user_agent_id', 'pipeline_role_id');
            $table->renameColumn('agent_type', 'role');
        });

        Schema::table('project_roles', function (Blueprint $table) {
            $table->foreign('pipeline_role_id')->references('id')->on('pipeline_roles')->nullOnDelete();
        });

        Schema::rename('agent_runs', 'pipeline_steps');

        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->renameColumn('agent_type', 'role');
        });

        Schema::rename('agent_prompt_histories', 'role_prompt_histories');

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('current_agent', 'current_role');
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->dropForeign(['agent_run_id']);
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->renameColumn('agent_run_id', 'pipeline_step_id');
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->foreign('pipeline_step_id')->references('id')->on('pipeline_steps')->cascadeOnDelete();
        });

        Schema::table('cost_logs', function (Blueprint $table) {
            $table->dropForeign(['agent_run_id']);
        });

        Schema::table('cost_logs', function (Blueprint $table) {
            $table->renameColumn('agent_run_id', 'pipeline_step_id');
        });

        Schema::table('cost_logs', function (Blueprint $table) {
            $table->foreign('pipeline_step_id')->references('id')->on('pipeline_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pipeline_roles') || Schema::hasTable('user_agents')) {
            return;
        }

        Schema::table('cost_logs', function (Blueprint $table) {
            $table->dropForeign(['pipeline_step_id']);
        });

        Schema::table('cost_logs', function (Blueprint $table) {
            $table->renameColumn('pipeline_step_id', 'agent_run_id');
        });

        Schema::table('cost_logs', function (Blueprint $table) {
            $table->foreign('agent_run_id')->references('id')->on('agent_runs')->nullOnDelete();
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->dropForeign(['pipeline_step_id']);
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->renameColumn('pipeline_step_id', 'agent_run_id');
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->foreign('agent_run_id')->references('id')->on('agent_runs')->cascadeOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('current_role', 'current_agent');
        });

        Schema::rename('role_prompt_histories', 'agent_prompt_histories');

        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->renameColumn('role', 'agent_type');
        });

        Schema::rename('pipeline_steps', 'agent_runs');

        Schema::table('project_roles', function (Blueprint $table) {
            $table->dropForeign(['pipeline_role_id']);
        });

        Schema::table('project_roles', function (Blueprint $table) {
            $table->renameColumn('pipeline_role_id', 'user_agent_id');
            $table->renameColumn('role', 'agent_type');
        });

        Schema::rename('project_roles', 'project_agents');

        Schema::table('project_agents', function (Blueprint $table) {
            $table->foreign('user_agent_id')->references('id')->on('user_agents')->nullOnDelete();
        });

        Schema::rename('pipeline_roles', 'user_agents');
    }
};
