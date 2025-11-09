<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Ventas</title>
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
    th { background: #f4f4f4; }
    h2 { margin-bottom: 0; }
  </style>
</head>
<body>
  <h2>Reporte de Ventas</h2>
  <p>Período: {{ $from ?? '—' }} al {{ $to ?? '—' }}</p>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Cliente</th>
        <th>Total</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      @foreach($sales as $s)
        <tr>
          <td>{{ $s->id }}</td>
          <td>{{ $s->client->name ?? '—' }}</td>
          <td>Gs. {{ number_format($s->total, 0, ',', '.') }}</td>
          <td>{{ $s->created_at->format('Y-m-d') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
