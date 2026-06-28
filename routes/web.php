<?php

use App\Http\Controllers\PipelineSteps\PipelineStepController;
use App\Http\Controllers\Costs\CostController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gates\GateController;
use App\Http\Controllers\GitHub\GitHubOAuthController;
use App\Http\Controllers\Projects\ProjectRoleController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectPipelineController;
use App\Http\Controllers\Projects\ProjectSettingsController;
use App\Http\Controllers\Projects\ProjectWizardController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\BudgetController;
use App\Http\Controllers\Settings\GitHubAccountController;
use App\Http\Controllers\Settings\McpDocumentationController;
use App\Http\Controllers\Settings\McpSettingsController;
use App\Http\Controllers\Settings\McpTokenController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Tasks\CostEstimatorController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Webhooks\GitHubWebhookController;
use App\Http\Middleware\VerifyGitHubWebhook;
use App\Livewire\Actions\Logout;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::post('/webhooks/github', GitHubWebhookController::class)
    ->middleware(VerifyGitHubWebhook::class)
    ->name('webhooks.github');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/logout', function (Logout $logout) {
        $logout();

        return redirect('/');
    })->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/api-key', [ApiKeyController::class, 'update'])->name('api-key.update');
        Route::put('/budget', [BudgetController::class, 'update'])->name('budget.update');
        Route::get('/mcp', [McpSettingsController::class, 'edit'])->name('mcp');
        Route::get('/mcp/docs', [McpDocumentationController::class, 'show'])->name('mcp.docs');
        Route::post('/mcp-tokens', [McpTokenController::class, 'store'])->name('mcp-tokens.store');
        Route::delete('/mcp-tokens/{mcpToken}', [McpTokenController::class, 'destroy'])->name('mcp-tokens.destroy');
        Route::put('/github', [GitHubAccountController::class, 'update'])->name('github.update');
        Route::delete('/github', [GitHubAccountController::class, 'disconnect'])->name('github.disconnect');
    });

    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');

        Route::get('/create', [ProjectWizardController::class, 'create'])->name('create');
        Route::post('/create/step/1', [ProjectWizardController::class, 'storeStep1'])->name('wizard.step1');
        Route::post('/create/step/2', [ProjectWizardController::class, 'storeStep2'])->name('wizard.step2');
        Route::post('/create/step/3', [ProjectWizardController::class, 'storeStep3'])->name('wizard.step3');
        Route::post('/create/step/4', [ProjectWizardController::class, 'storeStep4'])->name('wizard.step4');
        Route::post('/create/finalize', [ProjectWizardController::class, 'finalize'])->name('wizard.finalize');

        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');

        Route::get('/{project}/settings', [ProjectSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/{project}/settings', [ProjectSettingsController::class, 'update'])->name('settings.update');
        Route::put('/{project}/settings/roles', [ProjectRoleController::class, 'update'])->name('settings.roles.update');
        Route::put('/{project}/settings/pipeline', [ProjectPipelineController::class, 'update'])->name('settings.pipeline.update');
        Route::post('/{project}/settings/roles/{type}/test', [ProjectRoleController::class, 'test'])->name('settings.roles.test');

        Route::get('/{project}/costs', [CostController::class, 'index'])->name('costs.index');

        Route::get('/{project}/discovery', fn (Project $project) => view('discovery.index', compact('project')))
            ->name('discovery');

        Route::prefix('{project}/tasks')->name('tasks.')->group(function () {
            Route::get('/create', [TaskController::class, 'create'])->name('create');
            Route::get('/estimate', [CostEstimatorController::class, 'estimateDraft'])->name('estimate.draft');
            Route::post('/', [TaskController::class, 'store'])->name('store');
            Route::get('/{task}', [TaskController::class, 'show'])->name('show');
            Route::put('/{task}', [TaskController::class, 'update'])->name('update');
            Route::post('/{task}/start', [TaskController::class, 'start'])->name('start');
            Route::post('/{task}/retry', [TaskController::class, 'retry'])->name('retry');
            Route::post('/{task}/abandon', [TaskController::class, 'abandon'])->name('abandon');
            Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
            Route::get('/{task}/estimate', [CostEstimatorController::class, 'estimate'])->name('estimate');
            Route::get('/{task}/cockpit', [TaskController::class, 'cockpit'])->name('cockpit');
        });
    });

    Route::get('/costs', [CostController::class, 'global'])->name('costs.global');

    Route::post('/gates/{gate}/approve', [GateController::class, 'approve'])->name('gates.approve');
    Route::post('/gates/{gate}/reject', [GateController::class, 'reject'])->name('gates.reject');

    Route::put('/pipeline-steps/{step}/output', [PipelineStepController::class, 'updateOutput'])->name('pipeline-steps.output.update');

    Route::prefix('auth/github')->name('github.')->group(function () {
        Route::get('/', [GitHubOAuthController::class, 'redirect'])->name('redirect');
        Route::get('/callback', [GitHubOAuthController::class, 'callback'])->name('callback');
    });
});

require __DIR__.'/auth.php';
