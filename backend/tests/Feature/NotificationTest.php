<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Notifications;
use App\Services\NotificationService;
use App\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function system_creates_notification_when_event_occurs()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $notification = NotificationService::send(
            $sender->id,
            $receiver->id,
            NotificationType::RESERVATION_REQUEST,
            'Nueva solicitud',
            'Tienes una nueva solicitud',
            ['test' => true]
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'title' => 'Nueva solicitud',
            'is_read' => false,
        ]);
    }

    /** @test */
    public function user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();

        $notification = Notifications::factory()->create([
            'receiver_id' => $user->id,
            'is_read' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->post("/api/notifications/{$notification->id}/read")
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }
}
