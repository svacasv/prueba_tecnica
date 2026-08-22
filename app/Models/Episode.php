<?php

namespace App\Models;

use App\Models\Concerns\SearchesByName;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['external_id', 'name', 'air_date', 'code'])]
class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory, SearchesByName;

    protected function casts(): array
    {
        return [
            'air_date' => 'date',
        ];
    }

    /**
     * Personajes que aparecen en el episodio.
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class);
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
            ->when($filters['code'] ?? null, fn (Builder $query, string $code) => $query->where('code', strtoupper($code)))
            // La temporada va dentro del código: S03E05 → temporada 3.
            ->when($filters['season'] ?? null, fn (Builder $query, int $season) => $query->where('code', 'like', sprintf('S%02dE%%', $season)));
    }
}
