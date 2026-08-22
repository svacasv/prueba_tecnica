<?php

namespace App\Models;

use App\Models\Concerns\SearchesByName;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'external_id',
    'name',
    'status',
    'species',
    'type',
    'gender',
    'image',
    'origin_location_id',
    'current_location_id',
])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory, SearchesByName;

    /**
     * Localización de la que procede el personaje. Puede ser null.
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    /**
     * Localización en la que se encuentra actualmente. Puede ser null.
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /**
     * Episodios en los que aparece el personaje.
     */
    public function episodes(): BelongsToMany
    {
        return $this->belongsToMany(Episode::class);
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
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['species'] ?? null, fn (Builder $query, string $species) => $query->where('species', $species))
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender));
    }
}
