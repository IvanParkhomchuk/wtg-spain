<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $offers
     */
    public function __construct(
        public Import $import,
        public array $offers,
    ) {}

    public function handle(): void
    {
        $this->import->update(['status' => Import::STATUS_PROCESSING]);

        try {
            foreach ($this->offers as $offerData) {
                $this->processOffer($offerData);
                $this->import->increment('processed_offers');
            }

            $this->import->update([
                'status' => Import::STATUS_COMPLETED,
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->import->update([
                'status' => Import::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $offerData
     */
    protected function processOffer(array $offerData): void
    {
        $property = Property::firstOrCreate(
            ['code' => $offerData['property']['code']],
            [
                'name' => $offerData['property']['name'],
                'city' => $offerData['property']['city'],
            ],
        );

        Offer::updateOrCreate(
            [
                'supplier_id' => $this->import->supplier_id,
                'external_id' => $offerData['external_id'],
            ],
            [
                'property_id' => $property->id,
                'import_id' => $this->import->id,
                'check_in' => $offerData['check_in'],
                'check_out' => $offerData['check_out'],
                'max_guests' => $offerData['max_guests'],
                'price' => $offerData['price'],
                'currency' => $offerData['currency'],
                'available_units' => $offerData['available_units'],
                'expires_at' => $offerData['expires_at'],
            ],
        );
    }
}
