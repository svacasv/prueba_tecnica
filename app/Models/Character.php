<?php

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    use HasFactory;

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
}
