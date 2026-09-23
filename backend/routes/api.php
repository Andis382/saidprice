<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\NudgeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PublicQuoteController;
use App\Http\Controllers\QuoteActionController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/auth/csrf', [AuthController::class, 'csrf']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/auth/invitations/{token}', [AuthController::class, 'invitation']);
Route::post('/auth/join', [AuthController::class, 'join'])->middleware('throttle:10,1');
Route::get('/public/files/{file}', [FilesController::class, 'publicShow'])->name('files.public')->middleware('signed:relative');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);

// The customer's quote page (link sent on WhatsApp)
Route::prefix('/public/quotes/{token}')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [PublicQuoteController::class, 'show']);
    Route::get('/pdf', [PublicQuoteController::class, 'pdf']);
    Route::post('/accept', [PublicQuoteController::class, 'accept'])->middleware('throttle:10,1');
    Route::post('/decline', [PublicQuoteController::class, 'decline'])->middleware('throttle:10,1');
    Route::post('/question', [PublicQuoteController::class, 'question'])->middleware('throttle:10,1');
});

// Signed in
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'show']);
    Route::put('/auth/me', [AuthController::class, 'update']);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::put('/organization', [OrganizationController::class, 'update']);
    Route::get('/settings/quoting', [BusinessSettingsController::class, 'show']);
    Route::put('/settings/quoting', [BusinessSettingsController::class, 'update']);

    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team/invitations', [TeamController::class, 'invite']);
    Route::delete('/team/invitations/{id}', [TeamController::class, 'revoke'])->whereNumber('id');
    Route::delete('/team/members/{id}', [TeamController::class, 'remove'])->whereNumber('id');

    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store'])->middleware('throttle:30,1');
    Route::post('/quotes/parse', [QuoteController::class, 'parse'])->middleware('throttle:30,1');
    Route::prefix('/quotes/{id}')->whereNumber('id')->group(function () {
        Route::get('/', [QuoteController::class, 'show']);
        Route::put('/', [QuoteController::class, 'update']);
        Route::delete('/', [QuoteController::class, 'destroy']);
        Route::get('/pdf', [QuoteController::class, 'pdf']);
        Route::post('/send', [QuoteActionController::class, 'send']);
        Route::post('/resend', [QuoteActionController::class, 'resend']);
        Route::post('/duplicate', [QuoteActionController::class, 'duplicate']);
        Route::post('/outcome', [QuoteActionController::class, 'outcome']);
    });

    Route::get('/nudges', [NudgeController::class, 'index']);
    Route::put('/nudges/{id}', [NudgeController::class, 'update'])->whereNumber('id');
    Route::post('/nudges/{id}/send', [NudgeController::class, 'send'])->whereNumber('id');
    Route::post('/nudges/{id}/dismiss', [NudgeController::class, 'dismiss'])->whereNumber('id');

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->whereNumber('id');
    Route::put('/customers/{id}', [CustomerController::class, 'update'])->whereNumber('id');

    Route::get('/stats', [StatsController::class, 'show']);
    Route::get('/overview', [StatsController::class, 'overview']);

    Route::get('/messages', [MessagesController::class, 'outbox']);
    Route::get('/messages/inbound', [MessagesController::class, 'inbox']);
    Route::post('/messages/{id}/retry', [MessagesController::class, 'retry'])->whereNumber('id');
    Route::post('/dev/inbound', [MessagesController::class, 'simulate']);

    Route::get('/files/{id}', [FilesController::class, 'show']);
});
