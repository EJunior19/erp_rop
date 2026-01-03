<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;

class DashboardController extends Controller
{
    public function index(Request $req)
    {
        $hoy = now()->toDateString();

        // =============================
        // KPIs PRINCIPALES
        // =============================

        // Clientes
        $clientes = (int) Client::count();

        // Proveedores (para que NO se rompa tu Blade si lo muestra)
        $proveedores = Schema::hasTable('suppliers')
            ? (int) DB::table('suppliers')->count()
            : 0;

        // Productos
        $productos = (int) Product::count();

        // Ventas HOY
        $ventasHoy      = (int) DB::table('sales')->whereDate('created_at', $hoy)->count();
        $montoVentasHoy = (float) DB::table('sales')->whereDate('created_at', $hoy)->sum('total');

        // Ventas MES
        $inicioMes      = now()->startOfMonth();
        $ventasMes      = (int) DB::table('sales')->whereBetween('created_at', [$inicioMes, now()])->count();
        $montoVentasMes = (float) DB::table('sales')->whereBetween('created_at', [$inicioMes, now()])->sum('total');

        // Pendientes de aprobación
        $ventasPendientes = (int) DB::table('sales')->where('status', 'pendiente_aprobacion')->count();

        // Stock bajo (NO tenés stock_min, usamos umbral fijo)
        $stockMin = 5;
        $stockBajo = (int) DB::table('products')
            ->where('stock', '<=', $stockMin)
            ->count();

        // Movimientos últimas 24h
        $mov24h = Schema::hasTable('inventory_movements')
            ? (int) DB::table('inventory_movements')->where('created_at', '>=', now()->subDay())->count()
            : 0;

        // =============================
        // LISTAS (actividad reciente)
        // =============================

        // Últimas ventas (cliente + total)
        $ultimasVentas = DB::table('sales')
            ->leftJoin('clients', 'clients.id', '=', 'sales.client_id')
            ->orderByDesc('sales.id')
            ->limit(10)
            ->get([
                'sales.id',
                'sales.status',
                'sales.total',
                'sales.created_at',
                DB::raw("COALESCE(clients.name,'—') as client_name"),
            ]);

        // Últimas compras:
        // Tu tabla purchases NO tiene total. El total real está en purchase_orders.total.
        $ultimasCompras = collect();

        if (Schema::hasTable('purchase_orders')) {
            $ultimasCompras = DB::table('purchase_orders')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->orderByDesc('purchase_orders.id')
                ->limit(10)
                ->get([
                    'purchase_orders.id',
                    'purchase_orders.total',
                    'purchase_orders.status',
                    'purchase_orders.created_at',
                    DB::raw("COALESCE(suppliers.name,'—') as supplier_name"),
                ]);
        }

        // Inventario reciente
        $ultimosMov = collect();
        if (Schema::hasTable('inventory_movements')) {
            $ultimosMov = DB::table('inventory_movements')
                ->leftJoin('products', 'products.id', '=', 'inventory_movements.product_id')
                ->leftJoin('users', 'users.id', '=', 'inventory_movements.user_id')
                ->orderByDesc('inventory_movements.id')
                ->limit(10)
                ->get([
                    'inventory_movements.id',
                    'inventory_movements.type',
                    'inventory_movements.qty',
                    'inventory_movements.reason',
                    'inventory_movements.created_at',
                    DB::raw("COALESCE(products.name,'—') as product_name"),
                    DB::raw("COALESCE(users.name,'Sistema') as user_name"),
                ]);
        }

        return view('dashboard', compact(
            'clientes','proveedores','productos',
            'ventasHoy','montoVentasHoy',
            'ventasMes','montoVentasMes',
            'ventasPendientes',
            'stockBajo','stockMin',
            'mov24h',
            'ultimasVentas','ultimasCompras','ultimosMov',
        ));
    }

    // Endpoint JSON opcional (si querés charts)
    public function stats(Request $req)
    {
        $from = $req->date('from') ?? now()->startOfMonth();
        $to   = $req->date('to')   ?? now();

        $kpis = [
            'clientes_total'  => (int) DB::table('clients')->count(),
            'ventas_total'    => (int) DB::table('sales')->whereBetween('created_at', [$from, $to])->count(),
            'ventas_monto'    => (float) DB::table('sales')->whereBetween('created_at', [$from, $to])->sum('total'),
            'pendientes'      => (int) DB::table('sales')->where('status', 'pendiente_aprobacion')->count(),
            'stock_bajo'      => (int) DB::table('products')->where('stock', '<=', 5)->count(),
            'movimientos_24h' => Schema::hasTable('inventory_movements')
                                ? (int) DB::table('inventory_movements')->where('created_at','>=', now()->subDay())->count()
                                : 0,
        ];

        // Serie ventas por día (Postgres)
        $ventasPorDia = DB::table('sales')
            ->selectRaw("DATE(created_at) as fecha, COUNT(*) as cantidad, SUM(total) as total")
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw("DATE(created_at)")
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'kpis'      => $kpis,
            'ventasDia' => $ventasPorDia,
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
        ]);
    }
}
