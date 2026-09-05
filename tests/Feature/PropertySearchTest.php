<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $params
     */
    private function url(array $params = []): string
    {
        return '/api/properties?'.http_build_query(array_merge([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ], $params));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function offerFor(Property $property, array $overrides = []): Offer
    {
        $supplier = Supplier::firstOrCreate(['name' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->create();

        return Offer::factory()->create(array_merge([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'import_id' => $import->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDays(30),
        ], $overrides));
    }

    public function test_returns_cheapest_offer_per_property(): void
    {
        $property = Property::factory()->create(['code' => 'BCN-0001', 'city' => 'Barcelona']);
        $this->offerFor($property, ['price' => 80000]);
        $this->offerFor($property, ['price' => 72500]);

        $response = $this->getJson($this->url());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('data.0.best_offer.price', 72500);
    }

    public function test_filters_by_city(): void
    {
        $this->offerFor(Property::factory()->create(['city' => 'Barcelona']), ['price' => 10000]);
        $this->offerFor(Property::factory()->create(['city' => 'Madrid']), ['price' => 20000]);

        $response = $this->getJson($this->url(['city' => 'Barcelona']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Barcelona');
    }

    public function test_excludes_expired_offers(): void
    {
        $this->offerFor(Property::factory()->create(), ['expires_at' => now()->subDay()]);

        $this->getJson($this->url())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_excludes_offers_with_no_units(): void
    {
        $this->offerFor(Property::factory()->create(), ['available_units' => 0]);

        $this->getJson($this->url())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_excludes_offers_with_insufficient_guests(): void
    {
        $this->offerFor(Property::factory()->create(), ['max_guests' => 2]);

        $this->getJson($this->url(['guests' => 4]))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pagination_returns_meta(): void
    {
        $this->offerFor(Property::factory()->create());

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonStructure(['data', 'next', 'prev', 'per_page'])
            ->assertJsonPath('per_page', 15);
    }

    public function test_validates_required_params(): void
    {
        $this->getJson('/api/properties')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['check_in', 'check_out', 'guests']);
    }
}
