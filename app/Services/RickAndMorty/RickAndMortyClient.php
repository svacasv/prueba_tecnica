<?php

namespace App\Services\RickAndMorty;

use App\Services\RickAndMorty\DTO\PageData;
use App\Services\RickAndMorty\Exceptions\RickAndMortyApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP de la API pública de Rick and Morty.
 *
 * Es la única clase del proyecto que habla con el servicio externo. Devuelve
 * DTOs propios (nunca el JSON tal cual) y convierte cualquier problema de red
 * o de formato en una excepción controlada.
 */
final class RickAndMortyClient
{
    public function __construct(
        private readonly ResponseParser $parser,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
        private readonly int $maxAttempts,
        private readonly int $retryDelayMs,
    ) {}

    public function characters(int $page): PageData
    {
        return $this->parser->charactersPage($this->fetchPage('character', $page));
    }

    public function episodes(int $page): PageData
    {
        return $this->parser->episodesPage($this->fetchPage('episode', $page));
    }

    public function locations(int $page): PageData
    {
        return $this->parser->locationsPage($this->fetchPage('location', $page));
    }

    /**
     * Descarga una página del recurso indicado y devuelve el JSON decodificado.
     *
     * @return array<string, mixed>
     *
     * @throws RickAndMortyApiException
     */
    private function fetchPage(string $resource, int $page): array
    {
        $url = "{$this->baseUrl}/{$resource}";

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry(
                    times: $this->maxAttempts,
                    sleepMilliseconds: fn (int $attempt) => $attempt * $this->retryDelayMs,
                    when: fn (Throwable $exception) => $this->isWorthRetrying($exception),
                    throw: false,
                )
                ->get($url, ['page' => $page]);
        } catch (ConnectionException $exception) {
            throw RickAndMortyApiException::connectionFailed($url, $page, $exception);
        }

        if (! $response->successful()) {
            throw RickAndMortyApiException::unexpectedStatus($url, $page, $response->status());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw RickAndMortyApiException::invalidJson($url, $page);
        }

        return $json;
    }

    /**
     * Merece la pena reintentar ante errores de red, límite de peticiones (429)
     * o errores del servidor (5xx). Un 404 (página inexistente) no va a cambiar
     * por mucho que se insista.
     */
    private function isWorthRetrying(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }
}
