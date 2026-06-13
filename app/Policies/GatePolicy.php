<?php

namespace App\Policies;

use App\Models\Gate;
use App\Models\User;

class GatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Gate $gate): bool
    {
        return $user->id === $gate->task->project->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Gate $gate): bool
    {
        return $user->id === $gate->task->project->user_id;
    }

    public function delete(User $user, Gate $gate): bool
    {
        return $user->id === $gate->task->project->user_id;
    }
}
