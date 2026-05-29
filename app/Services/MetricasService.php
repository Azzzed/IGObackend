<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Informe;
use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MetricasService
{
    // ─── Cache ────────────────────────────────────────────────────────────────

    /**
     * Genera una cache key basada en un endpoint y los filtros activos.
     * Incluye un "bust" que se incrementa cada vez que cambian las iniciativas,
     * lo que invalida todos los caches de analytics sin necesidad de Redis tags.
     */
    public function cacheKey(string $endpoint, array $filtros = []): string
    {
        $bust = Cache::get('admin:cache:bust', 0);
        ksort($filtros);
        $hash = empty($filtros) ? 'global' : md5(json_encode($filtros));

        return "admin:b{$bust}:{$endpoint}:{$hash}";
    }

    /**
     * Invalida todos los caches de analytics incrementando el bust counter.
     * Llamar desde IniciativaController tras store/update/destroy.
     */
    public static function invalidarCacheAdmin(): void
    {
        Cache::increment('admin:cache:bust');
    }

    // ─── Filtros ──────────────────────────────────────────────────────────────

    /**
     * Aplica filtros dinámicos a un query que ya tiene las tablas unidas.
     *
     * @param bool   $conEmpresas    La tabla `empresas`    está disponible en el query
     * @param bool   $conIniciativas La tabla `iniciativas` está disponible en el query
     * @param string $periodoTabla   Tabla cuyo `created_at` se usa para el filtro de período
     */
    private function aplicarFiltros(
        Builder $query,
        array $filtros,
        bool $conEmpresas    = true,
        bool $conIniciativas = true,
        string $periodoTabla = 'iniciativas'
    ): void {
        if ($conEmpresas) {
            if (! empty($filtros['sector']))  $query->where('empresas.sector', $filtros['sector']);
            if (! empty($filtros['genero']))  $query->where('empresas.genero_empresario', $filtros['genero']);
            if (! empty($filtros['edad']))    $query->where('empresas.rango_edad', $filtros['edad']);
            if (! empty($filtros['tamano']))  $query->where('empresas.tamano', $filtros['tamano']);
            if (! empty($filtros['ciudad']))  $query->where('empresas.ciudad', $filtros['ciudad']);
            if (! empty($filtros['pais']))    $query->where('empresas.pais', $filtros['pais']);
        }

        if ($conIniciativas) {
            if (! empty($filtros['cuadrante'])) $query->where('iniciativas.cuadrante', (int) $filtros['cuadrante']);
            if (! empty($filtros['categoria'])) $query->where('iniciativas.categoria', $filtros['categoria']);
        }

        if (! empty($filtros['periodo']) && $filtros['periodo'] !== 'all') {
            $days = $this->periodoDias($filtros['periodo']);
            if ($days !== null) {
                $query->where("{$periodoTabla}.created_at", '>=', now()->subDays($days));
            }
        }
    }

    // ─── KPIs ─────────────────────────────────────────────────────────────────

    /**
     * Retorna los 4 KPIs globales con delta vs mes anterior y sparkline semanal.
     * Sin filtros — siempre sobre el universo completo.
     */
    public function kpis(): array
    {
        $now               = now();
        $inicioMesActual   = $now->copy()->startOfMonth();
        $inicioMesAnterior = $now->copy()->subMonth()->startOfMonth();
        $finMesAnterior    = $now->copy()->subMonth()->endOfMonth();

        $entidades = [
            'usuarios'    => User::whereNull('deleted_at'),
            'empresas'    => Empresa::whereNull('deleted_at'),
            'iniciativas' => Iniciativa::whereNull('deleted_at'),
            'informes'    => Informe::query(),
        ];

        $resultado = [];
        foreach ($entidades as $clave => $baseQuery) {
            $total     = (clone $baseQuery)->count();
            $esteMes   = (clone $baseQuery)->where('created_at', '>=', $inicioMesActual)->count();
            $mesPasado = (clone $baseQuery)
                ->whereBetween('created_at', [$inicioMesAnterior, $finMesAnterior])
                ->count();

            $deltaPct = $mesPasado > 0
                ? round((($esteMes - $mesPasado) / $mesPasado) * 100, 1)
                : ($esteMes > 0 ? 100.0 : 0.0);

            // Sparkline: últimas 7 semanas
            $sparkline = [];
            for ($i = 6; $i >= 0; $i--) {
                $ini       = $now->copy()->subWeeks($i)->startOfWeek();
                $fin       = $now->copy()->subWeeks($i)->endOfWeek();
                $sparkline[] = (clone $baseQuery)->whereBetween('created_at', [$ini, $fin])->count();
            }

            $resultado[$clave] = [
                'total'     => $total,
                'delta_pct' => $deltaPct,
                'sparkline' => $sparkline,
            ];
        }

        return $resultado;
    }

    // ─── Usuarios (serie combinada) ───────────────────────────────────────────

    /**
     * Serie temporal de crecimiento: usuarios + empresas + iniciativas por período.
     * Formato de salida: array ordenado apto para gráfico de líneas.
     */
    public function totalUsuarios(string $periodo = 'mensual'): array
    {
        $pgFmt = match ($periodo) {
            'diario'  => 'YYYY-MM-DD',
            'semanal' => 'IYYY"-W"IW',
            default   => 'YYYY-MM',
        };

        $serieUsuarios = User::where('tipo', 'registrado')
            ->whereNull('deleted_at')
            ->selectRaw("TO_CHAR(created_at, '{$pgFmt}') as periodo, COUNT(*) as total")
            ->groupByRaw("TO_CHAR(created_at, '{$pgFmt}')")
            ->orderByRaw("TO_CHAR(created_at, '{$pgFmt}') DESC")
            ->limit(12)
            ->pluck('total', 'periodo')
            ->toArray();

        $serieEmpresas = Empresa::whereNull('deleted_at')
            ->selectRaw("TO_CHAR(created_at, '{$pgFmt}') as periodo, COUNT(*) as total")
            ->groupByRaw("TO_CHAR(created_at, '{$pgFmt}')")
            ->orderByRaw("TO_CHAR(created_at, '{$pgFmt}') DESC")
            ->limit(12)
            ->pluck('total', 'periodo')
            ->toArray();

        $serieIniciativas = Iniciativa::whereNull('deleted_at')
            ->selectRaw("TO_CHAR(created_at, '{$pgFmt}') as periodo, COUNT(*) as total")
            ->groupByRaw("TO_CHAR(created_at, '{$pgFmt}')")
            ->orderByRaw("TO_CHAR(created_at, '{$pgFmt}') DESC")
            ->limit(12)
            ->pluck('total', 'periodo')
            ->toArray();

        // Merge en array de objetos ordenados por período desc
        $todosPeriodos = array_unique(array_merge(
            array_keys($serieUsuarios),
            array_keys($serieEmpresas),
            array_keys($serieIniciativas),
        ));
        rsort($todosPeriodos);
        $todosPeriodos = array_slice($todosPeriodos, 0, 12);

        $serie = [];
        foreach ($todosPeriodos as $p) {
            $serie[] = [
                'fecha'       => $p,
                'usuarios'    => (int) ($serieUsuarios[$p] ?? 0),
                'empresas'    => (int) ($serieEmpresas[$p] ?? 0),
                'iniciativas' => (int) ($serieIniciativas[$p] ?? 0),
            ];
        }

        return [
            'totales' => [
                'registrados' => User::where('tipo', 'registrado')->whereNull('deleted_at')->count(),
                'invitados'   => User::where('tipo', 'invitado')->whereNull('deleted_at')->count(),
            ],
            'serie' => $serie,
        ];
    }

    // ─── Demográficos (con filtros) ───────────────────────────────────────────

    /**
     * Distribuciones demográficas de empresas, segmentadas por los filtros activos.
     * Los filtros de iniciativas se aplican vía whereHas para evitar duplicados.
     */
    public function demograficos(array $filtros = []): array
    {
        $base = Empresa::whereNull('empresas.deleted_at');

        // Filtros directos en empresas (sin prefijo porque la tabla principal es Empresa)
        if (! empty($filtros['sector']))  $base->where('sector', $filtros['sector']);
        if (! empty($filtros['genero']))  $base->where('genero_empresario', $filtros['genero']);
        if (! empty($filtros['edad']))    $base->where('rango_edad', $filtros['edad']);
        if (! empty($filtros['tamano']))  $base->where('tamano', $filtros['tamano']);
        if (! empty($filtros['ciudad']))  $base->where('ciudad', $filtros['ciudad']);
        if (! empty($filtros['pais']))    $base->where('pais', $filtros['pais']);

        // Filtros de iniciativas via whereHas → no duplica filas de empresa
        if (! empty($filtros['cuadrante']) || ! empty($filtros['categoria'])) {
            $base->whereHas('iniciativas', function ($q) use ($filtros): void {
                if (! empty($filtros['cuadrante'])) $q->where('cuadrante', (int) $filtros['cuadrante']);
                if (! empty($filtros['categoria'])) $q->where('categoria', $filtros['categoria']);
            });
        }

        // Filtro de período sobre empresas
        if (! empty($filtros['periodo']) && $filtros['periodo'] !== 'all') {
            $days = $this->periodoDias($filtros['periodo']);
            if ($days !== null) {
                $base->where('empresas.created_at', '>=', now()->subDays($days));
            }
        }

        return [
            'por_sector' => (clone $base)->selectRaw('sector, COUNT(*) as total')->groupBy('sector')->orderByDesc('total')->pluck('total', 'sector')->toArray(),
            'por_tamano' => (clone $base)->selectRaw('tamano, COUNT(*) as total')->groupBy('tamano')->orderByDesc('total')->pluck('total', 'tamano')->toArray(),
            'por_genero' => (clone $base)->selectRaw('genero_empresario, COUNT(*) as total')->groupBy('genero_empresario')->orderByDesc('total')->pluck('total', 'genero_empresario')->toArray(),
            'por_edad'   => (clone $base)->selectRaw('rango_edad, COUNT(*) as total')->groupBy('rango_edad')->orderByDesc('total')->pluck('total', 'rango_edad')->toArray(),
            'por_pais'   => (clone $base)->selectRaw('pais, COUNT(*) as total')->groupBy('pais')->orderByDesc('total')->limit(20)->pluck('total', 'pais')->toArray(),
        ];
    }

    // ─── Palabras clave (con filtros) ─────────────────────────────────────────

    /**
     * Frecuencia de palabras en títulos de iniciativas.
     * El cuadrante ya NO está hardcodeado a 1 — viene del filtro ?cuadrante=.
     * Si no se pasa cuadrante, analiza todos.
     */
    public function palabrasClave(array $filtros = []): array
    {
        $necesitaEmpresas = $this->filtroRequiereEmpresas($filtros);

        $query = Iniciativa::whereNull('iniciativas.deleted_at');

        if ($necesitaEmpresas) {
            $query->join('empresas', 'iniciativas.empresa_id', '=', 'empresas.id')
                  ->whereNull('empresas.deleted_at');
        }

        $this->aplicarFiltros($query, $filtros, $necesitaEmpresas, true, 'iniciativas');

        $titulos = $query->pluck('iniciativas.titulo')->toArray();

        $stopWords = [
            'de','la','el','en','y','a','los','las','un','una','es','se','del',
            'con','para','por','que','su','sus','al','lo','como','más','pero',
            'si','le','ya','o','mi','porque','qué','esto','entre','cuando',
            'muy','sin','sobre','ser','tiene','también','fue',
        ];

        $frecuencias = [];
        foreach ($titulos as $titulo) {
            $palabras = preg_split('/\s+/', mb_strtolower(strip_tags((string) $titulo)));
            foreach ($palabras as $palabra) {
                $palabra = preg_replace('/[^a-záéíóúüñ]/u', '', $palabra);
                if (mb_strlen($palabra) >= 4 && ! in_array($palabra, $stopWords)) {
                    $frecuencias[$palabra] = ($frecuencias[$palabra] ?? 0) + 1;
                }
            }
        }

        arsort($frecuencias);

        return array_slice($frecuencias, 0, 50, true);
    }

    // ─── Matriz IGO agregada (con filtros) ────────────────────────────────────

    /**
     * Promedios globales, distribución por cuadrante y por sector-cuadrante,
     * segmentados por los filtros activos.
     * Las asíntotas del segmento son el avg_importancia/gobernabilidad del promedio_global.
     */
    public function matrizAgregada(array $filtros = []): array
    {
        // Siempre join con empresas para tener sector disponible y para filtros
        $base = Iniciativa::whereNull('iniciativas.deleted_at')
            ->join('empresas', 'iniciativas.empresa_id', '=', 'empresas.id')
            ->whereNull('empresas.deleted_at');

        $this->aplicarFiltros($base, $filtros, true, true, 'iniciativas');

        $promedios = (clone $base)
            ->selectRaw('AVG(iniciativas.importancia) as avg_importancia, AVG(iniciativas.gobernabilidad) as avg_gobernabilidad, COUNT(*) as total')
            ->first();

        $porCuadrante = (clone $base)
            ->whereNotNull('iniciativas.cuadrante')
            ->selectRaw('iniciativas.cuadrante, COUNT(*) as total, AVG(iniciativas.importancia) as avg_importancia, AVG(iniciativas.gobernabilidad) as avg_gobernabilidad')
            ->groupBy('iniciativas.cuadrante')
            ->orderBy('iniciativas.cuadrante')
            ->get()
            ->keyBy('cuadrante');

        $porSectorCuadrante = (clone $base)
            ->whereNotNull('iniciativas.cuadrante')
            ->selectRaw('empresas.sector, iniciativas.cuadrante, COUNT(*) as total')
            ->groupBy('empresas.sector', 'iniciativas.cuadrante')
            ->orderBy('empresas.sector')
            ->orderBy('iniciativas.cuadrante')
            ->get()
            ->toArray();

        return [
            'promedio_global' => [
                'importancia'    => round((float) ($promedios->avg_importancia ?? 0), 2),
                'gobernabilidad' => round((float) ($promedios->avg_gobernabilidad ?? 0), 2),
                'total'          => (int) ($promedios->total ?? 0),
            ],
            'distribucion_por_cuadrante' => $porCuadrante->map(fn ($row) => [
                'total'              => (int) $row->total,
                'avg_importancia'    => round((float) $row->avg_importancia, 2),
                'avg_gobernabilidad' => round((float) $row->avg_gobernabilidad, 2),
            ])->toArray(),
            'distribucion_sector_cuadrante' => $porSectorCuadrante,
        ];
    }

    // ─── Iniciativas paginadas (con filtros) ──────────────────────────────────

    /**
     * Tabla paginada de iniciativas con todos los filtros combinables.
     * empresa_nombre se incluye (no es dato sensible).
     * NUNCA incluye email ni user_id.
     */
    public function iniciativasPaginadas(array $filtros = []): array
    {
        $page    = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filtros['per_page'] ?? 20)));
        $sort    = in_array($filtros['sort'] ?? '', ['fecha','importancia','gobernabilidad','cuadrante','sector'])
            ? $filtros['sort']
            : 'fecha';
        $dir     = (strtolower($filtros['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

        $sortCol = match ($sort) {
            'sector' => 'empresas.sector',
            'fecha'  => 'iniciativas.created_at',
            default  => "iniciativas.{$sort}",
        };

        $query = Iniciativa::whereNull('iniciativas.deleted_at')
            ->join('empresas', 'iniciativas.empresa_id', '=', 'empresas.id')
            ->whereNull('empresas.deleted_at')
            ->select([
                'iniciativas.id',
                DB::raw("TO_CHAR(iniciativas.created_at, 'YYYY-MM-DD') as fecha"),
                'empresas.nombre as empresa_nombre',
                'empresas.ciudad',
                'empresas.sector',
                'iniciativas.categoria',
                'iniciativas.importancia',
                'iniciativas.gobernabilidad',
                'iniciativas.cuadrante',
            ]);

        $this->aplicarFiltros($query, $filtros, true, true, 'iniciativas');

        $total = (clone $query)->count();
        $rows  = $query
            ->orderBy($sortCol, $dir)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'rows' => $rows,
            'meta' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    // ─── Exportar CSV (con filtros) ───────────────────────────────────────────

    public function exportarCsv(array $filtros = []): array
    {
        $iniciativas = $this->queryExportar($filtros)->get();

        $headers = [
            'id','categoria','importancia','gobernabilidad','cuadrante',
            'sector','tamano','genero_empresario','rango_edad','pais','ciudad','fecha',
        ];

        return ['headers' => $headers, 'rows' => $iniciativas->toArray()];
    }

    // ─── Exportar Excel (con filtros) ─────────────────────────────────────────

    public function exportarExcel(array $filtros = []): Spreadsheet
    {
        $iniciativas = $this->queryExportar($filtros)->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Iniciativas IGO');

        // Encabezados
        $encabezados = [
            'ID','Categoría','Importancia','Gobernabilidad','Cuadrante',
            'Sector','Tamaño','Género','Rango Edad','País','Ciudad','Fecha',
        ];
        $sheet->fromArray($encabezados, null, 'A1');

        // Estilo de encabezados
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
        ]);

        // Datos — extraemos atributos escalares explícitamente (no cast arrays)
        $filas = $iniciativas->map(fn ($ini) => [
            $ini->id,
            $ini->categoria,
            $ini->importancia,
            $ini->gobernabilidad,
            $ini->cuadrante,
            $ini->sector,
            $ini->tamano,
            $ini->genero_empresario,
            $ini->rango_edad,
            $ini->pais,
            $ini->ciudad,
            $ini->fecha,
        ])->toArray();

        if (! empty($filas)) {
            $sheet->fromArray($filas, null, 'A2');
        }

        // Autoajustar ancho de columnas
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    // ─── Query base reutilizable para exportaciones ───────────────────────────

    private function queryExportar(array $filtros = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = Iniciativa::whereNull('iniciativas.deleted_at')
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
            ->orderByDesc('iniciativas.created_at');

        // Remove pagination/sort from filtros — exportar no pagina
        $filtrosExport = array_diff_key($filtros, array_flip(['page','per_page','sort','dir']));
        $this->aplicarFiltros($query, $filtrosExport, true, true, 'iniciativas');

        return $query;
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    /** Verdadero si algún filtro de empresa está presente en el array */
    private function filtroRequiereEmpresas(array $filtros): bool
    {
        foreach (['sector','genero','edad','tamano','ciudad','pais'] as $campo) {
            if (! empty($filtros[$campo])) return true;
        }

        return false;
    }

    /** Convierte el alias de período a días */
    private function periodoDias(string $periodo): ?int
    {
        return match ($periodo) {
            'last_30'  => 30,
            'last_90'  => 90,
            'last_180' => 180,
            'last_365' => 365,
            default    => null,
        };
    }
}
