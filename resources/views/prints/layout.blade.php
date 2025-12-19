<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>@yield('title')</title>

<style>
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    font-size: 11px;
    width: 260px;
    margin: 0 auto;
    padding: 10px;
    color: #000;
  }
  .bold { font-weight: bold; }
  .line { border-top: 1px dashed #000; margin: 4px 0; }
  .center { text-align: center; }

  @media print {
    .no-print { display: none; }
    body { width: 260px; }
  }
</style>

</head>
<body>
  @yield('content')
</body>
</html>
