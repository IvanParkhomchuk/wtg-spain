<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function offer(array $overrides = []): array
    {
        return array_merge([
            'external_id' => 'offer-a-10001',
            'property' => [
                'code' => 'BCN-0001',
                'name' => 'Apartment near Sagrada Familia',
                'city' => 'Barcelona',
            ],
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => '2026-09-10T23:59:59Z',
        ], $overrides);
    }

    public function test_processes_offers_correctly(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->create(['total_offers' => 1]);

        (new ProcessImportJob($import, [$this->offer()]))->handle();

        $import->refresh();
        $this->assertSame(Import::STATUS_COMPLETED, $import->status);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);

        $this->assertDatabaseHas('properties', ['code' => 'BCN-0001']);
        $this->assertDatabaseHas('offers', [
            'external_id' => 'offer-a-10001',
            'supplier_id' => $supplier->id,
            'price' => 72500,
        ]);
    }

    public function test_updates_existing_offer_from_new_import(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'supplier-a']);

        $first = Import::factory()->for($supplier)->create(['total_offers' => 1]);
        (new ProcessImportJob($first, [$this->offer()]))->handle();

        $second = Import::factory()->for($supplier)->create(['total_offers' => 1]);
        (new ProcessImportJob($second, [
            $this->offer(['price' => 69900, 'available_units' => 5]),
        ]))->handle();

        $this->assertSame(1, Offer::count());

        $offer = Offer::where('external_id', 'offer-a-10001')->firstOrFail();
        $this->assertSame(69900, $offer->price);
        $this->assertSame(5, $offer->available_units);
        $this->assertSame($second->id, $offer->import_id);
    }

    public function test_marks_failed_on_error(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->create(['total_offers' => 1]);

        // price is a NOT NULL column, so a null value makes the offer insert
        // throw a QueryException — exercising the job's failure branch.
        (new ProcessImportJob($import, [$this->offer(['price' => null])]))->handle();

        $import->refresh();
        $this->assertSame(Import::STATUS_FAILED, $import->status);
        $this->assertNotNull($import->error);
        $this->assertSame(0, $import->processed_offers);
        $this->assertSame(0, Offer::count());
    }
}
