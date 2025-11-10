<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Controladores
 */
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController; // LEGACY
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\CreditDashboardController;
use App\Http\Controllers\ClientTelegramController;
use App\Http\Controllers\BotAdminController;
use App\Http\Controllers\SaleApprovalController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinancePinController;
use App\Http\Controllers\ClientDocumentController;
use App\Http\Controllers\ClientReferenceController;
use App\Http\Controllers\ContactDashboardController;

// Nuevo módulo de Compras
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReceiptController;
use App\Http\Controllers\PurchaseApprovalController;
use App\Http\Controllers\PurchaseInvoiceController;

// **Imágenes de producto**
use App\Http\Controllers\ProductImageController;

use App\Models\Client;
use App\Models\Invoice;

/**
 * Login público (guest) + raíz
 */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Raíz -> login
Route::get('/', fn () => redirect('/login'));

// Logout solo autenticado
Route::middleware('auth')->post('/logout', [AuthController::class, 'logout'])->name('logout');

/**
 * API pública (JSON)
 */
Route::prefix('api')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    // Productos (autocomplete)
    Route::get('/products', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/{code}', [ProductController::class, 'findByCode'])->name('products.findByCode');
    Route::get('/products/id/{id}', [ProductController::class, 'findById'])->name('products.findById');

    // Proveedores (autocomplete)
    Route::get('/suppliers', [SupplierController::class, 'search'])->name('suppliers.search');

    // Clientes (autocomplete)
    Route::get('/clients', [ClientController::class, 'search'])->name('clients.search');
});

/**
 * Rutas protegidas (auth)
 */
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // FINANZAS (solo ADMIN)
    Route::middleware('can:view-finance')->group(function () {
        Route::get('/finance/pin',  [FinancePinController::class, 'show'])->name('finance.pin');
        Route::post('/finance/pin', [FinancePinController::class, 'verify'])->name('finance.pin.verify');
        Route::post('/finance/lock', [FinancePinController::class, 'lock'])->name('finance.lock');

        Route::middleware('finance.pin')->group(function () {
            Route::get('/finance',       [FinanceController::class, 'index'])->name('finance.index');
            Route::get('/finance/stats', [FinanceController::class, 'stats'])->name('finance.stats');
            Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
        });
    });

    // Recibo de pago
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    // Admin bot Telegram
    Route::prefix('bot')->group(function () {
        Route::get('/', [BotAdminController::class, 'index'])->name('bot.index');
        Route::post('/webhook/set', [BotAdminController::class, 'setWebhook'])->name('bot.webhook.set');
        Route::post('/webhook/test', [BotAdminController::class, 'testWebhook'])->name('bot.webhook.test');
        Route::post('/broadcast/test', [BotAdminController::class, 'broadcastTest'])->name('bot.broadcast.test');
        Route::post('/clients/{client}/ping', [BotAdminController::class, 'pingClient'])->name('bot.client.ping');
        Route::post('/clients/{client}/regenerate-link', [BotAdminController::class, 'regenerateLink'])->name('bot.client.regen');
    });

    // Telegram + clientes
    Route::prefix('clients/{client}')->name('clients.telegram.')->group(function () {
        Route::get('telegram',           [ClientTelegramController::class, 'show'])->name('show');
        Route::post('telegram/generate', [ClientTelegramController::class, 'generate'])->name('generate');
        Route::post('telegram/save',     [ClientTelegramController::class, 'saveChatId'])->name('save');
        Route::post('telegram/ping',     [ClientTelegramController::class, 'ping'])->name('ping');
        Route::post('telegram/unlink',   [ClientTelegramController::class, 'unlink'])->name('unlink');
    });

    // Dashboard de Créditos
    Route::prefix('dashboard/creditos')->name('credits.')->group(function () {
        Route::get('/',             [CreditDashboardController::class, 'index'])->name('dashboard');
        Route::get('/logs',         [CreditDashboardController::class, 'logs'])->name('logs');
        Route::get('/estadisticas', [CreditDashboardController::class, 'stats'])->name('stats');
        Route::post('/{credit}/recordatorio', [CreditDashboardController::class, 'remind'])->name('remind');
    });

    // Dashboard de Contactos
    Route::prefix('dashboard/contactos')->name('contact.')->group(function () {
        Route::get('/',                [ContactDashboardController::class, 'index'])->name('index');
        Route::post('/send/{client}',  [ContactDashboardController::class, 'send'])->name('send');
        Route::post('/broadcast',      [ContactDashboardController::class, 'broadcast'])->name('broadcast'); // opcional
        Route::get('/logs',            [ContactDashboardController::class, 'logs'])->name('logs');           // json para tabla
    });

    // Pagos (desde dashboard)
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');

    // Créditos
    Route::resource('credits', CreditController::class)->except(['edit','update']);
    Route::post('credits/{credit}/pay', [CreditController::class, 'pay'])->name('credits.pay');

    // Stock check
    Route::post('/stock/check', [StockController::class, 'check'])->name('stock.check');

    // Facturas de compra
    Route::resource('purchase_invoices', PurchaseInvoiceController::class)->only(['index','create','store','show']);

    // Categorías (extras + CRUD)
    Route::prefix('categories')->group(function () {
        Route::put('{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::get('deleted',           [CategoryController::class, 'deleted'])->name('categories.deleted');
        Route::post('{id}/restore',     [CategoryController::class, 'restore'])->name('categories.restore');
        Route::delete('{id}/force',     [CategoryController::class, 'forceDelete'])->name('categories.forceDelete');
    });
    Route::resource('categories', CategoryController::class);

    // Clientes + contactos
    Route::post('clients/{client}/activate', [ClientController::class, 'activate'])->name('clients.activate');
    Route::get('clients/deleted',            [ClientController::class, 'deleted'])->name('clients.deleted');
    Route::resource('clients', ClientController::class);
    Route::resource('clients.contacts', ContactController::class)->shallow();

    // Documentos de clientes
    Route::get('/clients/{client}/documents', function (Client $client) {
        return redirect()->route('clients.edit', [$client, 'tab' => 'docs']);
    })->name('clients.documents.index');
    Route::post('/clients/{client}/documents',                 [ClientDocumentController::class, 'store'])->name('clients.documents.store');
    Route::delete('/clients/{client}/documents/{doc}',         [ClientDocumentController::class, 'destroy'])->name('clients.documents.destroy');

    // Referencias de clientes
    Route::post('/clients/{client}/references',                [ClientReferenceController::class, 'store'])->name('clients.references.store');
    Route::delete('/clients/{client}/references/{reference}',  [ClientReferenceController::class, 'destroy'])->name('clients.references.destroy');

    // Ventas + aprobaciones
    Route::post('/sales/{sale}/approve', [SaleApprovalController::class,'approve'])->name('sales.approve');
    Route::get('/sales/{sale}/print',    [SaleController::class, 'print'])->name('sales.print');
    Route::put('sales/{sale}/status',    [SaleController::class, 'updateStatus'])->name('sales.updateStatus');
    Route::resource('sales', SaleController::class);

    // Compras (LEGACY)
    Route::put('/purchases/{purchase}/status', [PurchaseController::class, 'updateStatus'])->name('purchases.updateStatus');
    Route::resource('purchases', PurchaseController::class);

    // Catálogo
    Route::resource('suppliers', SupplierController::class);
    Route::resource('brands',    BrandController::class);
    Route::resource('products',  ProductController::class);

    // **Imágenes de producto (subrutas)**
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
        ->name('products.images.destroy');  // elimina una imagen
    Route::post('/products/{product}/images/{image}/cover', [ProductImageController::class, 'setCover'])
        ->name('products.images.setCover'); // marca como portada
    Route::post('/products/{product}/images/reorder', [ProductImageController::class, 'reorder'])
        ->name('products.images.reorder');  // reordenar por sort_order

    // Sub-recursos de proveedores
    Route::prefix('suppliers/{supplier}')->name('suppliers.')->group(function () {
        Route::post('/addresses',                 [SupplierController::class, 'storeAddress'])->name('addresses.store');
        Route::delete('/addresses/{address}',     [SupplierController::class, 'destroyAddress'])->name('addresses.destroy');
        Route::post('/addresses/{address}/primary',[SupplierController::class, 'setPrimaryAddress'])->name('addresses.primary');

        Route::post('/phones',                    [SupplierController::class, 'storePhone'])->name('phones.store');
        Route::delete('/phones/{phone}',          [SupplierController::class, 'destroyPhone'])->name('phones.destroy');
        Route::post('/phones/{phone}/primary',    [SupplierController::class, 'setPrimaryPhone'])->name('phones.primary');

        Route::post('/emails',                    [SupplierController::class, 'storeEmail'])->name('emails.store');
        Route::delete('/emails/{email}',          [SupplierController::class, 'destroyEmail'])->name('emails.destroy');
        Route::post('/emails/{email}/default',    [SupplierController::class, 'setDefaultEmail'])->name('emails.default');
    });

    // Inventario (movimientos)
    Route::resource('inventory', InventoryMovementController::class)->only(['index','create','store','destroy']);

    // Reportes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales',        [ReportController::class, 'sales'])->name('sales');
        Route::get('/purchases',    [ReportController::class, 'purchases'])->name('purchases');
        Route::get('/credits',      [ReportController::class, 'credits'])->name('credits');
        Route::get('/inventory',    [ReportController::class, 'inventory'])->name('inventory');

        Route::get('/sales/pdf',        [ReportController::class, 'salesPdf'])->name('sales.pdf');
        Route::get('/purchases/pdf',    [ReportController::class, 'purchasesPdf'])->name('purchases.pdf');
        Route::get('/credits/pdf',      [ReportController::class, 'creditsPdf'])->name('credits.pdf');
        Route::get('/inventory/pdf',    [ReportController::class, 'inventoryPdf'])->name('inventory.pdf');

        Route::get('/sales/print',      [ReportController::class, 'salesPrint'])->name('sales.print');
        Route::get('/purchases/print',  [ReportController::class, 'purchasesPrint'])->name('purchases.print');
        Route::get('/credits/print',    [ReportController::class, 'creditsPrint'])->name('credits.print');
        Route::get('/inventory/print',  [ReportController::class, 'inventoryPrint'])->name('inventory.print');
    });

    /**
     * Documentos (PDFs) generados
     */
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/invoice/{invoice}', function (Invoice $invoice) {
            $path = "invoices/{$invoice->number}.pdf";
            abort_unless(Storage::disk('public')->exists($path), 404, 'Documento no encontrado');
            return response()->file(storage_path("app/public/{$path}"));
        })->name('invoice');

        Route::get('/receipt/{invoice}', function (Invoice $invoice) {
            $path = "receipts/{$invoice->number}.pdf";
            abort_unless(Storage::disk('public')->exists($path), 404, 'Documento no encontrado');
            return response()->file(storage_path("app/public/{$path}"));
        })->name('receipt');

        Route::get('/contract/{invoice}', function (Invoice $invoice) {
            $path = "contracts/credit-{$invoice->number}.pdf";
            abort_unless(Storage::disk('public')->exists($path), 404, 'Documento no encontrado');
            return response()->file(storage_path("app/public/{$path}"));
        })->name('contract');

        Route::get('/schedule/{invoice}', function (Invoice $invoice) {
            $path = "schedules/{$invoice->number}.pdf";
            abort_unless(Storage::disk('public')->exists($path), 404, 'Documento no encontrado');
            return response()->file(storage_path("app/public/{$path}"));
        })->name('schedule');
    });

    /**
     * NUEVO MÓDULO DE COMPRAS
     */
    Route::resource('purchase_orders',   PurchaseOrderController::class);
    Route::resource('purchase_receipts', PurchaseReceiptController::class)->only(['index','create','store','show']);
    Route::post('purchase_receipts/{receipt}/approve', [PurchaseApprovalController::class,'approve'])->name('purchase_receipts.approve');
    Route::post('purchase_receipts/{receipt}/reject',  [PurchaseApprovalController::class,'reject'])->name('purchase_receipts.reject');

    // Acciones de estado OC
    Route::post('purchase_orders/{purchase_order}/send',   [PurchaseOrderController::class,'send'])->name('purchase_orders.send');
    Route::post('purchase_orders/{purchase_order}/close',  [PurchaseOrderController::class,'close'])->name('purchase_orders.close');
    Route::post('purchase_orders/{purchase_order}/reopen', [PurchaseOrderController::class,'reopen'])->name('purchase_orders.reopen');
});
