<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Models\CostLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        $currentMonthCost = (float) CostLog::query()
            ->where('user_id', $user->id)
            ->where('month', now()->startOfMonth()->toDateString())
            ->sum('cost');

        return view('settings.index', [
            'user' => $user,
            'currentMonthCost' => $currentMonthCost,
            'mcpTokens' => $user->mcpTokens()->latest()->get(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Profil mis à jour.');
    }
}
