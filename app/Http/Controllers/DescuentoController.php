<?php

namespace App\Http\Controllers;

use App\Models\CursoLectivo;
use App\Models\DescuentoGeneral;
use App\Models\Grupo;
use App\Models\Docente;
use App\Models\Movimiento;
use App\Services\SaldoService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DescuentoController extends Controller
{
    // Formulario + lista de descuentos generales ya aplicados
    public function index()
    {
        $curso = CursoLectivo::actual();

        $descuentos = DescuentoGeneral::where('curso_lectivo_id', $curso->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        // Por cada descuento: a cuántas docentes se aplicó y cuántos minutos se quitaron en total
        $resumen = Movimiento::where('tipo', SaldoService::DESCUENTO)
            ->whereIn('descuento_general_id', $descuentos->pluck('id'))
            ->selectRaw('descuento_general_id, COUNT(*) as docentes, SUM(minutos) as total')
            ->groupBy('descuento_general_id')
            ->get()
            ->keyBy('descuento_general_id');

        // Quiénes fueron afectadas por cada descuento, con lo que se les quitó
        $afectadas = Movimiento::with('docente')
            ->where('tipo', SaldoService::DESCUENTO)
            ->whereIn('descuento_general_id', $descuentos->pluck('id'))
            ->get()
            ->sortBy(fn (Movimiento $m) => $m->docente->nombre)
            ->groupBy('descuento_general_id');

        // Solo las docentes guía pueden recibir el descuento general
        $guias = $this->guiasDelCurso($curso->id)
            ->map(function (Docente $d) use ($curso) {
                $d->saldo_minutos = $d->saldoEn($curso->id);
                return $d;
            });

        return view('descuentos.index', compact('curso', 'descuentos', 'resumen', 'afectadas', 'guias'));
    }

    // Aplica un descuento general a todo el personal activo
    public function store(Request $request, SaldoService $saldos)
    {
        $datos = $request->validate([
            'minutos' => ['required', 'integer', 'min:1'],
            'fecha'   => ['required', 'date'],
            'motivo'  => ['required', 'string', 'max:255'],
            'docente_ids'   => ['required', 'array', 'min:1'],
            'docente_ids.*' => ['integer'],
        ], [
            'docente_ids.required' => 'Elija al menos una docente.',
            'docente_ids.min'      => 'Elija al menos una docente.',
        ]);

        $curso = CursoLectivo::actual();

        // Aunque alguien mande otros ids, solo cuentan las guías del curso
        $permitidas = $this->guiasDelCurso($curso->id)->pluck('id')->all();
        $elegidas = array_values(array_intersect($datos['docente_ids'], $permitidas));

        if ($elegidas === []) {
            throw ValidationException::withMessages([
                'docente_ids' => 'Solo se puede descontar a docentes guía.',
            ]);
        }

        try {
            $saldos->aplicarDescuentoGeneral($curso->id, (int) $datos['minutos'], $datos['fecha'], $datos['motivo'], $elegidas);
        } catch (DomainException $e) {
            return back()->withErrors(['minutos' => $e->getMessage()])->withInput();
        }

        return redirect()->route('descuentos.index')->with('exito', 'Descuento general aplicado.');
    }

    // Deshace un descuento general: devuelve a cada docente lo que se le quitó
    public function revertir(DescuentoGeneral $descuento, SaldoService $saldos)
    {
        try {
            $saldos->revertirDescuentoGeneral($descuento);
        } catch (DomainException $e) {
            return back()->withErrors(['descuento' => $e->getMessage()]);
        }

        return redirect()->route('descuentos.index')->with('exito', 'Descuento revertido: se devolvieron los minutos.');
    }

    // Docentes activas que son guía de algún grupo del curso, en orden alfabético
    private function guiasDelCurso(int $cursoId)
    {
        $ids = Grupo::where('curso_lectivo_id', $cursoId)->pluck('guia_id')->unique();

        return Docente::where('activo', true)->whereIn('id', $ids)->orderBy('nombre')->get();
    }
}
