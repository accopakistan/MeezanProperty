<?php

use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Meta's WhatsApp callback. Authenticated by the verify token (GET) and the
| HMAC signature (POST), so it needs no session, cookies or CSRF token.
| Meta can only reach it over public HTTPS: see WHATSAPP_WEBHOOK_URL.
*/
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('api.whatsapp.verify');
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:120,1')->name('api.whatsapp.webhook');
