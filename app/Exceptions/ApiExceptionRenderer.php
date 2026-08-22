<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Convierte cualquier excepción de la API en una respuesta JSON con un formato
 * único: {"message": "..."} y, solo en errores de validación, "errors".
 *
 * Nunca se devuelve la traza ni el nombre de una clase interna, esté o no
 * activado APP_DEBUG: eso va al log. Las rutas web siguen el comportamiento
 * normal de Laravel.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null; // null = que Laravel lo gestione como siempre
        }

        if ($exception instanceof ValidationException) {
            return $this->respond(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
            );
        }

        if ($exception instanceof AuthenticationException) {
            return $this->respond(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        // Un id que no existe llega aquí como NotFoundHttpException envolviendo a
        // ModelNotFoundException, con un mensaje del tipo "No query results for
        // model [App\Models\Character] 5". Se sustituye por uno genérico para no
        // exponer nombres de clases. Los 404 con mensaje propio (abort) se respetan.
        if ($exception instanceof NotFoundHttpException && $exception->getPrevious() instanceof ModelNotFoundException) {
            return $this->respond(Response::HTTP_NOT_FOUND, $this->defaultMessage(Response::HTTP_NOT_FOUND));
        }

        // abort(409, '...'), 405 de método no permitido, 429 de throttle, etc.
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return $this->respond(
                $status,
                $exception->getMessage() !== '' ? $exception->getMessage() : $this->defaultMessage($status),
                headers: $exception->getHeaders(), // p. ej. Retry-After en un 429
            );
        }

        // Cualquier otra cosa es un fallo del servidor. Laravel ya lo ha registrado en el log.
        return $this->respond(Response::HTTP_INTERNAL_SERVER_ERROR, $this->defaultMessage(Response::HTTP_INTERNAL_SERVER_ERROR));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message] + $extra, $status, $headers);
    }

    /**
     * Texto estándar del código HTTP: "Not Found.", "Method Not Allowed."…
     */
    private function defaultMessage(int $status): string
    {
        return (Response::$statusTexts[$status] ?? 'Error').'.';
    }
}
