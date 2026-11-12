<?php

namespace App\Services;

use App\Models\Ausencia;
use App\Models\AusenciaLeccion;
use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Horario;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Responde: "¿quién está libre para cubrir esta lección?"
 *
 * Una docente se considera LIBRE en una franja si:
 *  - está activa y no es la que faltó,
 *  - no tiene ausencia registrada ese día,
 *  - no da una lección de especialidad en esa franja (según el horario),
 *  - no está ya cubriendo otra lección en esa misma franja ese día,
 *  - y, si es guía, queda libre solo si sobra una guía de su nivel:
 *    en 1° a 3° cada grupo tiene su guía; en 4° a 6° las dos guías del nivel
 *    dan clase en las dos secciones, así que por cada sección SIN especialista
 *    se ocupa una guía. Si sobra una guía pero no se sabe cuál (el horario de las
 *    guías no está cargado), queda como "quizá disponible".
 */
class DisponibilidadService
{
    /**
     * @param  Collection<int, Horario>  $lecciones  las lecciones que dejó la docente ausente
     * @param  Collection<int, Docente>  $docentes   candidatas (docentes activas)
     * @return array<int, array{libres: array<int,int>, quiza: array<int,int>}>  por horario_id
     */
    public function libres(Carbon $fecha, Docente $ausente, Collection $lecciones, Collection $docentes): array
    {
        $dia = $fecha->dayOfWeekIso;
        $dia_fecha = $fecha->toDateString();
        $franjaIds = $lecciones->pluck('franja_id')->unique()->values();

        // Horarios vigentes ese día en esas franjas
        $horarios = Horario::where('dia_semana', $dia)
            ->whereIn('franja_id', $franjaIds)
            ->whereDate('vigente_desde', '<=', $dia_fecha)
            ->where(function ($q) use ($dia_fecha) {
                $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia_fecha);
            })
            ->get();

        // Docentes dando clase por franja, y grupos que están con una especialista por franja
        $dandoClase = $horarios->groupBy('franja_id')->map(fn ($g) => $g->pluck('docente_id')->flip());
        $gruposConEspecial = $horarios->groupBy('franja_id')->map(fn ($g) => $g->pluck('grupo_id')->flip());

        // Grupos del curso actual: de ahí salen las guías de cada nivel
        $grupos = Grupo::where('curso_lectivo_id', CursoLectivo::actual()->id)->get();
        $gruposPorNivel = $grupos->groupBy('nivel');
        $guiasPorNivel = $gruposPorNivel->map(fn ($g) => $g->pluck('guia_id')->unique());
        $nivelDeGuia = $grupos->groupBy('guia_id')->map(fn ($g) => $g->first()->nivel);

        // Docentes que ya están cubriendo otra lección en esa franja ese día
        $cubriendo = AusenciaLeccion::whereDate('fecha', $dia_fecha)
            ->where('anulada', false)
            ->whereIn('franja_id', $franjaIds)
            ->whereHas('ausencia', fn ($q) => $q->where('estado', 'activa'))
            ->get()
            ->groupBy('franja_id')
            ->map(fn ($g) => $g->pluck('cubre_docente_id')->flip());

        // Docentes con ausencia registrada ese día: null = faltó todo el día;
        // si no, la lista de franjas en las que faltó
        $ausentes = Ausencia::whereDate('fecha', $dia_fecha)
            ->where('estado', 'activa')
            ->get()
            ->groupBy('docente_id')
            ->map(function ($lista) {
                if ($lista->contains(fn ($a) => $a->franjas_faltadas === null)) {
                    return null;
                }

                return $lista->flatMap(fn ($a) => $a->franjas_faltadas)->all();
            });

        $estaAusente = fn (int $id, int $franja): bool => $ausentes->has($id)
            && ($ausentes->get($id) === null || in_array($franja, $ausentes->get($id), true));

        $resultado = [];

        foreach ($lecciones as $leccion) {
            $franja = $leccion->franja_id;
            $libres = [];
            $quiza = [];

            foreach ($docentes as $d) {
                if ($d->id === $ausente->id || $estaAusente($d->id, $franja)) {
                    continue;
                }
                if (isset(($dandoClase[$franja] ?? collect())[$d->id])) {
                    continue; // da una lección de especialidad en esa franja
                }
                if (isset(($cubriendo[$franja] ?? collect())[$d->id])) {
                    continue; // ya cubre otra lección a esa hora
                }

                // Si es guía, depende de cuántas guías sobran en su nivel
                if (isset($nivelDeGuia[$d->id])) {
                    $nivel = $nivelDeGuia[$d->id];
                    $conEspecial = $gruposConEspecial[$franja] ?? collect();

                    $secciones = $gruposPorNivel[$nivel]->count();
                    $conEspecialista = $gruposPorNivel[$nivel]->filter(fn ($g) => isset($conEspecial[$g->id]))->count();
                    $seccionesQueNecesitanGuia = $secciones - $conEspecialista;

                    // Guías del nivel que sí están presentes ese día
                    $guiasPresentes = $guiasPorNivel[$nivel]
                        ->filter(fn ($id) => $id !== $ausente->id && ! $estaAusente($id, $franja))
                        ->count();

                    $sobran = $guiasPresentes - $seccionesQueNecesitanGuia;

                    if ($sobran <= 0) {
                        continue; // todas las guías están dando clase
                    }
                    if ($sobran < $guiasPresentes) {
                        $quiza[] = $d->id; // sobra alguna, pero no sabemos cuál
                        continue;
                    }
                }

                $libres[] = $d->id;
            }

            $resultado[$leccion->id] = ['libres' => $libres, 'quiza' => $quiza];
        }

        return $resultado;
    }
}
