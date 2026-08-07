<?php

namespace Tests\Feature;

use App\Models\Reports;
use App\Models\User;
use App\Models\StoreRooms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_create_report()
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $store = StoreRooms::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/reports', [
                'store_id' => $store->id,
                'title' => 'Problema con la bodega',
                'priority' => 'high',
                'report_type' => 'store',
                'description' => 'La bodega no coincide con la descripción publicada.',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reports', [
            'store_id' => $store->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function admin_can_resolve_report()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = Reports::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/reports/{$report->id}/status", [
                'status' => 'resolved',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'resolved',
            ]);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
        ]);
    }
}
