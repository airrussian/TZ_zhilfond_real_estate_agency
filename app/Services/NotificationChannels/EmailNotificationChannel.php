<?php

namespace App\Services\NotificationChannels;

use App\Contracts\NotificationChannel;
use App\Models\NotificationDelivery;
use RuntimeException;

class EmailNotificationChannel implements NotificationChannel
{
    public function send(NotificationDelivery $delivery): void
    {
        $decoded = json_decode((string) $delivery->getRawOriginal('payload'), true);
        $payload = is_array($decoded) ? $decoded : [];

        if (($payload['simulate_failure'] ?? null) === true) {
            throw new RuntimeException('Simulated email delivery failure.');
        }
    }
}
