<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MetricasService
{
    public function totalUsuarios(string $periodo = 'mensual'): array
    {
        $pgFmt = match ($periodo) {
            'diario'  => 'YYYY-MM-DD',
            'semanal' => 'IYYY"-W"IW',
            default   => 'YYYY-MM',
        };

        // ->toArray() convierte la Collection a array plano — serializable por el caché file
        $registrados = User::where('tipo', 'registrado')
            ->whereNull('deleted_at')
            ->selectRaw("TO_CHAR(created_at, '{$pgFmt}') as periodo, COUNT(*) as total")
            ->groupByRaw("TO_CHAR(created_at, '{$pgFmt}')")
            ->orderByRaw("TO_CHAR(created_at, '{$pgFmt}') DESC")
            ->limit(12)
            ->pluck('total', 'periodo')
            ->toArray();

        $invitados = User::where('tipo', 'invitado')
            ->whereNull('deleted_at')
            ->selectRaw("TO_CHAR(created_at, '{$pgFmt}') as periodo, COUNT(*) as total")
            ->groupByRaw("TO_CHAR(created_at, '{$pgFmt}')")
            ->orderByRaw("TO_CHAR(created_at, '{$pgFmt}') DESC")
            ->limit(12)
            ->pluck('total', 'periodo')
            ->toArray();

        return [
            'total_registrados' => User::where('tipo', 'registrado')->whereNull('deleted_at')->count(),
            'total_invitados'   => User::where('tipo', 'invitado')->whereNull('deleted_at')->count(),
            'por_periodo'       => [
                'registrados' => $registrados,
                'invitados'   => $invitados,
            ],
        ];
    }

    public function demograficos(): array
    {
        $baseQuery = Empresa::whereNull('deleted_at');

        return [
            'por_sector' => (clone $baseQuery)
                ->selectRaw('sector, COUNT(*) as total')
                ->groupBy('sector')
                ->orderByDesc('total')
                ->pluck('total', 'sector')
                ->toArray(),

            'por_tamano' => (clone $baseQuery)
                ->selectRaw('tamano, COUNT(*) as total')
                ->groupBy('tamano')
                ->orderByDesc('total')
                ->pluck('total', 'tamano')
                ->toArray(),

            'por_genero' => (clone $baseQuery)
                ->selectRaw('genero_empresario, COUNT(*) as total')
                ->groupBy('genero_empresario')
                ->orderByDesc('total')
                ->pluck('total', 'genero_empresario')
                ->toArray(),

            'por_edad' => (clone $baseQuery)
                ->selectRaw('rango_edad, COUNT(*) as total')
                ->groupBy('rango_edad')
                ->orderByDesc('total')
                ->pluck('total', 'rango_edad')
                ->toArray(),

            'por_pais' => (clone $baseQuery)
                ->selectRaw('pais, COUNT(*) as total')
                ->groupBy('pais')
                ->orderByDesc('total')
                ->limit(20)
                ->pluck('total', 'pais')
                ->toArray(),
        ];
    }

    public function palabrasClave(): array
    {
        $titulos = Iniciativa::whereNull('deleted_at')
            ->where('cuadrante', 1)
            ->pluck('titulo')
            ->toArray();

        $stopWords = ['de','la','el','en','y','a','los','las','un','una','es','se','del',
                      'con','para','por','que','su','sus','al','lo','como','más','pero',
                      'si','sus','le','ya','o','mi','porque','qué','esto','entre','cuando',
                      'muy','sin','sobre','ser','tiene','también','fue'];

        $frecuencias = [];
        foreach ($titulos as $titulo) {
            $palabras = preg_split('/\s+/', mb_strtolower(strip_tags($titulo)));
            foreach ($palabras as $palabra) {
                $palabra = preg_replace('/[^a-záéíóúüñ]/u', '', $palabra);
                if (strlen($palabra) >= 4 && ! in_array($palabra, $stopWords)) {
                    $frecuencias[$palabra] = ($frecuencias[$palabra] ?? 0) + 1;
                }
            }
        }

        arsort($frecuencias);

        return array_slice($frecuencias, 0, 50, true);
    }

    public function matrizAgregada(): array
    {
        $promedios = Iniciativa::whereNull('deleted_at')
            ->selectRaw('AVG(importancia) as avg_importancia, AVG(gobernabilidad) as avg_gobernabilidad, COUNT(*) as total')
            ->first();

        $porCuadrante = Iniciativa::whereNull('deleted_at')
            ->whereNotNull('cuadrante')
            ->selectRaw('cuadrante, COUNT(*) as total, AVG(importancia) as avg_importancia, AVG(gobernabilidad) as avg_gobernabilidad')
            ->groupBy('cuadrante')
            ->orderBy('cuadrante')
            ->get()
            ->keyBy('cuadrante');

        $porSectorCuadrante = Empresa::whereNull('empresas.deleted_at')
            ->join('iniciativas', 'empresas.id', '=', 'iniciativas.empresa_id')
            ->whereNull('iniciativas.deleted_at')
            ->whereNotNull('iniciativas.cuadrante')
            ->selectRaw('empresas.sector, iniciativas.cuadrante, COUNT(*) as total')
            ->groupBy('empresas.sector', 'iniciativas.cuadrante')
            ->orderBy('empresas.sector')
            ->orderBy('iniciativas.cuadrante')
            ->get();

        return [
            'promedio_global' => [
                'importancia'    => round((float) $promedios->avg_importancia, 2),
                'gobernabilidad' => round((float) $promedios->avg_gobernabilidad, 2),
                'total'          => (int) $promedios->total,
            ],
            'distribucion_por_cuadrante' => $porCuadrante->map(fn ($row) => [
                'total'              => (int) $row->total,
                'avg_importancia'    => round((float) $row->avg_importancia, 2),
                'avg_gobernabilidad' => round((float) $row->avg_gobernabilidad, 2),
            ])->toArray(),
            'distribucion_sector_cuadrante' => $porSectorCuadrante->toArray(),
        ];
    }

    public function exportarCsv(): array
    {
        $iniciativas = Iniciativa::whereNull('iniciativas.deleted_at')
            ->join('empresas', 'iniciativas.empresa_id', '=', 'empresas.id')
            ->whereNull('empresas.deleted_at')
            ->select([
                'iniciativas.id',
                'iniciativas.categoria',
                'iniciativas.importancia',
                'iniciativas.gobernabilidad',
                'iniciativas.cuadrante',
                'empresas.sector',
                'empresas.tamano',
                'empresas.genero_empresario',
                'empresas.rango_edad',
                'empresas.pais',
                'empresas.ciudad',
                DB::raw("TO_CHAR(iniciativas.created_at, 'YYYY-MM-DD') as fecha"),
            ])
            ->orderByDesc('iniciativas.created_at')
            ->get();

        $headers = ['id','categoria','importancia','gobernabilidad','cuadrante',
                    'sector','tamano','genero_empresario','rango_edad','pais','ciudad','fecha'];

        return ['headers' => $headers, 'rows' => $iniciativas->toArray()];
    }
}
