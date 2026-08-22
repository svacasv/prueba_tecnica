<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'type' => $this->type,
            'dimension' => $this->dimension,
            // Solo aparecen cuando el controlador los ha cargado: en el listado
            // va el contador, en el detalle la lista completa.
            'residents_count' => $this->whenCounted('residents'),
            'residents' => CharacterResource::collection($this->whenLoaded('residents')),
        ];
    }
}
