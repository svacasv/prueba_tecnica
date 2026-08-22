<?php

namespace App\Models;

use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['external_id', 'name', 'air_date', 'code'])]
class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

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
}
