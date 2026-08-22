<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpisodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'code' => $this->code,
            'air_date' => $this->air_date?->toDateString(),
            'characters_count' => $this->whenCounted('characters'),
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
        ];
    }
}
