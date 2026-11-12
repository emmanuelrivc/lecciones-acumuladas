<?php

namespace App\Services;

use App\Models\Ausencia;
use App\Models\AusenciaLeccion;
use App\Models\CursoLectivo;
use App\Models\DescuentoGeneral;
use App\Models\Docente;
use App\Models\Movimiento;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Aquí viven las REGLAS DE NEGOCIO de los saldos de lecciones acumuladas.
 *
 * Idea central: el saldo no se guarda como un número. Se calcula SUMANDO los
 * movimientos de cada docente (como un libro de cuentas). Nada se borra: para
 * deshacer algo se crea un movimiento contrario que apunta al original.
 */
class SaldoService
{
    // Los tipos de movimiento que existen
    public const ACUMULACION = 'acumulacion'; // suma: cubrió una lección
    public const USO = 'uso';                 // resta: usó minutos acumulados
    public const DESCUENTO = 'descuento';     // resta: descuento general de la directora
    public const AJUSTE = 'ajuste';           // suma o resta: corrección manual
    public const REVERSION = 'reversion';     // deshace otro movimiento

    // Una lección cubierta suma sus minutos a quien la cubrió
    public function acumular(AusenciaLeccion $leccion): Movimiento
    {
        $cursoId = $leccion->ausencia->curso_lectivo_id;
        $this->asegurarCursoAbierto($cursoId);

        if ($leccion->anulada) {
            throw new DomainException('La lección está anulada y no puede acumular minutos.');
        }

        $yaAcumulada = Movimiento::where('ausencia_leccion_id', $leccion->id)
            ->where('tipo', self::ACUMULACION)
            ->exists();

        if ($yaAcumulada) {
            throw new DomainException('Esta lección ya acumuló sus minutos.');
        }

        return Movimiento::create([
            'docente_id' => $leccion->cubre_docente_id,
            'curso_lectivo_id' => $cursoId,
            'ausencia_leccion_id' => $leccion->id,
            'tipo' => self::ACUMULACION,
            'minutos' => $leccion->minutos,
            'fecha' => $leccion->fecha,
        ]);
    }

    // Usar minutos acumulados (resta). Nunca se puede gastar más de lo que hay.
    public function usar(Docente $docente, int $cursoLectivoId, int $minutos, string $fecha, ?string $nota = null): Movimiento
    {
        $this->asegurarCursoAbierto($cursoLectivoId);

        if ($minutos <= 0) {
            throw new DomainException('Los minutos a usar deben ser mayores que cero.');
        }

        return DB::transaction(function () use ($docente, $cursoLectivoId, $minutos, $fecha, $nota) {
            $saldo = $docente->saldoEn($cursoLectivoId);

            if ($minutos > $saldo) {
                throw new DomainException("Saldo insuficiente: tiene {$saldo} minutos y se piden {$minutos}.");
            }

            return Movimiento::create([
                'docente_id' => $docente->id,
                'curso_lectivo_id' => $cursoLectivoId,
                'tipo' => self::USO,
                'minutos' => -$minutos,
                'fecha' => $fecha,
                'nota' => $nota,
            ]);
        });
    }

    // Corrección manual: suma (positivo) o resta (negativo). Exige motivo y no deja el saldo bajo cero.
    public function ajustar(Docente $docente, int $cursoLectivoId, int $minutos, string $fecha, string $motivo): Movimiento
    {
        $this->asegurarCursoAbierto($cursoLectivoId);

        if ($minutos === 0) {
            throw new DomainException('El ajuste no puede ser de cero minutos.');
        }

        if (trim($motivo) === '') {
            throw new DomainException('El motivo del ajuste es obligatorio.');
        }

        return DB::transaction(function () use ($docente, $cursoLectivoId, $minutos, $fecha, $motivo) {
            if ($docente->saldoEn($cursoLectivoId) + $minutos < 0) {
                throw new DomainException('El ajuste dejaría el saldo en negativo.');
            }

            return Movimiento::create([
                'docente_id' => $docente->id,
                'curso_lectivo_id' => $cursoLectivoId,
                'tipo' => self::AJUSTE,
                'minutos' => $minutos,
                'fecha' => $fecha,
                'nota' => $motivo,
            ]);
        });
    }

    // Deshacer un movimiento: no se borra, se crea uno contrario que apunta al original
    public function revertir(Movimiento $original, ?string $nota = null): Movimiento
    {
        $this->asegurarCursoAbierto($original->curso_lectivo_id);

        if ($original->tipo === self::REVERSION) {
            throw new DomainException('Una reversión no se puede revertir.');
        }

        if (Movimiento::where('revierte_id', $original->id)->exists()) {
            throw new DomainException('Este movimiento ya fue revertido.');
        }

        return DB::transaction(function () use ($original, $nota) {
            $saldoResultante = $original->docente->saldoEn($original->curso_lectivo_id) - $original->minutos;

            if ($saldoResultante < 0) {
                throw new DomainException('No se puede revertir: el saldo quedaría en negativo.');
            }

            return Movimiento::create([
                'docente_id' => $original->docente_id,
                'curso_lectivo_id' => $original->curso_lectivo_id,
                'revierte_id' => $original->id,
                'tipo' => self::REVERSION,
                'minutos' => -$original->minutos,
                'fecha' => now()->toDateString(),
                'nota' => $nota,
            ]);
        });
    }

    // Anular una lección: marca la lección como anulada y quita los minutos que había sumado
    public function anularLeccion(AusenciaLeccion $leccion, ?string $nota = null): void
    {
        DB::transaction(function () use ($leccion, $nota) {
            if ($leccion->anulada) {
                throw new DomainException('La lección ya está anulada.');
            }

            $acumulacion = Movimiento::where('ausencia_leccion_id', $leccion->id)
                ->where('tipo', self::ACUMULACION)
                ->first();

            if ($acumulacion && ! Movimiento::where('revierte_id', $acumulacion->id)->exists()) {
                $this->revertir($acumulacion, $nota ?? 'Lección anulada');
            }

            $leccion->update(['anulada' => true]);
        });
    }

    // Anular una ausencia completa: anula todas sus lecciones (revierte los minutos que sumaron).
    // Si alguna lección ya gastó sus minutos, falla y NO se anula nada (todo o nada).
    public function anularAusencia(Ausencia $ausencia, ?string $nota = null): void
    {
        $this->asegurarCursoAbierto($ausencia->curso_lectivo_id);

        if ($ausencia->estado === 'anulada') {
            throw new DomainException('La ausencia ya está anulada.');
        }

        DB::transaction(function () use ($ausencia, $nota) {
            foreach ($ausencia->lecciones()->where('anulada', false)->get() as $leccion) {
                $this->anularLeccion($leccion, $nota ?? 'Ausencia anulada');
            }

            $ausencia->update(['estado' => 'anulada']);
        });
    }

    // Descuento general (solo la directora): a cada docente activa se le descuenta
    // el MENOR entre el descuento y su saldo, así nadie queda en negativo
    public function aplicarDescuentoGeneral(int $cursoLectivoId, int $minutos, string $fecha, string $motivo, ?array $docenteIds = null): DescuentoGeneral
    {
        $this->asegurarCursoAbierto($cursoLectivoId);

        if ($minutos <= 0) {
            throw new DomainException('Los minutos del descuento deben ser mayores que cero.');
        }

        return DB::transaction(function () use ($cursoLectivoId, $minutos, $fecha, $motivo, $docenteIds) {
            $descuento = DescuentoGeneral::create([
                'curso_lectivo_id' => $cursoLectivoId,
                'fecha' => $fecha,
                'minutos' => $minutos,
                'motivo' => $motivo,
                'revertido' => false,
            ]);

            // Si se indican docentes, solo a ellas; si no, a todo el personal activo
            $destinatarias = Docente::where('activo', true)
                ->when($docenteIds !== null, fn ($q) => $q->whereIn('id', $docenteIds))
                ->get();

            foreach ($destinatarias as $docente) {
                $aplicar = min($minutos, $docente->saldoEn($cursoLectivoId));

                if ($aplicar > 0) {
                    Movimiento::create([
                        'docente_id' => $docente->id,
                        'curso_lectivo_id' => $cursoLectivoId,
                        'descuento_general_id' => $descuento->id,
                        'tipo' => self::DESCUENTO,
                        'minutos' => -$aplicar,
                        'fecha' => $fecha,
                        'nota' => $motivo,
                    ]);
                }
            }

            return $descuento;
        });
    }

    // Deshacer un descuento general completo: devuelve a cada docente lo que se le descontó
    public function revertirDescuentoGeneral(DescuentoGeneral $descuento): void
    {
        $this->asegurarCursoAbierto($descuento->curso_lectivo_id);

        if ($descuento->revertido) {
            throw new DomainException('Este descuento general ya fue revertido.');
        }

        DB::transaction(function () use ($descuento) {
            $movimientos = Movimiento::where('descuento_general_id', $descuento->id)
                ->where('tipo', self::DESCUENTO)
                ->get();

            foreach ($movimientos as $movimiento) {
                if (! Movimiento::where('revierte_id', $movimiento->id)->exists()) {
                    $this->revertir($movimiento, 'Descuento general revertido');
                }
            }

            $descuento->update(['revertido' => true]);
        });
    }

    // Salida del personal: queda inactiva y su saldo se deja en cero con un ajuste (no se borra nada)
    public function darDeBaja(Docente $docente, int $cursoLectivoId, string $fecha): void
    {
        $this->asegurarCursoAbierto($cursoLectivoId);

        DB::transaction(function () use ($docente, $cursoLectivoId, $fecha) {
            $saldo = $docente->saldoEn($cursoLectivoId);

            if ($saldo > 0) {
                $this->ajustar($docente, $cursoLectivoId, -$saldo, $fecha, 'Salida del personal');
            }

            $docente->update(['activo' => false]);
        });
    }

    // Un curso cerrado es de solo lectura: ningún movimiento nuevo
    private function asegurarCursoAbierto(int $cursoLectivoId): void
    {
        $curso = CursoLectivo::findOrFail($cursoLectivoId);

        if ($curso->cerrado) {
            throw new DomainException('El curso lectivo está cerrado: solo se puede consultar.');
        }
    }
}
