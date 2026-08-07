<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StoreRooms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreRoomModerationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_approve_a_store_room()
    {
        // Admin
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        // Store room en estado pending
        $storeRoom = StoreRooms::factory()->create([
            'publication_status' => 'pending',
        ]);

        // Act: aprobar bodega
        $response = $this->putJson("/api/storeRooms/{$storeRoom->id}", [
            'publication_status' => 'approved',
        ]);

        // Assert
        $response->assertStatus(200);

        $this->assertDatabaseHas('storeRooms', [
            'id' => $storeRoom->id,
            'publication_status' => 'approved',
        ]);
    }

    /** @test */
    public function public_store_rooms_endpoint_returns_store_rooms_with_different_publication_statuses()
    {
        // Crear bodegas con distintos estados
        StoreRooms::factory()->create([
            'publication_status' => 'approved',
        ]);

        StoreRooms::factory()->create([
            'publication_status' => 'pending',
        ]);

        StoreRooms::factory()->create([
            'publication_status' => 'rejected',
        ]);

        // Act
        $response = $this->getJson('/api/storeRooms');

        // Assert
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'publication_status' => 'approved',
        ]);

        $response->assertJsonFragment([
            'publication_status' => 'pending',
        ]);
    }
}
