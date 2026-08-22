<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ApiTokenService;
use App\Services\Auth\IssuedToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $tokens,
    ) {}

    /**
     * Crea un usuario y devuelve su primer token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // La contraseña se guarda cifrada gracias al cast "hashed" del modelo User.
        $user = User::create($request->validated());

        return $this->respondWithToken($user, $this->tokens->issue($user), Response::HTTP_CREATED);
    }

    /**
     * Comprueba las credenciales y devuelve un token nuevo.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Mismo mensaje si el email no existe o la contraseña falla: así no se
        // puede usar el login para averiguar qué emails están registrados.
        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid credentials.');
        }

        return $this->respondWithToken($user, $this->tokens->issue($user));
    }

    /**
     * Usuario autenticado.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Invalida el token con el que se ha hecho la petición.
     */
    public function logout(Request $request): Response
    {
        $this->tokens->revoke($request->bearerToken());

        return response()->noContent();
    }

    private function respondWithToken(User $user, IssuedToken $token, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $token->plainText,
            'token_type' => 'Bearer',
            'expires_at' => $token->expiresAt?->toIso8601String(),
            'user' => new UserResource($user),
        ], $status);
    }
}
