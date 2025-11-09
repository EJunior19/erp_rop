<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;        
use App\Models\Purchase;    
use App\Models\Credit;      
use App\Models\InventoryMovement; 
use Barryvdh\DomPDF\Facade\Pdf; // Librería para exportar a PDF
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    // ------------------------------
    // 📊 Reporte de Ventas
    // ------------------------------
    public function sales(Request $request) {
        $sales = Sale::with('client')
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at','desc')
            ->get();

        return view('purchases.reports.sales', compact('sales'));
    }

    public function salesPdf(Request $request) {
        $sales = Sale::with('client')
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at','desc')
            ->get();

        $pdf = Pdf::loadView('reports.exports.sales-pdf', compact('sales'));
        return $pdf->download('reporte_ventas.pdf');
    }

    public function salesPrint(Request $request) {
        $sales = Sale::with('client')
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at','desc')
            ->get();

        return view('reports.exports.sales-print', compact('sales'));
    }

    // ------------------------------
    // 🛒 Reporte de Compras
    // ------------------------------
    public function purchases(Request $request) {
        $purchases = Purchase::with('supplier')
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at','desc')
            ->get();

        return view('reports.purchases', compact('purchases'));
    }

    public function purchasesPdf(Request $request) {
        $purchases = Purchase::with('supplier')
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at','desc')
            ->get();

        $pdf = Pdf::loadView('reports.exports.purchases-pdf', compact('purchases'));
        return $pdf->download('reporte_compras.pdf');
    }

    public function purchasesPrint(Request $request) {
        $purchases = Purchase::with('supplier')
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at','desc')
            ->get();

        return view('reports.exports.purchases-print', compact('purchases'));
    }

    // ------------------------------
    // 💳 Reporte de Cuentas por Cobrar
    // ------------------------------
    public function credits(Request $request) {
        $credits = Credit::with('client')
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->orderBy('due_date','asc')
            ->get();

        return view('reports.credits', compact('credits'));
    }

    public function creditsPdf(Request $request) {
        $credits = Credit::with('client')
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->orderBy('due_date','asc')
            ->get();

        $pdf = Pdf::loadView('reports.exports.credits-pdf', compact('credits'));
        return $pdf->download('reporte_creditos.pdf');
    }

    public function creditsPrint(Request $request) {
        $credits = Credit::with('client')
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->orderBy('due_date','asc')
            ->get();

        return view('reports.exports.credits-print', compact('credits'));
    }

    // ------------------------------
    // 📦 Reporte de Inventario
    // ------------------------------
    public function inventory(Request $request) {
        $movements = InventoryMovement::with('product')
            ->orderBy('created_at','desc')
            ->get();

        return view('purchases.reports.inventory', compact('movements'));
    }

    public function inventoryPdf(Request $request) {
        $movements = InventoryMovement::with('product')
            ->orderBy('created_at','desc')
            ->get();

        $pdf = Pdf::loadView('reports.exports.inventory-pdf', compact('movements'));
        return $pdf->download('reporte_inventario.pdf');
    }

    public function inventoryPrint(Request $request) {
        $movements = InventoryMovement::with('product')
            ->orderBy('created_at','desc')
            ->get();

        return view('reports.exports.inventory-print', compact('movements'));
    
    // Total pendiente (acepta 'pending' y 'pendiente', case-insensitive)
        $collection = $credits instanceof AbstractPaginator ? $credits->getCollection() : collect($credits);
        $pendingTotal = $collection
            ->filter(fn ($c) => in_array(Str::lower($c->status), ['pending','pendiente']))
            ->sum('amount');

        return view('reports.credits', compact('credits', 'pendingTotal'));
    }
}
