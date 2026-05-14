<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $notification = Notification::query()->create([
            'user_id' => $validated['user_id'],
            'channel' => $validated['channel'],
            'message' => $validated['message'],
            'payload' => $validated['payload'] ?? null,
            'status' => 'processing',
        ]);

        NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
            'payload' => $notification->payload,
            'status' => 'queued',
            'available_at' => now(),
        ]);

        return response()->json($notification, 201);
    }

    public function show(Notification $notification): JsonResponse
    {
        return response()->json([
            'id' => $notification->id,
            'status' => $notification->status,
            'channel' => $notification->channel,
            'sent_at' => $notification->sent_at,
            'error_message' => $notification->error_message,
        ]);
    }

    public function userHistory(User $user): JsonResponse
    {
        $query = $user->notifications()->latest('id');

        $status = request()->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $channel = request()->query('channel');
        if (is_string($channel) && $channel !== '') {
            $query->where('channel', $channel);
        }

        return response()->json($query->paginate(20));
    }
}
