<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['external_id', 'name', 'type', 'dimension'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

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
}
