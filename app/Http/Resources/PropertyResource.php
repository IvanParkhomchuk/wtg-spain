<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders a property together with its cheapest matching offer.
 *
 * @mixin Offer
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->property->code,
            'name' => $this->property->name,
            'city' => $this->property->city,
            'best_offer' => [
                'id' => $this->id,
                'supplier' => $this->supplier->name,
                'price' => $this->price,
                'currency' => $this->currency,
                'available_units' => $this->available_units,
                'expires_at' => $this->expires_at,
            ],
        ];
    }
}
