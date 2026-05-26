<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\StoreEmpresaRequest;
use App\Http\Requests\Empresa\UpdateEmpresaRequest;
use App\Http\Resources\EmpresaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmpresaController extends Controller
{
    private const TTL = 300; // 5 minutos

    private function cacheKeyLista(int $userId): string   { return "empresas:{$userId}"; }
    private function cacheKeyDetalle(int $userId, int $id): string { return "empresa:{$userId}:{$id}"; }

    private function invalidarCacheUsuario(int $userId, ?int $empresaId = null): void
    {
        Cache::forget($this->cacheKeyLista($userId));
        if ($empresaId) {
            Cache::forget($this->cacheKeyDetalle($userId, $empresaId));
        }
    }

    public function index(Request $request): JsonResponse
    {
        $userId   = $request->user()->id;
        $cacheKey = $this->cacheKeyLista($userId);

        // Cacheamos el array plano (->resolve()), no el modelo Eloquent — evita errores de deserialización
        $empresasData = Cache::remember($cacheKey, self::TTL, function () use ($request) {
            $empresas = $request->user()
                ->empresas()
                ->withCount('iniciativas')
                ->orderByDesc('created_at')
                ->get();
            return EmpresaResource::collection($empresas)->resolve();
        });

        return response()->json([
            'success' => true,
            'data'    => ['empresas' => $empresasData],
            'message' => 'Empresas obtenidas correctamente.',
        ]);
    }

    public function store(StoreEmpresaRequest $request): JsonResponse
    {
        $empresa = $request->user()->empresas()->create($request->validated());

        $this->invalidarCacheUsuario($request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => ['empresa' => new EmpresaResource($empresa)],
            'message' => 'Empresa creada correctamente.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId   = $request->user()->id;
        $cacheKey = $this->cacheKeyDetalle($userId, $id);

        $empresaData = Cache::remember($cacheKey, self::TTL, function () use ($request, $id) {
            $empresa = $request->user()->empresas()->withCount('iniciativas')->findOrFail($id);
            return (new EmpresaResource($empresa))->resolve();
        });

        return response()->json([
            'success' => true,
            'data'    => ['empresa' => $empresaData],
            'message' => 'Empresa obtenida correctamente.',
        ]);
    }

    public function update(UpdateEmpresaRequest $request, int $id): JsonResponse
    {
        $empresa = $request->user()->empresas()->findOrFail($id);
        $empresa->update($request->validated());

        $this->invalidarCacheUsuario($request->user()->id, $id);

        return response()->json([
            'success' => true,
            'data'    => ['empresa' => new EmpresaResource($empresa->fresh())],
            'message' => 'Empresa actualizada correctamente.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $empresa = $request->user()->empresas()->findOrFail($id);
        $empresa->delete();

        $this->invalidarCacheUsuario($request->user()->id, $id);

        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'Empresa eliminada correctamente.',
        ]);
    }
}
