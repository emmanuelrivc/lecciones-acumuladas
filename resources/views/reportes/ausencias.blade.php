<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Historial de ausencias</title>
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
    <h1>Escuela Los Ángeles — Historial de ausencias</h1>
    <div class="sub">Curso lectivo {{ $curso->anio }}</div>

    <table>
        <thead>
            <tr><th>Fecha</th><th>Docente</th><th>Motivo</th><th class="der">Lecciones</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($ausencias as $a)
                <tr class="{{ $a->estado === 'anulada' ? 'anulada' : '' }}">
                    <td>{{ $a->fecha->format('d/m/Y') }}</td>
                    <td>{{ $a->docente->nombre }}</td>
                    <td>{{ $a->motivo }}</td>
                    <td class="der">{{ $a->lecciones_count }}</td>
                    <td>{{ $a->estado === 'anulada' ? 'Anulada' : 'Activa' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No hay ausencias registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pie">Generado el {{ $generado->format('d/m/Y H:i') }}</div>
</body>
</html>
