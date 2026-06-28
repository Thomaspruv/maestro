<?php

namespace App\Providers;

use App\Enums\ProjectStatus;
use App\Livewire\GitHubConnect;
use App\Models\Project;
use App\Support\ProtectDevDatabase;
use Database\Seeders\PipelineRoleSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Opcodes\LogViewer\Facades\LogViewer;

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
        Livewire::component('github-connect', GitHubConnect::class);

        LogViewer::auth(fn ($request) => $request->user() !== null);

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            ProtectDevDatabase::guardArtisanCommand($event->command);
        });

        Event::listen(Registered::class, function (Registered $event): void {
            PipelineRoleSeeder::seedForUser($event->user);
        });

        View::composer(['layouts.maestro', 'components.sidebar', 'components.topbar'], function ($view): void {
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
