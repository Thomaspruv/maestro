<?php

namespace App\Policies;

use App\Models\PipelineRole;
use App\Models\User;

class PipelineRolePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PipelineRole $pipelineRole): bool
    {
        return $user->id === $pipelineRole->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PipelineRole $pipelineRole): bool
    {
        return $user->id === $pipelineRole->user_id;
    }

    public function delete(User $user, PipelineRole $pipelineRole): bool
    {
        return $user->id === $pipelineRole->user_id && ! $pipelineRole->is_builtin;
    }
}
