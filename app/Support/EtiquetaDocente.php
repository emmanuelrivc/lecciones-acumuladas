<?php

namespace App\Support;

use App\Models\AsignacionMateria;
use App\Models\Grupo;

// Dice de qué es cada docente (su materia o el grupo que guía) y con qué color mostrarla
class EtiquetaDocente
{
    // Colores por materia: [color del texto, color de fondo]
    private const COLORES = [
        'Música'              => ['#6B21A8', '#F3E8FF'],
        'Artes Industriales'  => ['#9A3412', '#FFEDD5'],
        'Educación Religiosa' => ['#854D0E', '#FEF9C3'],
        'Educación Física'    => ['#166534', '#DCFCE7'],
        'Hogar'               => ['#9D174D', '#FCE7F3'],
        'Artes Plásticas'     => ['#991B1B', '#FEE2E2'],
        'Inglés'              => ['#1E40AF', '#DBEAFE'],
        'Informática'         => ['#155E75', '#CFFAFE'],
    ];

    private const COLOR_GUIA = ['#0B5A54', '#E6F4F1'];

    // Los niveles en texto corto: [2] -> "2°", [4,5,6] -> "4° a 6°", [1,2,4] -> "1° a 2° y 4°"
    private static function niveles(array $niveles): string
    {
        sort($niveles);
        $tramos = [];
        foreach ($niveles as $n) {
            $ultimo = array_key_last($tramos);
            if ($ultimo !== null && $tramos[$ultimo][1] === $n - 1) {
                $tramos[$ultimo][1] = $n;
            } else {
                $tramos[] = [$n, $n];
            }
        }

        return collect($tramos)
            ->map(fn ($t) => $t[0] === $t[1] ? $t[0].'°' : $t[0].'° a '.$t[1].'°')
            ->implode(' y ');
    }

    // Devuelve [docente_id => ['rotulo', 'texto', 'fondo', 'grupo' (para agrupar), 'orden']]
    public static function para(int $cursoLectivoId): array
    {
        $etiquetas = [];

        // Primero las especialistas, por su materia
        $materias = AsignacionMateria::with('materia')
            ->where('curso_lectivo_id', $cursoLectivoId)
            ->get()
            ->groupBy('docente_id');

        foreach ($materias as $docenteId => $filas) {
            $nombres = $filas->pluck('materia.nombre')->filter()->unique()->values();
            if ($nombres->isEmpty()) {
                continue;
            }
            $primera = $nombres->first();
            [$texto, $fondo] = self::COLORES[$primera] ?? ['#374151', '#F3F4F6'];

            // Si no atiende todos los niveles (ej. Inglés de 2°), se anota cuáles
            $niveles = $filas->pluck('nivel')->filter()->unique()->values()->all();
            $detalle = (count($niveles) > 0 && count($niveles) < 6) ? ' ('.self::niveles($niveles).')' : '';

            $posicion = array_search($primera, array_keys(self::COLORES), true);
            $etiquetas[$docenteId] = [
                'rotulo' => $nombres->implode(' y ').$detalle,
                'texto'  => $texto,
                'fondo'  => $fondo,
                'grupo'  => $primera,
                'orden'  => $posicion === false ? 50 : $posicion,
            ];
        }

        // Luego las guías, por su grupo (solo si no son especialistas)
        $grupos = Grupo::where('curso_lectivo_id', $cursoLectivoId)
            ->orderBy('nivel')->orderBy('seccion')->get()
            ->groupBy('guia_id');

        foreach ($grupos as $docenteId => $lista) {
            if (isset($etiquetas[$docenteId])) {
                continue;
            }
            [$texto, $fondo] = self::COLOR_GUIA;
            $nombres = $lista->map(fn ($g) => $g->nivel.$g->seccion)->implode(' y ');
            $primero = $lista->first();
            $etiquetas[$docenteId] = [
                'rotulo' => 'Guía '.$nombres,
                'texto'  => $texto,
                'fondo'  => $fondo,
                'grupo'  => 'Docentes guía',
                'orden'  => 100 + $primero->nivel * 10 + (ord($primero->seccion) - 64),
            ];
        }

        return $etiquetas;
    }

    // Ordena la lista para el desplegable: primero cada materia especial, abajo las guías
    // (por grado) y al final quien no tenga ni materia ni grupo.
    // Devuelve [título => [docentes en orden]]
    public static function agrupar($docentes, array $etiquetas): array
    {
        $bloques = [];

        foreach ($docentes as $d) {
            $e = $etiquetas[$d->id] ?? null;
            $titulo = $e['grupo'] ?? 'Otras docentes';
            $bloques[$titulo]['orden'] = $e['orden'] ?? 999;
            $bloques[$titulo]['docentes'][] = $d;
        }

        // Cada bloque queda en su posición; dentro de las materias, por nombre; las guías, por grado
        uasort($bloques, fn ($a, $b) => $a['orden'] <=> $b['orden']);

        $resultado = [];
        foreach ($bloques as $titulo => $bloque) {
            $lista = collect($bloque['docentes']);
            $lista = $titulo === 'Docentes guía'
                ? $lista->sortBy(fn ($d) => $etiquetas[$d->id]['orden'])
                : $lista->sortBy('nombre');
            $resultado[$titulo] = $lista->values()->all();
        }

        return $resultado;
    }
}
