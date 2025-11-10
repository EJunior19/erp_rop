<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Credit;
use App\Models\InventoryMovement;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /* =========================================================
     | 📊 VENTAS
     | Vistas:
     |  - resources/views/purchases/reports/sales.blade.php
     |  - resources/views/purchases/reports/exports/sales-pdf.blade.php
     |  - resources/views/purchases/reports/exports/sales-print.blade.php
     * ========================================================*/
    public function sales(Request $request)
    {
        $sales = Sale::with('client')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        return view('purchases.reports.sales', compact('sales'));
    }

    public function salesPdf(Request $request)
    {
        $sales = Sale::with('client')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('purchases.reports.exports.sales-pdf', compact('sales'));
        return $pdf->download('reporte_ventas.pdf');
    }

    public function salesPrint(Request $request)
    {
        $sales = Sale::with('client')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        return view('purchases.reports.exports.sales-print', compact('sales'));
    }

    /* =========================================================
     | 🛒 COMPRAS
     | Vistas:
     |  - resources/views/purchases/reports/purchases.blade.php
     |  - resources/views/purchases/reports/exports/purchases-pdf.blade.php
     |  - resources/views/purchases/reports/exports/purchases-print.blade.php
     * ========================================================*/
    public function purchases(Request $request)
    {
        $purchases = Purchase::with('supplier')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        return view('purchases.reports.purchases', compact('purchases'));
    }

    public function purchasesPdf(Request $request)
    {
        $purchases = Purchase::with('supplier')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('purchases.reports.exports.purchases-pdf', compact('purchases'));
        return $pdf->download('reporte_compras.pdf');
    }

    public function purchasesPrint(Request $request)
    {
        $purchases = Purchase::with('supplier')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        return view('purchases.reports.exports.purchases-print', compact('purchases'));
    }

    /* =========================================================
     | 💳 CRÉDITOS / CUENTAS POR COBRAR
     | Vistas:
     |  - resources/views/purchases/reports/credits.blade.php
     |  - resources/views/purchases/reports/exports/credits-pdf.blade.php
     |  - resources/views/purchases/reports/exports/credits-print.blade.php
     * ========================================================*/
    public function credits(Request $request)
    {
        $credits = Credit::with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('due_date')
            ->get();

        return view('purchases.reports.credits', compact('credits'));
    }

    public function creditsPdf(Request $request)
    {
        $credits = Credit::with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('due_date')
            ->get();

        $pdf = Pdf::loadView('purchases.reports.exports.credits-pdf', compact('credits'));
        return $pdf->download('reporte_creditos.pdf');
    }

    public function creditsPrint(Request $request)
    {
        $credits = Credit::with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('due_date')
            ->get();

        return view('purchases.reports.exports.credits-print', compact('credits'));
    }

    /* =========================================================
     | 📦 INVENTARIO (Movimientos)
     | Vistas:
     |  - resources/views/purchases/reports/inventory.blade.php
     |  - resources/views/purchases/reports/exports/inventory-pdf.blade.php
     |  - resources/views/purchases/reports/exports/inventory-print.blade.php
     * ========================================================*/
    public function inventory(Request $request)
    {
        $movements = InventoryMovement::with('product')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        return view('purchases.reports.inventory', compact('movements'));
    }

    public function inventoryPdf(Request $request)
    {
        $movements = InventoryMovement::with('product')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('purchases.reports.exports.inventory-pdf', compact('movements'));
        return $pdf->download('reporte_inventario.pdf');
    }

    public function inventoryPrint(Request $request)
    {
        $movements = InventoryMovement::with('product')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->get();

        return view('purchases.reports.exports.inventory-print', compact('movements'));
    }
}
