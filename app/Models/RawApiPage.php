<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Página de la API externa guardada sin procesar (ver migración raw_api_pages).
 */
#[Fillable(['entity', 'page', 'payload', 'fetched_at'])]
class RawApiPage extends Model
{
    // La tabla solo guarda la fecha de descarga; no necesita created_at/updated_at.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
