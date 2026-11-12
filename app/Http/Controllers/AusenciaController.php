<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\AusenciaLeccion;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Horario;
use App\Services\DisponibilidadService;
use App\Services\SaldoService;
use App\Support\EtiquetaDocente;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AusenciaController extends Controller
{
    // Lista de ausencias registradas, con el botón para anular
    public function index()
    {
        $curso = CursoLectivo::actual();

        $ausencias = Ausencia::with('docente')
            ->withCount('lecciones')
            ->where('curso_lectivo_id', $curso->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return view('ausencias.index', compact('curso', 'ausencias'));
    }

    // Anula una ausencia y revierte los minutos que había sumado
    public function anular(Ausencia $ausencia, SaldoService $saldos)
    {
        try {
            $saldos->anularAusencia($ausencia);
        } catch (DomainException $e) {
            return back()->withErrors(['ausencia' => $e->getMessage()]);
        }

        return redirect()->route('ausencias.index')->with('exito', 'Ausencia anulada y minutos revertidos.');
    }

    // Muestra el formulario. Si ya viene docente y fecha, también muestra sus lecciones de ese día.
    public function create(Request $request, DisponibilidadService $disponibilidad)
    {
        $docentes = Docente::where('activo', true)->orderBy('nombre')->get();

        $docente = null;
        $fecha = null;
        $lecciones = collect();
        $libres = [];

        if ($request->filled('docente_id') && $request->filled('fecha')) {
            $datos = $request->validate([
                'docente_id' => ['required', 'exists:docentes,id'],
                'fecha'      => ['required', 'date'],
            ]);

            $docente = Docente::findOrFail($datos['docente_id']);
            $fecha = Carbon::parse($datos['fecha']);

            $lecciones = Horario::with(['grupo.guia', 'materia', 'franja'])
                ->where('docente_id', $docente->id)
                ->where('dia_semana', $fecha->dayOfWeekIso)
                ->whereDate('vigente_desde', '<=', $fecha->toDateString())
                ->where(function ($q) use ($fecha) {
                    $q->whereNull('vigente_hasta')
                      ->orWhereDate('vigente_hasta', '>=', $fecha->toDateString());
                })
                ->get()
                ->sortBy('franja.numero');

            // Quién está libre en cada lección (para sugerírselo a la directora)
            $libres = $disponibilidad->libres($fecha, $docente, $lecciones, $docentes);
        }

        // De qué materia (o qué grupo) es cada docente, para mostrarlo en la lista
        $rotulos = EtiquetaDocente::para(CursoLectivo::actual()->id);

        // La lista de "quién faltó": por materia especial, y las guías abajo
        $docentesAgrupados = EtiquetaDocente::agrupar($docentes, $rotulos);

        return view('ausencias.create', compact('docentes', 'docente', 'fecha', 'lecciones', 'libres', 'rotulos', 'docentesAgrupados'));
    }

    // Guarda la ausencia, sus lecciones y suma los minutos a quien cubrió
    public function store(Request $request, SaldoService $saldos)
    {
        $datos = $request->validate([
            'docente_id'    => ['required', 'exists:docentes,id'],
            'fecha'         => ['required', 'date'],
            'motivo'        => ['required', 'string', 'max:255'],
            'justificacion' => ['nullable', 'string'],
            'cubre'         => ['nullable', 'array'],
            // Quien cubre debe existir y no puede ser la misma docente que faltó
            'cubre.*'       => ['nullable', 'exists:docentes,id', 'different:docente_id'],
            // Lecciones en las que SÍ faltó (la pantalla manda 'parcial' junto con las marcadas)
            'faltas'        => ['nullable', 'array'],
            'faltas.*'      => ['integer'],
        ]);

        // Con 'parcial' (la pantalla normal) solo cuentan las lecciones marcadas;
        // sin 'parcial' se mantiene el comportamiento anterior (todo el día)
        $parcial = $request->has('parcial');
        $idsFaltadas = $parcial ? array_map('intval', $datos['faltas'] ?? []) : [];
        $franjasFaltadas = null;

        if ($parcial) {
            if ($idsFaltadas === []) {
                throw ValidationException::withMessages([
                    'faltas' => 'Marque al menos una lección en la que faltó.',
                ]);
            }

            $horariosFaltados = Horario::where('docente_id', $datos['docente_id'])->whereIn('id', $idsFaltadas)->get();
            if ($horariosFaltados->count() !== count(array_unique($idsFaltadas))) {
                throw ValidationException::withMessages([
                    'faltas' => 'Alguna de las lecciones marcadas no es de esa docente.',
                ]);
            }
            $franjasFaltadas = $horariosFaltados->pluck('franja_id')->unique()->values()->all();
        }

        $curso = CursoLectivo::actual();
        if ($curso->cerrado) {
            throw ValidationException::withMessages([
                'docente_id' => 'El curso lectivo está cerrado: solo se puede consultar.',
            ]);
        }

        $fecha = Carbon::parse($datos['fecha'])->toDateString();

        // No registrar dos veces la misma ausencia
        $yaExiste = Ausencia::where('docente_id', $datos['docente_id'])
            ->whereDate('fecha', $fecha) // whereDate compara solo el día, sin la hora
            ->where('estado', 'activa')
            ->exists();

        if ($yaExiste) {
            throw ValidationException::withMessages([
                'docente_id' => 'Ya hay una ausencia registrada para esa docente en esa fecha.',
            ]);
        }

        // TRANSACCIÓN: o se guarda todo, o no se guarda nada
        DB::transaction(function () use ($datos, $curso, $fecha, $request, $saldos, $parcial, $idsFaltadas, $franjasFaltadas) {
            $ausencia = Ausencia::create([
                'docente_id'       => $datos['docente_id'],
                'curso_lectivo_id' => $curso->id,
                'registrada_por'   => $request->user()->id,
                'fecha'            => $fecha,
                'motivo'           => $datos['motivo'],
                'aviso_previo'     => $request->boolean('aviso_previo'),
                'justificacion'    => $datos['justificacion'] ?? null,
                'estado'           => 'activa',
                'franjas_faltadas' => $franjasFaltadas,
            ]);

            foreach (($datos['cubre'] ?? []) as $horarioId => $cubreId) {
                if ($parcial && ! in_array((int) $horarioId, $idsFaltadas, true)) {
                    continue; // lección en la que sí vino: no cuenta
                }
                if (! $cubreId) {
                    continue; // lección que nadie cubrió: no suma minutos
                }

                // Solo se aceptan lecciones que SÍ son de esa docente
                $horario = Horario::where('docente_id', $datos['docente_id'])->findOrFail($horarioId);

                $leccion = AusenciaLeccion::create([
                    'ausencia_id'      => $ausencia->id,
                    'grupo_id'         => $horario->grupo_id,
                    'cubre_docente_id' => $cubreId,
                    'fecha'            => $fecha,
                    'franja_id'        => $horario->franja_id,
                    'minutos'          => 40,
                ]);

                $saldos->acumular($leccion); // suma 40 minutos a quien cubrió
            }
        });

        // Si alguna lección quedó sin elegir quién cubre, se avisa (esas no suman minutos)
        if ($parcial) {
            $cubiertas = collect($datos['cubre'] ?? [])
                ->filter(fn ($id, $horarioId) => $id && in_array((int) $horarioId, $idsFaltadas, true))
                ->count();
            $sinCubrir = count(array_unique($idsFaltadas)) - $cubiertas;
        } else {
            $sinCubrir = collect($datos['cubre'] ?? [])->filter(fn ($id) => ! $id)->count();
        }

        $mensaje = 'Ausencia registrada y minutos acumulados.';
        if ($sinCubrir > 0) {
            $mensaje .= " Atención: {$sinCubrir} lección(es) quedaron sin quien cubra y no sumaron minutos.";
        }

        return redirect()->route('saldos.index')->with('exito', $mensaje);
    }
}
