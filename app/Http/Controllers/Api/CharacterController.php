<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListCharactersRequest;
use App\Http\Resources\CharacterResource;
use App\Models\Character;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterController extends Controller
{
    public function index(ListCharactersRequest $request): AnonymousResourceCollection
    {
        $characters = Character::query()
            ->filter($request->filters())
            ->with(['origin', 'currentLocation'])
            ->withCount('episodes')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString(); // los enlaces de paginación conservan los filtros

        return CharacterResource::collection($characters);
    }

    public function show(Character $character): CharacterResource
    {
        return new CharacterResource($character->load(['origin', 'currentLocation', 'episodes']));
    }
}
