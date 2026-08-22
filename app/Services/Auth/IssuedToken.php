<?php

namespace App\Services\Auth;

use Carbon\CarbonInterface;

/**
 * Resultado de emitir un token: el valor en claro (solo se conoce en este
 * momento, después únicamente existe su hash) y cuándo caduca.
 */
final readonly class IssuedToken
{
    public function __construct(
        public string $plainText,
        public ?CarbonInterface $expiresAt,
    ) {}
}
