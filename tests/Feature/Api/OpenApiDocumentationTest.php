<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        // symfony/yaml viene con laravel/sail (dependencia de desarrollo).
        return Yaml::parseFile(base_path('docs/openapi.yaml'));
    }

    public function test_la_especificacion_es_un_yaml_valido_con_la_version_de_openapi(): void
    {
        $spec = $this->spec();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function test_documenta_exactamente_las_rutas_que_existen(): void
    {
        $documented = [];

        foreach ($this->spec()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                // {id} en la documentación equivale a {character}, {episode}... en las rutas.
                $documented[] = strtoupper($method).' api'.preg_replace('/\{[^}]+}/', '{param}', $path);
            }
        }

        $registered = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || $route->uri() === 'api/docs') {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $registered[] = $method.' '.preg_replace('/\{[^}]+}/', '{param}', $route->uri());
                }
            }
        }

        sort($documented);
        sort($registered);

        $this->assertSame($registered, $documented);
    }

    public function test_se_sirve_la_especificacion_y_la_pagina_de_documentacion(): void
    {
        $this->get('/api/docs')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/yaml');

        $this->get('/')
            ->assertOk()
            ->assertSee('swagger-ui')
            ->assertSee('/api/docs');
    }
}
