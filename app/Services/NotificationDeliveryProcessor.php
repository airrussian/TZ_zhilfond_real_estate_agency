<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use Carbon\CarbonImmutable;
use Throwable;

class NotificationDeliveryProcessor
{
    public function __construct(
        private readonly NotificationChannelManager $channelManager,
    ) {}

    public function process(NotificationDelivery $delivery): void
    {
        $notification = $delivery->notification;

        try {
            $channel = $this->channelManager->resolve($delivery->channel);
            $channel->send($delivery);

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
            ]);

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $this->markAsFailed($delivery, $exception->getMessage());
        }
    }

    private function markAsFailed(NotificationDelivery $delivery, string $errorMessage): void
    {
        $attempts = $delivery->attempts + 1;
        $hasMoreAttempts = $attempts < $delivery->max_attempts;

        $delivery->update([
            'attempts' => $attempts,
            'status' => $hasMoreAttempts ? 'queued' : 'error',
            'available_at' => $hasMoreAttempts
                ? CarbonImmutable::now()->addSeconds(2 ** $attempts)
                : $delivery->available_at,
            'last_error' => $errorMessage,
            'reserved_at' => null,
        ]);

        if (! $hasMoreAttempts) {
            $delivery->notification->update([
                'status' => 'error',
                'error_message' => $errorMessage,
            ]);
        }
    }
}
