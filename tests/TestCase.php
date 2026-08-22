<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test puede hacer peticiones HTTP reales. Si alguno lo intenta
        // sin haber definido un Http::fake(), falla con una excepción clara.
        Http::preventStrayRequests();
    }

    /**
     * Carga una respuesta real de la API guardada en tests/Fixtures/rickandmorty.
     *
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        $path = base_path("tests/Fixtures/rickandmorty/{$name}.json");

        return json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
