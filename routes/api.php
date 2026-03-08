<?php

use App\Http\Controllers\GmailController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| BeyondChats Gmail Integration Dashboard API endpoints.
| All routes are prefixed with /api by default.
|
*/

// ── Gmail OAuth ──
Route::post('/connect-gmail', [GmailController::class, 'connect']);
Route::get('/gmail-callback', [GmailController::class, 'callback']);

// ── Auth Status ──
Route::get('/auth-status', [GmailController::class, 'authStatus']);
Route::post('/disconnect-gmail', [GmailController::class, 'disconnect']);

// ── Dashboard Stats ──
Route::get('/stats', [StatsController::class, 'index']);

// ── Email Sync ──
Route::post('/sync-emails', [GmailController::class, 'syncEmails']);
Route::get('/sync-status', [GmailController::class, 'syncStatus']);

// ── Emails ──
Route::get('/emails', [EmailController::class, 'index']);
Route::get('/email-thread/{threadId}', [EmailController::class, 'thread']);
Route::post('/reply-email', [EmailController::class, 'reply']);
