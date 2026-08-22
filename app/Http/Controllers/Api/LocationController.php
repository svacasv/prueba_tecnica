<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListLocationsRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function index(ListLocationsRequest $request): AnonymousResourceCollection
    {
        $locations = Location::query()
            ->filter($request->filters())
            ->withCount('residents')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return LocationResource::collection($locations);
    }

    public function show(Location $location): LocationResource
    {
        return new LocationResource($location->load('residents'));
    }
}
