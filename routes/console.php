<?php

use App\Jobs\CheckBudgetAlertsJob;
use Database\Seeders\LocalFakeProjectSeeder;
use Database\Seeders\ThomasRestoreSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maestro:restore-thomas', function () {
    $this->call(ThomasRestoreSeeder::class);
})->purpose('Restaure le compte thomas@mail.com, le projet Maestro et la tâche pipeline UI');

Artisan::command('maestro:seed-fake-project', function () {
    $this->call(LocalFakeProjectSeeder::class);
})->purpose('Crée un projet Sandbox Local avec des tâches fake pour thomas@mail.com (local uniquement)');

Schedule::job(new CheckBudgetAlertsJob)->daily();
