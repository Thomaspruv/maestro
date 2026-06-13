<?php

namespace App\Providers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, function (Registered $event): void {
            UserAgentSeeder::seedForUser($event->user);
        });

        View::composer('layouts.maestro', function ($view): void {
            $project = request()->route('project');

            if ($project instanceof Project) {
                $view->with('currentProject', $project);
            }

            if (Auth::check()) {
                $view->with(
                    'userProjects',
                    Project::query()
                        ->forUser(Auth::user())
                        ->where('status', ProjectStatus::Active)
                        ->orderBy('name')
                        ->get(),
                );
            }
        });
    }
}
