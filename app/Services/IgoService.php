<?php

namespace App\Services;

use App\Models\Iniciativa;
use Illuminate\Support\Facades\DB;

class IgoService
{
    /**
     * Calcula las asíntotas usando SQL AVG — 1 query en vez de cargar todos los registros.
     */
    public function calcularAsintotas(int $empresaId): array
    {
        $resultado = Iniciativa::where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->selectRaw('ROUND(AVG(importancia)::numeric, 2) as imp, ROUND(AVG(gobernabilidad)::numeric, 2) as gob, COUNT(*) as total')
            ->first();

        if (! $resultado || (int) $resultado->total === 0) {
            return ['importancia' => 3, 'gobernabilidad' => 3];
        }

        return [
            'importancia'    => (float) $resultado->imp,
            'gobernabilidad' => (float) $resultado->gob,
        ];
    }

    public function calcularCuadrante(
        float $importancia,
        float $gobernabilidad,
        float $asintotaImp,
        float $asintotaGob
    ): int {
        $altaImp = $importancia >= $asintotaImp;
        $altaGob = $gobernabilidad >= $asintotaGob;

        return match (true) {
            $altaImp && $altaGob   => 1,
            $altaImp && !$altaGob  => 2,
            !$altaImp && $altaGob  => 3,
            default                => 4,
        };
    }

    /**
     * Recalcula cuadrantes con un único UPDATE bulk — 1 round-trip en vez de N.
     * Antes: 1 SELECT + N UPDATEs individuales (≈580ms × N).
     * Ahora: 1 SELECT (solo id/imp/gob) + 1 UPDATE CASE WHEN (≈580ms total).
     */
    public function recalcularTodosLosCuadrantes(int $empresaId): void
    {
        $asintotas = $this->calcularAsintotas($empresaId);

        $iniciativas = Iniciativa::where('empresa_id', $empresaId)
            ->select(['id', 'importancia', 'gobernabilidad'])
            ->get();

        if ($iniciativas->isEmpty()) {
            return;
        }

        $cases = '';
        $ids   = [];
        foreach ($iniciativas as $ini) {
            $cuadrante = $this->calcularCuadrante(
                $ini->importancia,
                $ini->gobernabilidad,
                $asintotas['importancia'],
                $asintotas['gobernabilidad']
            );
            $cases .= "WHEN {$ini->id} THEN {$cuadrante} ";
            $ids[]  = (int) $ini->id;
        }

        DB::statement(
            'UPDATE iniciativas SET cuadrante = CASE id ' . $cases . 'END, updated_at = NOW() '
            . 'WHERE id IN (' . implode(',', $ids) . ')'
        );
    }
}
