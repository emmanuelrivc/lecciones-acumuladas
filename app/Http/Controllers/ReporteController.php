<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Movimiento;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    // Pantalla con los reportes disponibles
    public function index()
    {
        return view('reportes.index', ['curso' => CursoLectivo::actual()]);
    }

    // PDF: saldo de cada docente activa
    public function saldos()
    {
        $curso = CursoLectivo::actual();

        $totales = Movimiento::where('curso_lectivo_id', $curso->id)
            ->selectRaw('docente_id, SUM(minutos) as total')
            ->groupBy('docente_id')
            ->pluck('total', 'docente_id');

        $personal = Docente::where('activo', true)->orderBy('nombre')->get()
            ->map(function (Docente $d) use ($totales) {
                $d->saldo_minutos = (int) ($totales[$d->id] ?? 0);
                return $d;
            });

        return Pdf::loadView('reportes.saldos', [
            'curso' => $curso,
            'personal' => $personal,
            'generado' => now(),
        ])->stream('saldos-del-personal-'.$curso->anio.'.pdf');
    }

    // PDF: todas las ausencias del curso
    public function ausencias()
    {
        $curso = CursoLectivo::actual();

        $ausencias = Ausencia::with('docente')
            ->withCount('lecciones')
            ->where('curso_lectivo_id', $curso->id)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return Pdf::loadView('reportes.ausencias', [
            'curso' => $curso,
            'ausencias' => $ausencias,
            'generado' => now(),
        ])->stream('historial-de-ausencias-'.$curso->anio.'.pdf');
    }
}
