<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $supplier = Supplier::factory();

        return [
            'supplier_id' => $supplier,
            'property_id' => Property::factory(),
            'import_id' => Import::factory()->for($supplier),
            'external_id' => 'offer-'.$this->faker->unique()->numerify('#####'),
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => $this->faker->numberBetween(2, 6),
            'price' => $this->faker->numberBetween(50000, 150000),
            'currency' => 'EUR',
            'available_units' => $this->faker->numberBetween(1, 5),
            'expires_at' => now()->addDays(10),
        ];
    }
}
