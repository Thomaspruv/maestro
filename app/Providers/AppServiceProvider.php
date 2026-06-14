<?php

namespace App\Providers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProtectDevDatabase;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Events\CommandStarting;
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
        // Force SQLite in-memory for testing
        if ($this->app->environment('testing')) {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            ProtectDevDatabase::guardArtisanCommand($event->command);
        });

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
