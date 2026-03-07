<?php

use App\Http\Controllers\GmailController;
use App\Http\Controllers\EmailController;
use Illuminate\Support\Facades\Route;

/*
| API endpoints with all routes are prefixed with /api by default.
|
*/

// ── Gmail OAuth ──
Route::post('/connect-gmail', [GmailController::class, 'connect']);
Route::get('/gmail-callback', [GmailController::class, 'callback']);
Route::post('/disconnect-gmail', [GmailController::class, 'disconnect']);

// ── Auth Status ──
Route::get('/auth-status', [GmailController::class, 'authStatus']);

// ── Email Sync ──
Route::post('/sync-emails', [GmailController::class, 'syncEmails']);
Route::get('/sync-status', [GmailController::class, 'syncStatus']);
Route::get('/sync-history', [GmailController::class, 'syncHistory']);

// ── Emails ──
Route::get('/emails', [EmailController::class, 'index']);
Route::get('/email-thread/{threadId}', [EmailController::class, 'thread']);
Route::post('/reply-email', [EmailController::class, 'reply']);
Route::get('/stats', [EmailController::class, 'stats']);
Route::post('/reply-email', [EmailController::class, 'reply']);
