<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
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
                ],
            ],
        ], $overrides);
    }

    public function test_creates_import_and_dispatches_job(): void
    {
        Queue::fake();
        Supplier::factory()->create(['name' => 'supplier-a']);

        $response = $this->postJson('/api/imports', $this->payload());

        $response->assertAccepted()
            ->assertJsonStructure(['data' => ['id', 'status']])
            ->assertJson(['data' => ['status' => Import::STATUS_PENDING]]);

        $this->assertDatabaseHas('imports', [
            'external_import_id' => 'import-2026-09-01-001',
            'status' => Import::STATUS_PENDING,
            'total_offers' => 1,
        ]);

        Queue::assertPushed(
            ProcessImportJob::class,
            fn (ProcessImportJob $job): bool => $job->import->external_import_id === 'import-2026-09-01-001'
                && count($job->offers) === 1,
        );
    }

    public function test_is_idempotent(): void
    {
        Queue::fake();
        Supplier::factory()->create(['name' => 'supplier-a']);

        $this->postJson('/api/imports', $this->payload())->assertAccepted();
        $this->postJson('/api/imports', $this->payload())->assertAccepted();

        $this->assertSame(1, Import::count());
        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_validates_unknown_supplier(): void
    {
        $response = $this->postJson('/api/imports', $this->payload(['supplier' => 'supplier-x']));

        $response->assertStatus(422)->assertJsonValidationErrors('supplier');
    }

    public function test_validates_request_structure(): void
    {
        Supplier::factory()->create(['name' => 'supplier-a']);

        $response = $this->postJson('/api/imports', $this->payload(['offers' => []]));

        $response->assertStatus(422)->assertJsonValidationErrors('offers');
    }
}
