<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iniciativa\StoreIniciativaRequest;
use App\Http\Requests\Iniciativa\UpdateIniciativaRequest;
use App\Http\Resources\IniciativaResource;
use App\Models\Iniciativa;
use App\Services\IgoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IniciativaController extends Controller
{
    public function __construct(private readonly IgoService $igoService) {}

    public function index(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $request->user()->empresas()->findOrFail($empresaId);

        $iniciativas = $empresa->iniciativas()
            ->with('planAccion')
            ->orderBy('cuadrante')
            ->orderByDesc('importancia')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ['iniciativas' => IniciativaResource::collection($iniciativas)],
            'message' => 'Iniciativas obtenidas correctamente.',
        ]);
    }

    public function store(StoreIniciativaRequest $request, int $empresaId): JsonResponse
    {
        $empresa    = $request->user()->empresas()->findOrFail($empresaId);
        $iniciativa = $empresa->iniciativas()->create($request->validated());

        // Recalcular cuadrantes de toda la empresa porque cambiaron las asíntotas
        $this->igoService->recalcularTodosLosCuadrantes($empresa->id);

        return response()->json([
            'success' => true,
            'data'    => ['iniciativa' => new IniciativaResource($iniciativa->fresh())],
            'message' => 'Iniciativa creada correctamente.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $iniciativa = $this->encontrarIniciativaDelUsuario($request, $id);

        return response()->json([
            'success' => true,
            'data'    => ['iniciativa' => new IniciativaResource($iniciativa->load('planAccion'))],
            'message' => 'Iniciativa obtenida correctamente.',
        ]);
    }

    public function update(UpdateIniciativaRequest $request, int $id): JsonResponse
    {
        $iniciativa = $this->encontrarIniciativaDelUsuario($request, $id);
        $iniciativa->update($request->validated());

        $this->igoService->recalcularTodosLosCuadrantes($iniciativa->empresa_id);

        return response()->json([
            'success' => true,
            'data'    => ['iniciativa' => new IniciativaResource($iniciativa->fresh()->load('planAccion'))],
            'message' => 'Iniciativa actualizada correctamente.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $iniciativa  = $this->encontrarIniciativaDelUsuario($request, $id);
        $empresaId   = $iniciativa->empresa_id;

        $iniciativa->delete();

        $this->igoService->recalcularTodosLosCuadrantes($empresaId);

        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'Iniciativa eliminada correctamente.',
        ]);
    }

    public function matriz(Request $request, int $empresaId): JsonResponse
    {
        $empresa     = $request->user()->empresas()->findOrFail($empresaId);
        $asintotas   = $this->igoService->calcularAsintotas($empresa->id);
        $iniciativas = $empresa->iniciativas()
            ->with('planAccion')
            ->orderBy('cuadrante')
            ->orderByDesc('importancia')
            ->get();

        $resumen = [
            'cuadrante_1' => $iniciativas->where('cuadrante', 1)->count(),
            'cuadrante_2' => $iniciativas->where('cuadrante', 2)->count(),
            'cuadrante_3' => $iniciativas->where('cuadrante', 3)->count(),
            'cuadrante_4' => $iniciativas->where('cuadrante', 4)->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'empresa'     => ['id' => $empresa->id, 'nombre' => $empresa->nombre, 'sector' => $empresa->sector],
                'asintotas'   => $asintotas,
                'resumen'     => $resumen,
                'iniciativas' => IniciativaResource::collection($iniciativas),
            ],
            'message' => 'Matriz IGO obtenida correctamente.',
        ]);
    }

    // Encuentra una iniciativa verificando que pertenezca al usuario autenticado
    private function encontrarIniciativaDelUsuario(Request $request, int $iniciativaId): Iniciativa
    {
        $iniciativa = Iniciativa::findOrFail($iniciativaId);
        $request->user()->empresas()->findOrFail($iniciativa->empresa_id);

        return $iniciativa;
    }
}
