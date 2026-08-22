<?php

namespace App\Models;

use App\Models\Concerns\SearchesByName;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['external_id', 'name', 'type', 'dimension'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, SearchesByName;

    /**
     * Personajes cuya ubicación actual es esta localización.
     *
     * No se guarda la lista "residents" que envía la API: se deriva de la relación
     * inversa, que es lo que pide el enunciado y evita tener el dato duplicado.
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Character::class, 'current_location_id');
    }

    /**
     * Aplica los filtros del listado. Solo actúan los que vienen informados.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['name'] ?? null, fn (Builder $query, string $name) => $query->nameContains($name))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['dimension'] ?? null, fn (Builder $query, string $dimension) => $query->where('dimension', $dimension));
    }
}
