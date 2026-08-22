<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Base de las peticiones de listado: cada una declara sus filtros y aquí se
 * añaden las reglas de paginación comunes a todas.
 */
abstract class ListRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * Reglas de los filtros propios de cada listado.
     *
     * @return array<string, array<int, string>>
     */
    abstract protected function filterRules(): array;

    public function rules(): array
    {
        return $this->filterRules() + [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', self::DEFAULT_PER_PAGE);
    }

    /**
     * Solo los filtros que han llegado en la petición, ya validados.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return Arr::except($this->validated(), ['page', 'per_page']);
    }
}
