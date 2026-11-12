<?php

namespace App\Http\Controllers;

use App\Models\AsignacionMateria;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Movimiento;
use App\Services\SaldoService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    private const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];

    // Lista de todo el personal (activo e inactivo) con su saldo
    public function index()
    {
        $curso = CursoLectivo::actual();

        $saldos = Movimiento::where('curso_lectivo_id', $curso->id)
            ->selectRaw('docente_id, SUM(minutos) as total')
            ->groupBy('docente_id')
            ->pluck('total', 'docente_id');

        $personal = Docente::orderByDesc('activo')->orderBy('nombre')->get()
            ->map(function (Docente $d) use ($saldos) {
                $d->saldo_minutos = (int) ($saldos[$d->id] ?? 0);
                return $d;
            });

        // Datos para el formulario de "agregar docente"
        $materias = Materia::orderBy('nombre')->get();
        $grupos = Grupo::with('guia')->where('curso_lectivo_id', $curso->id)
            ->orderBy('nivel')->orderBy('seccion')->get();
        $gruposSinGuia = $grupos->filter(fn (Grupo $g) => ! $g->guia || ! $g->guia->activo)->values();
        $franjas = FranjaLeccion::orderBy('numero')->get();
        $dias = self::DIAS;

        return view('personal.index', compact('curso', 'personal', 'materias', 'grupos', 'gruposSinGuia', 'franjas', 'dias'));
    }

    // Agrega una docente nueva. Siempre hay que decir qué es:
    //  - especialista: su materia y su horario semanal (día + hora + grupo)
    //  - guía: el grupo del que es guía
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'        => ['required', 'string', 'max:255', 'unique:docentes,nombre'],
            'tipo'          => ['required', 'in:especialista,guia'],
            'materia_id'    => ['nullable', 'required_if:tipo,especialista', 'exists:materias,id'],
            'grupo_id'      => ['nullable', 'required_if:tipo,guia', 'exists:grupos,id'],
            'horario'       => ['nullable', 'array'],
            'horario.*'     => ['nullable', 'array'],
            'horario.*.*'   => ['nullable', 'integer'],
        ], [
            'tipo.required'               => 'Indique si es especialista o guía.',
            'materia_id.required_if'      => 'Elija la materia que da.',
            'grupo_id.required_if'        => 'Elija el grupo del que es guía.',
        ]);

        $curso = CursoLectivo::actual();
        if ($curso->cerrado) {
            throw ValidationException::withMessages([
                'nombre' => 'El curso lectivo está cerrado: solo se puede consultar.',
            ]);
        }

        $nombre = trim($datos['nombre']);
        $hoy = now()->toDateString();

        if ($datos['tipo'] === 'guia') {
            $grupo = Grupo::with('guia')->where('curso_lectivo_id', $curso->id)->find($datos['grupo_id']);

            if (! $grupo) {
                throw ValidationException::withMessages(['grupo_id' => 'Ese grupo no es de este curso lectivo.']);
            }
            if ($grupo->guia && $grupo->guia->activo) {
                throw ValidationException::withMessages([
                    'grupo_id' => "El grupo {$grupo->nivel}{$grupo->seccion} ya tiene guía activa ({$grupo->guia->nombre}). Primero dé de baja a la guía anterior.",
                ]);
            }

            DB::transaction(function () use ($nombre, $grupo) {
                $docente = Docente::create(['nombre' => $nombre, 'activo' => true]);
                $grupo->update(['guia_id' => $docente->id]);
            });

            return redirect()->route('personal.index')
                ->with('exito', "Guía agregada: {$nombre} es guía de {$grupo->nivel}{$grupo->seccion}.");
        }

        // Especialista: se arma la lista de lecciones [día, franja, grupo] que llenó la directora
        $celdas = [];
        foreach (($datos['horario'] ?? []) as $dia => $franjas) {
            if (! isset(self::DIAS[$dia])) {
                continue;
            }
            foreach ($franjas as $franjaId => $grupoId) {
                if ($grupoId) {
                    $celdas[] = ['dia' => (int) $dia, 'franja' => (int) $franjaId, 'grupo' => (int) $grupoId];
                }
            }
        }

        if ($celdas === []) {
            throw ValidationException::withMessages(['horario' => 'Complete al menos una lección del horario.']);
        }

        $grupos = Grupo::where('curso_lectivo_id', $curso->id)->get()->keyBy('id');
        $franjas = FranjaLeccion::get()->keyBy('id');

        foreach ($celdas as $c) {
            if (! $grupos->has($c['grupo']) || ! $franjas->has($c['franja'])) {
                throw ValidationException::withMessages(['horario' => 'El horario tiene un grupo u hora que no existe en este curso.']);
            }
        }

        // Un grupo no puede tener dos especialistas a la misma hora
        $aCerrar = [];
        foreach ($celdas as $c) {
            $ocupado = Horario::with('docente')
                ->where('grupo_id', $c['grupo'])
                ->where('franja_id', $c['franja'])
                ->where('dia_semana', $c['dia'])
                ->whereDate('vigente_desde', '<=', $hoy)
                ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy))
                ->first();

            if (! $ocupado) {
                continue;
            }

            if ($ocupado->docente->activo) {
                $g = $grupos[$c['grupo']];
                $f = $franjas[$c['franja']];
                throw ValidationException::withMessages([
                    'horario' => "El grupo {$g->nivel}{$g->seccion} ya tiene lección con {$ocupado->docente->nombre} el "
                        .mb_strtolower(self::DIAS[$c['dia']]).' a las '.substr($f->hora_inicio, 0, 5).'.',
                ]);
            }

            // Era de una docente que ya salió: esa lección pasa a la nueva
            $aCerrar[] = $ocupado->id;
        }

        $niveles = collect($celdas)->map(fn ($c) => $grupos[$c['grupo']]->nivel)->unique()->values();
        $materia = Materia::findOrFail($datos['materia_id']);

        DB::transaction(function () use ($nombre, $celdas, $niveles, $materia, $curso, $hoy, $aCerrar) {
            Horario::whereIn('id', $aCerrar)->update(['vigente_hasta' => now()->subDay()->toDateString()]);

            $docente = Docente::create(['nombre' => $nombre, 'activo' => true]);

            foreach ($niveles as $nivel) {
                AsignacionMateria::firstOrCreate([
                    'docente_id'       => $docente->id,
                    'materia_id'       => $materia->id,
                    'curso_lectivo_id' => $curso->id,
                    'nivel'            => $nivel,
                ]);
            }

            foreach ($celdas as $c) {
                Horario::create([
                    'docente_id'    => $docente->id,
                    'grupo_id'      => $c['grupo'],
                    'materia_id'    => $materia->id,
                    'franja_id'     => $c['franja'],
                    'dia_semana'    => $c['dia'],
                    'vigente_desde' => $hoy,
                ]);
            }
        });

        return redirect()->route('personal.index')
            ->with('exito', "Docente agregada: {$nombre}, {$materia->nombre}, con ".count($celdas).' lección(es) por semana.');
    }

    // Da de baja: queda inactiva y su saldo se deja en cero con un ajuste
    public function baja(Docente $docente, SaldoService $saldos)
    {
        $curso = CursoLectivo::actual();

        try {
            $saldos->darDeBaja($docente, $curso->id, now()->toDateString());
        } catch (DomainException $e) {
            return back()->withErrors(['personal' => $e->getMessage()]);
        }

        return redirect()->route('personal.index')->with('exito', 'Docente dada de baja y saldo dejado en cero.');
    }

    // Vuelve a activar a una docente (su saldo no se recupera)
    public function reactivar(Docente $docente)
    {
        $docente->update(['activo' => true]);

        return redirect()->route('personal.index')->with('exito', 'Docente reactivada.');
    }
}
