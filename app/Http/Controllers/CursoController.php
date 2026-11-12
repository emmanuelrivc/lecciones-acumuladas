<?php

namespace App\Http\Controllers;

use App\Models\CursoLectivo;
use App\Models\Movimiento;
use Illuminate\Http\Request;

class CursoController extends Controller
{
    // Estado del curso: abierto o cerrado, y cuántos minutos hay acumulados
    public function index()
    {
        $curso = CursoLectivo::actual();
        $minutosAcumulados = (int) Movimiento::where('curso_lectivo_id', $curso->id)->sum('minutos');

        return view('curso.index', compact('curso', 'minutosAcumulados'));
    }

    // Cierra el curso. Para evitar un clic por error, hay que escribir el año
    public function cerrar(Request $request)
    {
        $curso = CursoLectivo::actual();

        $request->validate(
            ['confirmacion' => ['required', 'in:'.$curso->anio]],
            ['confirmacion.in' => 'Escriba el año del curso ('.$curso->anio.') para confirmar el cierre.']
        );

        $curso->update(['cerrado' => true]);

        return redirect()->route('curso.index')->with('exito', 'Curso cerrado: ahora es solo de consulta.');
    }

    // Reabre el curso (por si se cerró por error)
    public function reabrir()
    {
        CursoLectivo::actual()->update(['cerrado' => false]);

        return redirect()->route('curso.index')->with('exito', 'Curso reabierto.');
    }
}
