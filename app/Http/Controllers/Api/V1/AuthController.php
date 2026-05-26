<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InvitadoRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MigrarInvitadoRequest;
use App\Http\Requests\Auth\RegistroRequest;
use App\Http\Resources\UserResource;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registro(RegistroRequest $request): JsonResponse
    {
        $user = User::create([
            'tipo'                 => 'registrado',
            'nombre'               => $request->nombre,
            'email'                => $request->email,
            'password'             => Hash::make($request->password),
            'consentimiento'       => true,
            'fecha_consentimiento' => now(),
            'version_politica'     => '1.0',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => new UserResource($user),
            ],
            'message' => 'Cuenta creada exitosamente.',
        ], 201);
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

    public function migrarInvitado(MigrarInvitadoRequest $request): JsonResponse
    {
        $invitado = User::where('token_invitado', $request->token_invitado)
            ->where('tipo', 'invitado')
            ->firstOrFail();

        $registrado = User::create([
            'tipo'                 => 'registrado',
            'nombre'               => $request->nombre,
            'email'                => $request->email,
            'password'             => Hash::make($request->password),
            'consentimiento'       => true,
            'fecha_consentimiento' => now(),
            'version_politica'     => '1.0',
        ]);

        // Transferir todas las empresas del invitado al nuevo usuario registrado
        Empresa::where('user_id', $invitado->id)->update(['user_id' => $registrado->id]);

        // Revocar tokens del invitado y eliminarlo
        $invitado->tokens()->delete();
        $invitado->delete();

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
