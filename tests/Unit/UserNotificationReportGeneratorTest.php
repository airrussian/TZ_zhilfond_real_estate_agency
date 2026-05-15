<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\User;
use App\Services\UserNotificationReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserNotificationReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_notifications_and_errors_per_channel(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-05-01 10:00:00');
        Notification::query()->create([
            'user_id' => $user->id,
            'channel' => 'email',
            'message' => 'a',
            'status' => 'sent',
        ]);
        Carbon::setTestNow('2026-05-01 11:00:00');
        Notification::query()->create([
            'user_id' => $user->id,
            'channel' => 'email',
            'message' => 'b',
            'status' => 'error',
        ]);
        Carbon::setTestNow('2026-05-01 12:00:00');
        Notification::query()->create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'message' => 'c',
            'status' => 'error',
        ]);
        Carbon::setTestNow();

        $gen = app(UserNotificationReportGenerator::class);
        $day = Carbon::parse('2026-05-01');
        $agg = $gen->aggregateByChannel($user->id, $day, $day);

        $this->assertSame(2, $agg['email']['total']);
        $this->assertSame(1, $agg['email']['errors']);
        $this->assertSame(1, $agg['telegram']['total']);
        $this->assertSame(1, $agg['telegram']['errors']);
    }
}
