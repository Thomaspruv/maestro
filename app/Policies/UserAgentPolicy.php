<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAgent;

class UserAgentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserAgent $userAgent): bool
    {
        return $user->id === $userAgent->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UserAgent $userAgent): bool
    {
        return $user->id === $userAgent->user_id;
    }

    public function delete(User $user, UserAgent $userAgent): bool
    {
        return $user->id === $userAgent->user_id && ! $userAgent->is_builtin;
    }
}
