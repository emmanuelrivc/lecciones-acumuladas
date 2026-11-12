<?php

namespace Tests\Feature;

use App\Models\CursoLectivo;
use App\Models\Docente;
use App\Models\FranjaLeccion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use Database\Seeders\CursoSiguienteSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoSiguienteSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seederDePrueba(array $horarios): CursoSiguienteSeeder
    {
        return new class($horarios) extends CursoSiguienteSeeder {
            public function __construct(private array $h) {}
            protected function anio(): int { return 2027; }
            protected function vencimiento(): string { return '2027-12-12'; }
            protected function inicio(): string { return '2027-02-07'; }
            protected function guias(): array { return [[1, 'A', 'Guía Nueva']]; }
            protected function horarios(): array { return $this->h; }
        };
    }

    private function preparar(): void
    {
        $viejo = CursoLectivo::create(['anio' => 2026, 'fecha_vencimiento' => '2026-12-12', 'cerrado' => false]);
        $guia = Docente::create(['nombre' => 'Guía Nueva', 'activo' => true]);
        $esp = Docente::create(['nombre' => 'Especialista', 'activo' => true]);
        $materia = Materia::create(['nombre' => 'Música', 'es_especial' => true]);
        $franja = FranjaLeccion::create(['numero' => 1, 'hora_inicio' => '07:00', 'hora_fin' => '07:40', 'es_receso' => false]);
        $grupoViejo = Grupo::create(['curso_lectivo_id' => $viejo->id, 'nivel' => 1, 'seccion' => 'A', 'guia_id' => $guia->id]);

        Horario::create([
            'docente_id' => $esp->id, 'grupo_id' => $grupoViejo->id, 'materia_id' => $materia->id,
            'franja_id' => $franja->id, 'dia_semana' => 1, 'vigente_desde' => '2026-01-01',
        ]);
    }

    public function test_carga_el_curso_los_grupos_y_los_horarios_y_cierra_los_viejos(): void
    {
        $this->preparar();

        $this->seederDePrueba([['Especialista', 'Música', [1 => [1 => '1A']]]])->run();

        $nuevo = CursoLectivo::where('anio', 2027)->first();
        $this->assertNotNull($nuevo);
        $this->assertSame(1, Grupo::where('curso_lectivo_id', $nuevo->id)->count());
        $this->assertSame(1, Horario::whereDate('vigente_desde', '2027-02-07')->count());
        $this->assertSame(
            '2027-02-06',
            Horario::whereDate('vigente_desde', '2026-01-01')->first()->vigente_hasta->format('Y-m-d')
        );
    }

    public function test_correrlo_dos_veces_no_duplica(): void
    {
        $this->preparar();
        $seeder = $this->seederDePrueba([['Especialista', 'Música', [1 => [1 => '1A']]]]);

        $seeder->run();
        $seeder->run();

        $this->assertSame(1, Horario::whereDate('vigente_desde', '2027-02-07')->count());
        $this->assertSame(1, Grupo::where('nivel', 1)->whereHas('cursoLectivo', fn ($q) => $q->where('anio', 2027))->count());
    }

    public function test_un_nombre_mal_escrito_no_deja_nada_a_medias(): void
    {
        $this->preparar();

        try {
            $this->seederDePrueba([['Nombre Inexistente', 'Música', [1 => [1 => '1A']]]])->run();
            $this->fail('Debió fallar');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Nombre Inexistente', $e->getMessage());
        }

        $this->assertNull(CursoLectivo::where('anio', 2027)->first());
    }

    public function test_la_plantilla_vacia_avisa_en_vez_de_cargar_nada(): void
    {
        $this->expectException(DomainException::class);

        (new CursoSiguienteSeeder())->run();
    }
}
