<?php

namespace App\Http\Requests\Favorites;

use App\Http\Requests\Catalog\ListRequest;

/**
 * El listado de favoritos solo se pagina; no tiene filtros propios.
 */
class ListFavoritesRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [];
    }
}
