<?php

namespace Tests\Feature;

use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'client_reference' => 'ORDER-123',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ];
    }

    public function test_creates_reservation_successfully(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.client_reference', 'ORDER-123')
            ->assertJsonPath('data.customer_name', 'John Doe')
            ->assertJsonPath('data.customer_email', 'john@example.com');

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'ORDER-123',
        ]);
    }

    public function test_decrements_available_units(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated();

        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function test_fails_when_no_available_units(): void
    {
        $offer = Offer::factory()->create(['available_units' => 0]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertStatus(422);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_validates_request(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $this->postJson("/api/offers/{$offer->id}/reservations", [
            'client_reference' => 'ORDER-123',
            'customer_name' => 'John Doe',
            'customer_email' => 'not-an-email',
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_email']);
    }

    public function test_concurrent_booking_protection(): void
    {
        $offer = Offer::factory()->create(['available_units' => 1]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertStatus(422);

        $this->assertSame(0, $offer->fresh()->available_units);
        $this->assertDatabaseCount('reservations', 1);
    }
}
