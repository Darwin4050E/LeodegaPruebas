<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\StoreRooms;
use App\Models\Ratings;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RatingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tenant_can_publish_rating()
    {
        // TC-B-19
        $user = User::factory()->create();
        $storeRoom = StoreRooms::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ratings', [
                'store_id' => $storeRoom->id,
                'stars' => 5,
                'comment' => 'Excelente bodega',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ratings', [
            'store_id' => $storeRoom->id,
            'user_id' => $user->id,
            'stars' => 5,
        ]);
    }

    /** @test */
    public function store_room_rating_average_is_recalculated()
    {
        // TC-B-20
        $storeRoom = StoreRooms::factory()->create();

        Ratings::factory()->create([
            'store_id' => $storeRoom->id,
            'stars' => 4,
        ]);

        Ratings::factory()->create([
            'store_id' => $storeRoom->id,
            'stars' => 5,
        ]);

        $response = $this->getJson('/api/storeRooms');


        $response->assertStatus(200)
            ->assertJsonFragment([
                'rating_avg' => 4.5,
            ]);
    }
}
