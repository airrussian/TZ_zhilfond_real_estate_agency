<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationReportRequest;
use App\Jobs\GenerateNotificationReportJob;
use App\Models\NotificationReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class NotificationReportController extends Controller
{
    public function store(StoreNotificationReportRequest $request, User $user): JsonResponse
    {
        /** @var array{date_from: string, date_to: string} $validated */
        $validated = $request->validated();

        $report = NotificationReport::query()->create([
            'user_id' => $user->id,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'status' => NotificationReport::STATUS_PENDING,
        ]);

        GenerateNotificationReportJob::dispatch($report);

        $report->refresh();

        return response()->json([
            'id' => $report->id,
            'user_id' => $report->user_id,
            'date_from' => $report->date_from->toDateString(),
            'date_to' => $report->date_to->toDateString(),
            'status' => $report->status,
            'error_message' => $report->error_message,
            'output_path' => $report->output_path,
        ], 202);
    }

    public function show(User $user, NotificationReport $notification_report): JsonResponse
    {
        $this->ensureReportBelongsToUser($user, $notification_report);

        return response()->json([
            'id' => $notification_report->id,
            'user_id' => $notification_report->user_id,
            'date_from' => $notification_report->date_from->toDateString(),
            'date_to' => $notification_report->date_to->toDateString(),
            'status' => $notification_report->status,
            'error_message' => $notification_report->error_message,
            'output_path' => $notification_report->output_path,
        ]);
    }

    public function download(User $user, NotificationReport $notification_report): Response
    {
        $this->ensureReportBelongsToUser($user, $notification_report);

        if ($notification_report->status === NotificationReport::STATUS_FAILED) {
            return response()->json([
                'message' => 'Report generation failed.',
                'error' => $notification_report->error_message,
            ], 422);
        }

        if ($notification_report->status !== NotificationReport::STATUS_COMPLETED || $notification_report->output_path === null) {
            return response()->json([
                'message' => 'Report is not ready yet.',
                'status' => $notification_report->status,
            ], 409);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($notification_report->output_path)) {
            return response()->json([
                'message' => 'Report file is missing.',
            ], 404);
        }

        return $disk->download(
            $notification_report->output_path,
            'notification-report-'.$notification_report->id.'.txt',
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    private function ensureReportBelongsToUser(User $user, NotificationReport $notification_report): void
    {
        abort_unless($notification_report->user_id === $user->id, 404);
    }
}
