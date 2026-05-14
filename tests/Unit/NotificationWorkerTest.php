<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWorkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_marks_notification_as_sent(): void
    {
        $user = User::factory()->create();

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'message' => 'Send me updates',
            'payload' => 'chat_id=1',
            'status' => 'processing',
        ]);

        NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'telegram',
            'payload' => 'chat_id=1',
            'status' => 'queued',
            'available_at' => now(),
        ]);

        $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'status' => 'sent',
        ]);
    }
}
