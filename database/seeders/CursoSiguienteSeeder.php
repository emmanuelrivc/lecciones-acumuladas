<?php

namespace Database\Seeders;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PLANTILLA para cargar un curso lectivo nuevo (grupos + horarios).
 *
 * Cuándo se usa: cuando la directora ya aprobó el horario que armó la secretaria.
 * Cómo se usa: 1) llenar los 4 bloques de abajo, 2) hacer un respaldo,
 *              3) php artisan db:seed --class=CursoSiguienteSeeder
 *
 * No está dentro de DatabaseSeeder a propósito: solo corre cuando uno lo pide.
 * Se puede correr más de una vez sin duplicar nada (firstOrCreate).
 */
class CursoSiguienteSeeder extends Seeder
{
    // 1) Datos del curso
    protected function anio(): int
    {
        return 2027;
    }

    protected function vencimiento(): string
    {
        return '2027-12-12';
    }

    // Primer día en que rige el horario nuevo
    protected function inicio(): string
    {
        return '2027-02-07';
    }

    // 2) Grupos y su docente guía: [nivel, sección, 'Nombre exacto de la docente']
    protected function guias(): array
    {
        return [
            // [1, 'A', 'Nombre Apellido Apellido'],
        ];
    }

    // 3) Horarios: [docente, materia, [día => [lección => grupo]]]
    //    Días: 1 = lunes ... 5 = viernes. Grupo: nivel + sección ('2A').
    protected function horarios(): array
    {
        return [
            // ['Nombre Apellido Apellido', 'Música', [
            //     1 => [1 => '6A', 2 => '6A'], // Lunes, lecciones 1 y 2
            // ]],
        ];
    }

    public function run(): void
    {
        if ($this->guias() === [] || $this->horarios() === []) {
            throw new DomainException('Primero llene los grupos y los horarios dentro de CursoSiguienteSeeder.');
        }

        DB::transaction(function () {
            $curso = CursoLectivo::firstOrCreate(
                ['anio' => $this->anio()],
                ['fecha_vencimiento' => $this->vencimiento(), 'cerrado' => false]
            );

            // 4) Grupos
            foreach ($this->guias() as [$nivel, $seccion, $nombreGuia]) {
                $guia = Docente::where('nombre', $nombreGuia)->first()
                    ?? throw new DomainException("No existe la docente guía: {$nombreGuia}");

                Grupo::firstOrCreate(
                    ['curso_lectivo_id' => $curso->id, 'nivel' => $nivel, 'seccion' => $seccion],
                    ['guia_id' => $guia->id]
                );
            }

            $grupos = Grupo::where('curso_lectivo_id', $curso->id)->get()
                ->keyBy(fn ($g) => $g->nivel.$g->seccion);
            $franjas = FranjaLeccion::pluck('id', 'numero');

            // Horarios viejos que seguían abiertos se cierran el día anterior al inicio nuevo
            $gruposViejos = Grupo::where('curso_lectivo_id', '!=', $curso->id)->pluck('id');
            Horario::whereIn('grupo_id', $gruposViejos)
                ->whereNull('vigente_hasta')
                ->update(['vigente_hasta' => Carbon::parse($this->inicio())->subDay()->toDateString()]);

            // Horarios nuevos
            foreach ($this->horarios() as [$nombreDocente, $nombreMateria, $dias]) {
                $docente = Docente::where('nombre', $nombreDocente)->first()
                    ?? throw new DomainException("No existe la docente: {$nombreDocente}");
                $materia = Materia::where('nombre', $nombreMateria)->first()
                    ?? throw new DomainException("No existe la materia: {$nombreMateria}");

                foreach ($dias as $dia => $lecciones) {
                    foreach ($lecciones as $numero => $codigo) {
                        $grupo = $grupos[$codigo] ?? throw new DomainException("No existe el grupo {$codigo}");

                        Horario::firstOrCreate(
                            [
                                'docente_id' => $docente->id,
                                'grupo_id' => $grupo->id,
                                'materia_id' => $materia->id,
                                'franja_id' => $franjas[$numero],
                                'dia_semana' => $dia,
                            ],
                            ['vigente_desde' => $this->inicio()]
                        );
                    }
                }
            }
        });
    }
}
