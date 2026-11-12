<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Movimiento;

class InicioController extends Controller
{
    // Pantalla de inicio: un resumen del curso actual
    public function __invoke()
    {
        $curso = CursoLectivo::actual();

        $porDocente = Movimiento::where('curso_lectivo_id', $curso->id)
            ->selectRaw('docente_id, SUM(minutos) as total')
            ->groupBy('docente_id')
            ->pluck('total', 'docente_id');

        $activas = Docente::where('activo', true)->pluck('id');
        $saldosActivas = $activas->map(fn ($id) => (int) ($porDocente[$id] ?? 0));

        $hoy = now()->startOfDay();

        $resumen = [
            'minutos' => $saldosActivas->sum(),
            'docentes_con_saldo' => $saldosActivas->filter(fn ($m) => $m > 0)->count(),
            'ausencias_mes' => Ausencia::where('curso_lectivo_id', $curso->id)
                ->where('estado', 'activa')
                ->whereYear('fecha', $hoy->year)
                ->whereMonth('fecha', $hoy->month)
                ->count(),
            'dias_para_vencer' => (int) $hoy->diffInDays($curso->fecha_vencimiento->startOfDay(), false),
        ];

        $ultimasAusencias = Ausencia::with('docente')
            ->where('curso_lectivo_id', $curso->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('dashboard', compact('curso', 'resumen', 'ultimasAusencias'));
    }
}
