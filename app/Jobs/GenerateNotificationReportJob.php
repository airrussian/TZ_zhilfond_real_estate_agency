<?php

namespace App\Jobs;

use App\Models\NotificationReport;
use App\Services\UserNotificationReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateNotificationReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public NotificationReport $report,
    ) {}

    public function handle(UserNotificationReportGenerator $generator): void
    {
        $report = $this->report->fresh();
        if ($report === null) {
            return;
        }

        if ($report->status === NotificationReport::STATUS_COMPLETED) {
            return;
        }

        $report->update([
            'status' => NotificationReport::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        $tmpRelative = "reports/{$report->user_id}/{$report->id}.tmp";
        $finalRelative = "reports/{$report->user_id}/{$report->id}.txt";

        try {
            $byChannel = $generator->aggregateByChannel(
                $report->user_id,
                $report->date_from,
                $report->date_to,
            );

            $body = $generator->renderText(
                $report->user_id,
                $report->date_from,
                $report->date_to,
                $byChannel,
            );

            $disk = Storage::disk('local');
            $disk->makeDirectory(dirname($tmpRelative));

            if ($disk->exists($tmpRelative)) {
                $disk->delete($tmpRelative);
            }
            if ($disk->exists($finalRelative)) {
                $disk->delete($finalRelative);
            }

            if (! $disk->put($tmpRelative, $body)) {
                throw new \RuntimeException('Failed to write report temp file.');
            }

            if (! $disk->move($tmpRelative, $finalRelative)) {
                throw new \RuntimeException('Failed to finalize report file.');
            }

            $report->update([
                'status' => NotificationReport::STATUS_COMPLETED,
                'output_path' => $finalRelative,
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Notification report generation failed', [
                'report_id' => $report->id,
                'exception' => $e,
            ]);

            try {
                $disk = Storage::disk('local');
                if ($disk->exists($tmpRelative)) {
                    $disk->delete($tmpRelative);
                }
            } catch (Throwable) {
                // ignore cleanup errors
            }

            $report->update([
                'status' => NotificationReport::STATUS_FAILED,
                'output_path' => null,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
