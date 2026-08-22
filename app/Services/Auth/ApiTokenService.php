<?php

namespace App\Services\Auth;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Autenticación propia por tokens, sin paquetes externos.
 *
 * Funciona como una API key: al registrarse o iniciar sesión se emite una
 * cadena aleatoria que el cliente envía en cada petición en la cabecera
 * "Authorization: Bearer <token>". En base de datos solo se guarda su hash.
 */
final class ApiTokenService
{
    public function __construct(
        private readonly int $lifetimeDays,
    ) {}

    /**
     * Emite un token nuevo para el usuario. El valor en claro se devuelve una
     * única vez; no se puede recuperar después.
     */
    public function issue(User $user): IssuedToken
    {
        $plainText = Str::random(64);
        $expiresAt = $this->lifetimeDays > 0 ? now()->addDays($this->lifetimeDays) : null;

        $user->tokens()->create([
            'token_hash' => $this->hash($plainText),
            'expires_at' => $expiresAt,
        ]);

        return new IssuedToken($plainText, $expiresAt);
    }

    /**
     * Usuario al que pertenece el token, o null si el token no existe, está
     * caducado o viene vacío. Actualiza la fecha de último uso.
     */
    public function userFromToken(?string $plainText): ?User
    {
        if ($plainText === null || $plainText === '') {
            return null;
        }

        $token = ApiToken::query()
            ->where('token_hash', $this->hash($plainText))
            ->with('user')
            ->first();

        if ($token === null || $token->isExpired()) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token->user;
    }

    /**
     * Invalida un token (cierre de sesión). Si no existe, no pasa nada.
     */
    public function revoke(?string $plainText): void
    {
        if ($plainText === null || $plainText === '') {
            return;
        }

        ApiToken::query()->where('token_hash', $this->hash($plainText))->delete();
    }

    private function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }
}
