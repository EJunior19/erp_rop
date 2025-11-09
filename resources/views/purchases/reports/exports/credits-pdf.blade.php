<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Cuentas por Cobrar</title>
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
    th { background: #f4f4f4; }
  </style>
</head>
<body>
  <h2>Reporte de Cuentas por Cobrar</h2>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Cliente</th>
        <th>Monto</th>
        <th>Vencimiento</th>
        <th>Estado</th>
      </tr>
    </thead>
    <tbody>
      @foreach($credits as $c)
        <tr>
          <td>{{ $c->id }}</td>
          <td>{{ $c->client->name ?? '—' }}</td>
          <td>Gs. {{ number_format($c->monto, 0, ',', '.') }}</td>
          <td>{{ $c->fecha_vencimiento->format('Y-m-d') }}</td>
          <td>{{ ucfirst($c->estado) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
