<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda parcial por nombre, compartida por personajes, episodios y localizaciones.
 */
trait SearchesByName
{
    public function scopeNameContains(Builder $query, string $text): Builder
    {
        // En un LIKE, "%" y "_" son comodines. Se escapan para que buscar "100%"
        // encuentre ese texto literal y no cualquier cosa.
        $escaped = addcslashes($text, '%_\\');

        return $query->where('name', 'like', "%{$escaped}%");
    }
}
