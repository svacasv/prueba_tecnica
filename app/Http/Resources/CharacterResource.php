<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'status' => $this->status,
            'species' => $this->species,
            'type' => $this->type,
            'gender' => $this->gender,
            'image' => $this->image,
            // null cuando la API no conocía la localización.
            'origin' => new LocationResource($this->whenLoaded('origin')),
            'current_location' => new LocationResource($this->whenLoaded('currentLocation')),
            'episodes_count' => $this->whenCounted('episodes'),
            'episodes' => EpisodeResource::collection($this->whenLoaded('episodes')),
        ];
    }
}
