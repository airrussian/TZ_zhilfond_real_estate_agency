<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_notification_and_queue_item(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/notifications', [
            'user_id' => $user->id,
            'channel' => 'email',
            'message' => 'Test notification',
            'payload' => 'Текст полезной нагрузки',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'processing');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'channel' => 'email',
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'email',
            'status' => 'queued',
        ]);
    }
}
