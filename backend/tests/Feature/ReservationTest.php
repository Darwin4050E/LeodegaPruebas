<?php

namespace Tests\Feature;

use App\Models\Reservations;
use App\Models\StoreRooms;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tenant_can_create_reservation_for_available_store_room()
    {
        $user = User::factory()->create();
        $tenant = Tenants::factory()->create(['user_id' => $user->id]);
        $room = StoreRooms::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', [
                'store_room_id' => $room->id,
                'start_date' => now()->addDays(1)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'total_mount' => 150,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reservations', [
            'store_room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function cannot_reserve_store_room_if_dates_are_already_confirmed()
    {
        $user = User::factory()->create();
        $tenant = Tenants::factory()->create(['user_id' => $user->id]);
        $room = StoreRooms::factory()->create();

        // Reserva confirmada existente
        Reservations::factory()->create([
            'store_room_id' => $room->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-10',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', [
                'store_room_id' => $room->id,
                'start_date' => '2026-02-05',
                'end_date' => '2026-02-12',
                'total_mount' => 200,
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'La bodega ya está reservada en esas fechas.'
        ]);
    }
}
