<?php

namespace Tests\Feature;

use App\Models\Payments;
use App\Models\Reservations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    // Fase 0.5: /api/payments ahora requiere sesión en todos sus métodos — es el
    // dato más sensible de todos (hallazgo #1 de la matriz de riesgo).
    public function test_index_requires_authentication()
    {
        $response = $this->getJson('/api/payments');

        $response->assertStatus(401);
    }

    public function test_index_returns_payments_when_authenticated()
    {
        $caller = User::factory()->create();
        Payments::factory()->count(2)->create();

        $response = $this->actingAs($caller, 'sanctum')->getJson('/api/payments');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_store_requires_authentication()
    {
        $reservation = Reservations::factory()->create();

        $response = $this->postJson('/api/payments', [
            'reservation_id' => $reservation->id,
            'payment_method' => 'credit card',
            'payment_state' => 'paid',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_creates_payment_when_authenticated()
    {
        $caller = User::factory()->create();
        $reservation = Reservations::factory()->create();

        $response = $this->actingAs($caller, 'sanctum')->postJson('/api/payments', [
            'reservation_id' => $reservation->id,
            'payment_method' => 'credit card',
            'payment_state' => 'paid',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', ['reservation_id' => $reservation->id]);
    }

    public function test_store_fails_with_invalid_payment_method()
    {
        $caller = User::factory()->create();
        $reservation = Reservations::factory()->create();

        $response = $this->actingAs($caller, 'sanctum')->postJson('/api/payments', [
            'reservation_id' => $reservation->id,
            'payment_method' => 'bitcoin',
            'payment_state' => 'paid',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_destroy_requires_authentication()
    {
        $payment = Payments::factory()->create();

        $response = $this->deleteJson("/api/payments/{$payment->id}");

        $response->assertStatus(401);
    }

    public function test_destroy_deletes_payment_when_authenticated()
    {
        $caller = User::factory()->create();
        $payment = Payments::factory()->create();

        $response = $this->actingAs($caller, 'sanctum')->deleteJson("/api/payments/{$payment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }
}
