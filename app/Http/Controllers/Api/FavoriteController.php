<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Favorites\ListFavoritesRequest;
use App\Http\Requests\Favorites\StoreFavoriteRequest;
use App\Http\Resources\CharacterResource;
use App\Models\Character;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Favoritos del usuario autenticado. Todas las consultas parten de
 * $request->user(), así que es imposible ver o tocar los de otro usuario.
 */
class FavoriteController extends Controller
{
    public function index(ListFavoritesRequest $request): AnonymousResourceCollection
    {
        $favorites = $request->user()->favoriteCharacters()
            ->with(['origin', 'currentLocation'])
            ->withCount('episodes')
            ->orderByPivot('created_at', 'desc') // los más recientes primero
            ->paginate($request->perPage())
            ->withQueryString();

        return CharacterResource::collection($favorites);
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $character = Character::query()->findOrFail($request->validated('character_id'));

        try {
            $request->user()->favoriteCharacters()->attach($character);
        } catch (UniqueConstraintViolationException) {
            // El índice único de la tabla ya lo ha rechazado: estaba en favoritos.
            abort(Response::HTTP_CONFLICT, 'The character is already in your favorites.');
        }

        return (new CharacterResource($character->load(['origin', 'currentLocation'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Character $character): Response
    {
        $removed = $request->user()->favoriteCharacters()->detach($character);

        if ($removed === 0) {
            abort(Response::HTTP_NOT_FOUND, 'The character is not in your favorites.');
        }

        return response()->noContent();
    }
}
