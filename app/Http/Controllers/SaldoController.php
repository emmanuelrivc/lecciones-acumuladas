<?php

namespace App\Http\Controllers;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Movimiento;
use App\Models\SolicitudUso;
use App\Services\SaldoService;
use DomainException;
use Illuminate\Http\Request;

class SaldoController extends Controller
{
    // Lista de docentes con su saldo
    public function index()
    {
        $curso = CursoLectivo::actual();

        $saldos = $curso
            ? Movimiento::where('curso_lectivo_id', $curso->id)
                ->selectRaw('docente_id, SUM(minutos) as total')
                ->groupBy('docente_id')
                ->pluck('total', 'docente_id')
            : collect();

        $docentes = Docente::where('activo', true)->orderBy('nombre')->get()
            ->map(function (Docente $d) use ($saldos) {
                $d->saldo_minutos = (int) ($saldos[$d->id] ?? 0);
                return $d;
            });

        return view('saldos.index', compact('curso', 'docentes'));
    }

    // Historial de una docente: de dónde sale cada minuto
    public function show(Docente $docente)
    {
        $curso = CursoLectivo::actual();

        $movimientos = Movimiento::where('docente_id', $docente->id)
            ->where('curso_lectivo_id', $curso->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $saldo = $docente->saldoEn($curso->id);

        // Ids de movimientos que ya fueron revertidos (para no ofrecer el botón otra vez)
        $revertidos = Movimiento::whereIn('revierte_id', $movimientos->pluck('id'))
            ->pluck('revierte_id')
            ->all();

        return view('saldos.show', compact('docente', 'curso', 'movimientos', 'saldo', 'revertidos'));
    }

    // Registra que la docente USÓ minutos acumulados
    public function usar(Request $request, Docente $docente, SaldoService $saldos)
    {
        $datos = $request->validate([
            'minutos' => ['required', 'integer', 'min:1'],
            'fecha'   => ['required', 'date'],
            'nota'    => ['nullable', 'string', 'max:255'],
        ]);

        $curso = CursoLectivo::actual();

        try {
            $movimiento = $saldos->usar($docente, $curso->id, (int) $datos['minutos'], $datos['fecha'], $datos['nota'] ?? null);
        } catch (DomainException $e) {
            // La regla de negocio dijo que no (ej. saldo insuficiente): mostramos el motivo
            return back()->withErrors(['minutos' => $e->getMessage()])->withInput();
        }

        // Si la docente tenía solicitudes pendientes que estos minutos ya cubren, se dan por resueltas
        $restante = (int) $datos['minutos'];
        $pendientes = SolicitudUso::where('docente_id', $docente->id)
            ->where('curso_lectivo_id', $curso->id)
            ->where('estado', 'pendiente')
            ->orderBy('fecha_uso')
            ->get();

        foreach ($pendientes as $solicitud) {
            if ($solicitud->minutos > $restante) {
                continue;
            }
            $restante -= $solicitud->minutos;
            $solicitud->update([
                'estado'          => 'aprobada',
                'resuelta_por'    => $request->user()->id,
                'resuelta_en'     => now(),
                'nota_resolucion' => 'Resuelta al descontar los minutos desde Saldos',
                'movimiento_id'   => $movimiento->id,
            ]);
        }

        return redirect()->route('saldos.show', $docente)->with('exito', 'Uso de minutos registrado.');
    }

    // Corrección manual: suma (positivo) o resta (negativo) minutos, con motivo
    public function ajustar(Request $request, Docente $docente, SaldoService $saldos)
    {
        $datos = $request->validate([
            'minutos' => ['required', 'integer', 'not_in:0'],
            'fecha'   => ['required', 'date'],
            'motivo'  => ['required', 'string', 'max:255'],
        ]);

        $curso = CursoLectivo::actual();

        try {
            $saldos->ajustar($docente, $curso->id, (int) $datos['minutos'], $datos['fecha'], $datos['motivo']);
        } catch (DomainException $e) {
            return back()->withErrors(['minutos' => $e->getMessage()])->withInput();
        }

        return redirect()->route('saldos.show', $docente)->with('exito', 'Ajuste registrado.');
    }

    // Deshace un movimiento (por ejemplo un uso por error) con un movimiento contrario
    public function revertir(Movimiento $movimiento, SaldoService $saldos)
    {
        try {
            $saldos->revertir($movimiento);
        } catch (DomainException $e) {
            return back()->withErrors(['movimiento' => $e->getMessage()]);
        }

        return redirect()->route('saldos.show', $movimiento->docente_id)->with('exito', 'Movimiento revertido.');
    }
}
