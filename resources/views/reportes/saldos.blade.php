<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Saldos del personal</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  h1 { font-size: 16px; margin: 0 0 2px; }
  .sub { color: #555; margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #eee; text-align: left; padding: 5px 6px; border-bottom: 1px solid #999; }
  td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
  .der { text-align: right; }
  .pie { margin-top: 14px; color: #666; font-size: 9px; }
  .anulada { color: #888; }
</style>
</head>
<body>
    <h1>Escuela Los Ángeles — Saldos del personal</h1>
    <div class="sub">Curso lectivo {{ $curso->anio }} · Vencen el {{ $curso->fecha_vencimiento->format('d/m/Y') }}</div>

    <table>
        <thead>
            <tr><th>Docente</th><th class="der">Minutos</th><th class="der">Lecciones (40 min)</th></tr>
        </thead>
        <tbody>
            @foreach ($personal as $d)
                <tr>
                    <td>{{ $d->nombre }}</td>
                    <td class="der">{{ $d->saldo_minutos }}</td>
                    <td class="der">{{ number_format($d->saldo_minutos / 40, 1, ',', '') }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total</strong></td>
                <td class="der"><strong>{{ $personal->sum('saldo_minutos') }}</strong></td>
                <td class="der"><strong>{{ number_format($personal->sum('saldo_minutos') / 40, 1, ',', '') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="pie">Generado el {{ $generado->format('d/m/Y H:i') }}</div>
</body>
</html>
