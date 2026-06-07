<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InvitadoRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MigrarInvitadoRequest;
use App\Http\Requests\Auth\RegistroRequest;
use App\Http\Requests\Auth\UpgradeCuentaRequest;
use App\Http\Resources\UserResource;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function registro(RegistroRequest $request): JsonResponse
    {
        // Si la petición identifica una cuenta exploratoria activa (por
        // token_invitado en el body, o por el Bearer del invitado que adjunta
        // el interceptor), NO se crea una cuenta nueva huérfana: se crea la
        // registrada, se reasignan TODAS las empresas del invitado al nuevo
        // user_id y se elimina la cuenta exploratoria. Así nunca quedan
        // empresas huérfanas, sin importar qué endpoint use el frontend.
        $invitado = $this->invitadoDesdeRequest($request);

        $user = DB::transaction(function () use ($request, $invitado) {
            $registrado = User::create([
                'tipo'                 => 'registrado',
                'nombre'               => $request->nombre,
                'email'                => $request->email,
                'password'             => Hash::make($request->password),
                'consentimiento'       => true,
                'fecha_consentimiento' => now(),
                'version_politica'     => '1.0',
            ]);

            if ($invitado) {
                // Tomar las empresas de la cuenta exploratoria y reapuntar su
                // user_id al de la nueva cuenta registrada. Las iniciativas y
                // planes siguen a la empresa por su FK (no hay que tocarlas).
                Empresa::where('user_id', $invitado->id)
                    ->update(['user_id' => $registrado->id]);

                // Revocar tokens y eliminar la cuenta exploratoria del registro.
                $invitado->tokens()->delete();
                $invitado->delete();
            }

            return $registrado;
        });

        if ($invitado) {
            Cache::forget('me:' . $invitado->id);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => new UserResource($user),
            ],
            'message' => $invitado
                ? 'Cuenta creada. Tus empresas de la sesión exploratoria fueron transferidas.'
                : 'Cuenta creada exitosamente.',
        ], 201);
    }

    /**
     * Resuelve la cuenta invitado asociada a la petición de registro, por dos
     * vías (la primera que funcione):
     *   1) token_invitado en el body  — el frontend lo tiene de forma persistente
     *      y ahora también lo puede releer desde /auth/me tras recargar.
     *   2) Bearer token del invitado  — lo adjunta el interceptor de axios.
     * Devuelve null si no hay ninguna sesión invitada identificable.
     */
    private function invitadoDesdeRequest(Request $request): ?User
    {
        // 1) token_invitado explícito en el body (vía más fiable)
        $tokenInvitado = $request->input('token_invitado');
        if (is_string($tokenInvitado) && $tokenInvitado !== '') {
            $owner = User::where('token_invitado', $tokenInvitado)
                ->where('tipo', 'invitado')
                ->first();
            if ($owner) {
                return $owner;
            }
        }

        // 2) Bearer token del invitado
        $bearer = $request->bearerToken();
        if ($bearer) {
            $accessToken = PersonalAccessToken::findToken($bearer);
            $owner       = $accessToken?->tokenable;
            if ($owner instanceof User && $owner->tipo === 'invitado') {
                return $owner;
            }
        }

        return null;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->where('tipo', 'registrado')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Revocar tokens anteriores del mismo dispositivo (opcional: limitamos a 5)
        if ($user->tokens()->count() >= 5) {
            $user->tokens()->oldest()->first()?->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => new UserResource($user),
            ],
            'message' => 'Sesión iniciada correctamente.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data'    => [],
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $userData = Cache::remember('me:' . $request->user()->id, 300,
            fn () => (new UserResource($request->user()))->resolve()
        );

        return response()->json([
            'success' => true,
            'data'    => ['user' => $userData],
            'message' => 'Usuario autenticado.',
        ]);
    }

    public function invitado(InvitadoRequest $request): JsonResponse
    {
        $invitado = User::create([
            'tipo'                 => 'invitado',
            'token_invitado'       => Str::uuid()->toString(),
            'consentimiento'       => true,
            'fecha_consentimiento' => now(),
            'version_politica'     => '1.0',
        ]);

        // Emitimos un Sanctum token para que el invitado use los mismos endpoints autenticados
        $token = $invitado->createToken('guest_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token'          => $token,
                'token_invitado' => $invitado->token_invitado,
            ],
            'message' => 'Sesión de invitado creada. Guarda el token_invitado para poder migrar tu cuenta.',
        ], 201);
    }

    /**
     * Convierte la cuenta invitado AUTENTICADA en una cuenta registrada
     * SIN crear un usuario nuevo: actualiza la misma fila. Como las empresas
     * e iniciativas ya pertenecen a este user_id, no hay nada que transferir
     * y es imposible huérfanar datos.
     *
     * Esta es la vía recomendada para "enlazar un perfil a la cuenta
     * exploratoria": el frontend llama con el Bearer token del invitado.
     */
    public function upgrade(UpgradeCuentaRequest $request): JsonResponse
    {
        $user      = $request->user();
        $tipoAntes = $user->tipo;

        if ($user->tipo === 'registrado') {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta ya está registrada.',
                'errors'  => [],
            ], 409);
        }

        // UPDATE directo a nivel de BD (query builder): garantiza que la
        // escritura se ejecute sin depender de dirty-checking de Eloquent ni
        // de eventos de modelo. Devuelve el número de filas afectadas.
        $filas = DB::table('users')->where('id', $user->id)->update([
            'tipo'                 => 'registrado',
            'nombre'               => $request->nombre,
            'email'                => $request->email,
            'password'             => Hash::make($request->password),
            'token_invitado'       => null,
            'consentimiento'       => true,
            'fecha_consentimiento' => now(),
            'version_politica'     => '1.0',
            'updated_at'           => now(),
        ]);

        Cache::forget('me:' . $user->id);

        // Releer desde BD para confirmar el estado real persistido.
        $fresh = User::find($user->id);

        // Diagnóstico (tabla leíble): registra qué pasó realmente.
        try {
            DB::table('debug_auth_eventos')->insert([
                'ruta'         => 'upgrade',
                'bearer'       => (bool) $request->bearerToken(),
                'user_id'      => $user->id,
                'tipo_antes'   => $tipoAntes,
                'tipo_despues' => $fresh?->tipo,
                'filas'        => $filas,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // no romper el flujo por el log de diagnóstico
        }

        $token = $fresh->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => new UserResource($fresh),
            ],
            'message' => 'Perfil enlazado. Tu cuenta exploratoria ahora es una cuenta registrada con todos tus datos.',
        ]);
    }

    public function migrarInvitado(MigrarInvitadoRequest $request): JsonResponse
    {
        $invitado = User::where('token_invitado', $request->token_invitado)
            ->where('tipo', 'invitado')
            ->firstOrFail();

        // Todo o nada: si algo falla, no quedan empresas huérfanas ni cuentas a medias.
        $registrado = DB::transaction(function () use ($invitado, $request) {
            $registrado = User::create([
                'tipo'                 => 'registrado',
                'nombre'               => $request->nombre,
                'email'                => $request->email,
                'password'             => Hash::make($request->password),
                'consentimiento'       => true,
                'fecha_consentimiento' => now(),
                'version_politica'     => '1.0',
            ]);

            // Transferir todas las empresas del invitado al nuevo usuario registrado.
            // Las iniciativas y planes siguen a la empresa por su FK, no hace falta tocarlas.
            Empresa::where('user_id', $invitado->id)->update(['user_id' => $registrado->id]);

            // Revocar tokens del invitado y soft-deletear la cuenta invitado.
            $invitado->tokens()->delete();
            $invitado->delete();

            return $registrado;
        });

        // Limpiar caché del invitado
        Cache::forget('me:' . $invitado->id);

        $token = $registrado->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => new UserResource($registrado),
            ],
            'message' => 'Cuenta creada exitosamente. Tus datos han sido transferidos.',
        ], 201);
    }
}
