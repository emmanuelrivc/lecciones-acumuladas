<?php

namespace App\Http\Controllers;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Movimiento;
use App\Models\SolicitudUso;
use App\Services\SaldoService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitudController extends Controller
{
    // Pendientes arriba (para resolver) y las ya resueltas abajo
    public function index()
    {
        $curso = CursoLectivo::actual();

        $pendientes = SolicitudUso::with('docente')
            ->where('curso_lectivo_id', $curso->id)
            ->where('estado', 'pendiente')
            ->orderBy('fecha_uso')
            ->get();

        $resueltas = SolicitudUso::with('docente')
            ->where('curso_lectivo_id', $curso->id)
            ->where('estado', '!=', 'pendiente')
            ->orderByDesc('resuelta_en')
            ->limit(30)
            ->get();

        // Solo pueden pedir las docentes que tienen minutos acumulados
        $saldos = Movimiento::where('curso_lectivo_id', $curso->id)
            ->selectRaw('docente_id, SUM(minutos) as total')
            ->groupBy('docente_id')
            ->pluck('total', 'docente_id');

        // A cada pendiente se le anota el saldo actual de la docente
        $pendientes->each(fn (SolicitudUso $s) => $s->saldo_actual = (int) ($saldos[$s->docente_id] ?? 0));

        $docentes = Docente::where('activo', true)->orderBy('nombre')->get()
            ->map(function (Docente $d) use ($saldos) {
                $d->saldo_minutos = (int) ($saldos[$d->id] ?? 0);
                return $d;
            })
            ->filter(fn (Docente $d) => $d->saldo_minutos > 0)
            ->values();

        return view('solicitudes.index', compact('curso', 'pendientes', 'resueltas', 'docentes'));
    }

    // Anota una solicitud nueva (queda PENDIENTE; todavía no se descuenta nada)
    public function store(Request $request)
    {
        $datos = $request->validate([
            'docente_id' => ['required', 'exists:docentes,id'],
            'minutos'    => ['required', 'integer', 'min:1'],
            'fecha_uso'  => ['required', 'date'],
        ]);

        $curso = CursoLectivo::actual();

        if ($curso->cerrado) {
            throw ValidationException::withMessages([
                'docente_id' => 'El curso lectivo está cerrado: solo se puede consultar.',
            ]);
        }

        $docente = Docente::findOrFail($datos['docente_id']);
        $saldo = $docente->saldoEn($curso->id);

        if ((int) $datos['minutos'] > $saldo) {
            throw ValidationException::withMessages([
                'minutos' => "{$docente->nombre} solo tiene {$saldo} minutos acumulados.",
            ]);
        }

        SolicitudUso::create([
            'docente_id'       => $docente->id,
            'curso_lectivo_id' => $curso->id,
            'minutos'          => (int) $datos['minutos'],
            'fecha_uso'        => $datos['fecha_uso'],
            'estado'           => 'pendiente',
            'registrada_por'   => $request->user()->id,
        ]);

        return redirect()->route('solicitudes.index')->with('exito', 'Solicitud registrada. Está pendiente de aprobación.');
    }

    // Aprueba: descuenta los minutos con la misma regla de siempre (SaldoService::usar)
    public function aprobar(Request $request, SolicitudUso $solicitud, SaldoService $saldos)
    {
        if ($solicitud->estado !== 'pendiente') {
            return back()->withErrors(['solicitud' => 'Esa solicitud ya fue resuelta.']);
        }

        try {
            DB::transaction(function () use ($solicitud, $saldos, $request) {
                $movimiento = $saldos->usar(
                    $solicitud->docente,
                    $solicitud->curso_lectivo_id,
                    $solicitud->minutos,
                    $solicitud->fecha_uso->toDateString(),
                    $solicitud->motivo ? 'Uso aprobado: '.$solicitud->motivo : 'Uso de minutos aprobado'
                );

                $solicitud->update([
                    'estado'        => 'aprobada',
                    'resuelta_por'  => $request->user()->id,
                    'resuelta_en'   => now(),
                    'movimiento_id' => $movimiento->id,
                ]);
            });
        } catch (DomainException $e) {
            // Ej. el saldo ya no alcanza o el curso se cerró: la solicitud sigue pendiente
            return back()->withErrors(['solicitud' => $e->getMessage()]);
        }

        return redirect()->route('solicitudes.index')->with('exito', 'Solicitud aprobada y minutos descontados.');
    }

    // Rechaza: no se toca el saldo; se guarda la razón
    public function rechazar(Request $request, SolicitudUso $solicitud)
    {
        $datos = $request->validate([
            'nota' => ['required', 'string', 'max:255'],
        ]);

        if ($solicitud->estado !== 'pendiente') {
            return back()->withErrors(['solicitud' => 'Esa solicitud ya fue resuelta.']);
        }

        $solicitud->update([
            'estado'          => 'rechazada',
            'resuelta_por'    => $request->user()->id,
            'resuelta_en'     => now(),
            'nota_resolucion' => $datos['nota'],
        ]);

        return redirect()->route('solicitudes.index')->with('exito', 'Solicitud rechazada.');
    }
}
