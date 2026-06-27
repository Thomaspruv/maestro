<?php

use App\Http\Controllers\Mcp\OAuth\AuthorizationServerController;
use App\Http\Controllers\Mcp\OAuth\AuthorizeController;
use App\Http\Controllers\Mcp\OAuth\ProtectedResourceController;
use App\Http\Controllers\Mcp\OAuth\RegisterController;
use App\Http\Controllers\Mcp\OAuth\TokenController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/oauth-protected-resource', ProtectedResourceController::class)
    ->name('oauth.mcp.protected-resource');

Route::get('/.well-known/oauth-authorization-server', AuthorizationServerController::class)
    ->name('oauth.mcp.authorization-server');

Route::post('/oauth/register', RegisterController::class)->name('oauth.mcp.register');
Route::post('/oauth/token', TokenController::class)->name('oauth.mcp.token');
Route::get('/oauth/authorize', [AuthorizeController::class, 'show'])->name('oauth.mcp.authorize');

Route::middleware('auth')->group(function () {
    Route::post('/oauth/authorize', [AuthorizeController::class, 'approve'])->name('oauth.mcp.approve');
    Route::post('/oauth/deny', [AuthorizeController::class, 'deny'])->name('oauth.mcp.deny');
});
