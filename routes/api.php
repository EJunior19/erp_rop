<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Api\ErpCatalogOrderController;

// 🔹 GET de diagnóstico para el webhook de Telegram
Route::get('/telegram/webhook', function () {
    return response()->json([
        'ok'   => true,
        'info' => 'Esta URL es solo diagnóstico. El webhook real es POST.'
    ]);
});

// 🔹 POST real del webhook de Telegram (por si lo estás usando)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

// 🔹 Endpoint para pedidos que vienen desde el catálogo inteligente
Route::post('/erp/pedido-desde-catalogo', [ErpCatalogOrderController::class, 'store']);

