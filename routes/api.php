<?php

use App\Http\Controllers\Mcp\McpController;
use App\Http\Middleware\AuthenticateMcp;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', McpController::class)
    ->middleware(AuthenticateMcp::class)
    ->name('api.mcp');
