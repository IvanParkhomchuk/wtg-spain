<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertySearchRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Offer;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    private const PER_PAGE = 15;

    public function index(PropertySearchRequest $request): AnonymousResourceCollection
    {
        $checkIn = $request->validated('check_in');
        $checkOut = $request->validated('check_out');
        $guests = (int) $request->validated('guests');
        $city = $request->validated('city');
        $now = now();

        $offers = Offer::query()
            ->whereDate('check_in', $checkIn)
            ->whereDate('check_out', $checkOut)
            ->where('max_guests', '>=', $guests)
            ->where('available_units', '>', 0)
            ->where('expires_at', '>', $now)
            ->when($city, fn ($query) => $query->whereHas(
                'property',
                fn ($property) => $property->where('city', $city),
            ))
            // Keep only the cheapest matching offer per property: there must be
            // no other matching offer for the same property with a lower price
            // (ties broken by the smaller offer id).
            ->whereNotExists(function (Builder $sub) use ($checkIn, $checkOut, $guests, $now): void {
                $sub->from('offers as cheaper')
                    ->whereColumn('cheaper.property_id', 'offers.property_id')
                    ->whereDate('cheaper.check_in', $checkIn)
                    ->whereDate('cheaper.check_out', $checkOut)
                    ->where('cheaper.max_guests', '>=', $guests)
                    ->where('cheaper.available_units', '>', 0)
                    ->where('cheaper.expires_at', '>', $now)
                    ->where(function (Builder $tie): void {
                        $tie->whereColumn('cheaper.price', '<', 'offers.price')
                            ->orWhere(function (Builder $equal): void {
                                $equal->whereColumn('cheaper.price', '=', 'offers.price')
                                    ->whereColumn('cheaper.id', '<', 'offers.id');
                            });
                    });
            })
            ->with(['property', 'supplier'])
            ->orderBy('price')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return PropertyResource::collection($offers->getCollection())
            ->additional([
                'next' => $offers->nextPageUrl(),
                'prev' => $offers->previousPageUrl(),
                'per_page' => $offers->perPage(),
            ]);
    }
}
