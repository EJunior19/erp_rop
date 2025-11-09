<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Inventario</title>
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
    th { background: #f4f4f4; }
  </style>
</head>
<body>
  <h2>Reporte de Inventario</h2>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Producto</th>
        <th>Cantidad</th>
        <th>Movimiento</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      @foreach($inventory as $i)
        <tr>
          <td>{{ $i->id }}</td>
          <td>{{ $i->product->name ?? '—' }}</td>
          <td>{{ $i->cantidad }}</td>
          <td>{{ ucfirst($i->tipo) }}</td>
          <td>{{ $i->created_at->format('Y-m-d') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
