<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Compras</title>
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
    th { background: #f4f4f4; }
  </style>
</head>
<body>
  <h2>Reporte de Compras</h2>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Proveedor</th>
        <th>Total</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      @foreach($purchases as $p)
        <tr>
          <td>{{ $p->id }}</td>
          <td>{{ $p->supplier->name ?? '—' }}</td>
          <td>Gs. {{ number_format($p->total, 0, ',', '.') }}</td>
          <td>{{ $p->created_at->format('Y-m-d') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
