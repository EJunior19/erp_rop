<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Recepción #{{ $purchase_receipt->id }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Courier New", monospace;
            font-size: 11px;
            margin: 0;
            padding: 8px;
        }
        .ticket {
            width: 260px;    /* aprox 80mm */
            margin: 0 auto;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .mt-4 { margin-top: 4px; }
        .mt-8 { margin-top: 8px; }
        .separator {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            font-size: 11px;
            padding: 2px 0;
        }
        th {
            border-bottom: 1px dashed #000;
        }
        .text-right { text-align: right; }
        .text-left  { text-align: left; }

        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
<div class="ticket">

    <div class="center bold">
        RECEPCIÓN DE MERCADERÍA
    </div>

    <div class="mt-4">
        N° Recepción: <span class="bold">{{ $purchase_receipt->receipt_number }}</span><br>
        Fecha: {{ optional($purchase_receipt->received_date)->format('d/m/Y') }}<br>
        Proveedor: {{ $purchase_receipt->order?->supplier?->name ?? '-' }}<br>
        Orden Compra: {{ $purchase_receipt->order?->order_number ?? '-' }}<br>
        Recibido por: {{ optional($purchase_receipt->receivedBy)->name ?? '-' }}
    </div>

    <div class="separator"></div>

    <div class="bold">Ítems recibidos</div>

    <table class="mt-4">
        <thead>
        <tr>
            <th class="text-left">Prod.</th>
            <th class="text-right">Cant</th>
            <th class="text-right">Costo</th>
            <th class="text-right">Subt.</th>
        </tr>
        </thead>
        <tbody>
        @php
            $totalQty = 0;
            $totalVal = 0;
        @endphp

        @forelse ($purchase_receipt->items as $item)
            @php
                $totalQty += (int) $item->received_qty;
                $totalVal += (float) $item->subtotal;
            @endphp
            <tr>
                <td class="text-left">
                    {{ \Illuminate\Support\Str::limit($item->product?->name ?? '-', 14) }}
                </td>
                <td class="text-right">
                    {{ (int) $item->received_qty }}
                </td>
                <td class="text-right">
                    {{ number_format((float) $item->unit_cost, 0, ',', '.') }}
                </td>
                <td class="text-right">
                    {{ number_format((float) $item->subtotal, 0, ',', '.') }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">Sin ítems.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="separator"></div>

    <table>
        <tr>
            <td class="text-left bold">Total ítems:</td>
            <td class="text-right bold">{{ $totalQty }}</td>
        </tr>
        <tr>
            <td class="text-left bold">Total valor:</td>
            <td class="text-right bold">
                ₲ {{ number_format($totalVal, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    <div class="mt-8">
        Estado: <span class="bold">{{ ucfirst($purchase_receipt->status) }}</span><br>
        Creado: {{ optional($purchase_receipt->created_at)->format('d/m/Y H:i') }}<br>
        Aprobado por: {{ optional($purchase_receipt->approvedBy)->name ?? '-' }}
    </div>

    <div class="mt-8 center">
        --------------------------------<br>
        Firma encargado de recepción
    </div>

</div>

<script>
    // Abrir diálogo de impresión automáticamente
    window.print();
</script>
</body>
</html>
