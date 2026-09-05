<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_full_import_status(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->create([
            'external_import_id' => 'import-2026-09-01-001',
            'status' => Import::STATUS_COMPLETED,
            'total_offers' => 20,
            'processed_offers' => 20,
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'supplier',
                    'external_import_id',
                    'sent_at',
                    'status',
                    'total_offers',
                    'processed_offers',
                    'error',
                    'created_at',
                    'completed_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $import->id,
                    'supplier' => 'supplier-a',
                    'external_import_id' => 'import-2026-09-01-001',
                    'status' => Import::STATUS_COMPLETED,
                    'total_offers' => 20,
                    'processed_offers' => 20,
                    'error' => null,
                ],
            ]);
    }

    public function test_returns_404_for_missing_import(): void
    {
        $this->getJson('/api/imports/999')->assertNotFound();
    }
}
