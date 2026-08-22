<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListEpisodesRequest;
use App\Http\Resources\EpisodeResource;
use App\Models\Episode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EpisodeController extends Controller
{
    public function index(ListEpisodesRequest $request): AnonymousResourceCollection
    {
        $episodes = Episode::query()
            ->filter($request->filters())
            ->withCount('characters')
            ->orderBy('code')
            ->paginate($request->perPage())
            ->withQueryString();

        return EpisodeResource::collection($episodes);
    }

    public function show(Episode $episode): EpisodeResource
    {
        return new EpisodeResource($episode->load('characters'));
    }
}
