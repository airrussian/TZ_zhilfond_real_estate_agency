<?php

namespace Tests\Feature;

use App\Jobs\GenerateNotificationReportJob;
use App\Models\Notification;
use App\Models\NotificationReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_job_and_leaves_report_pending_when_queue_is_faked(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->postJson("/api/users/{$user->id}/notification-reports", [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-07',
        ]);

        $response->assertAccepted();
        Queue::assertPushed(GenerateNotificationReportJob::class);

        $report = NotificationReport::query()->firstOrFail();
        $this->assertSame(NotificationReport::STATUS_PENDING, $report->status);
    }

    public function test_it_generates_file_and_completes_report_with_sync_queue(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        Carbon::setTestNow('2026-05-01 14:00:00');
        Notification::query()->create([
            'user_id' => $user->id,
            'channel' => 'email',
            'message' => 'hello',
            'status' => 'sent',
        ]);
        Carbon::setTestNow();

        $this->postJson("/api/users/{$user->id}/notification-reports", [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-01',
        ])->assertAccepted();

        $report = NotificationReport::query()->firstOrFail();
        $this->assertSame(NotificationReport::STATUS_COMPLETED, $report->status);
        $this->assertNotNull($report->output_path);
        Storage::disk('local')->assertExists((string) $report->output_path);

        $this->getJson("/api/users/{$user->id}/notification-reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->get("/api/users/{$user->id}/notification-reports/{$report->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_download_returns_409_when_report_still_pending(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->postJson("/api/users/{$user->id}/notification-reports", [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-02',
        ])->assertAccepted();

        $report = NotificationReport::query()->firstOrFail();

        $this->getJson("/api/users/{$user->id}/notification-reports/{$report->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('status', 'pending');
    }

    public function test_show_returns_404_when_report_belongs_to_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $report = NotificationReport::query()->create([
            'user_id' => $userA->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-02',
            'status' => NotificationReport::STATUS_COMPLETED,
        ]);

        $this->getJson("/api/users/{$userB->id}/notification-reports/{$report->id}")
            ->assertNotFound();
    }

    public function test_store_validates_date_order(): void
    {
        $user = User::factory()->create();

        $this->postJson("/api/users/{$user->id}/notification-reports", [
            'date_from' => '2026-05-10',
            'date_to' => '2026-05-01',
        ])->assertUnprocessable();
    }
}
