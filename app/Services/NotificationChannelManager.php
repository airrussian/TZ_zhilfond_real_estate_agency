<?php

namespace App\Services;

use App\Contracts\NotificationChannel;
use App\Services\NotificationChannels\EmailNotificationChannel;
use App\Services\NotificationChannels\TelegramNotificationChannel;
use InvalidArgumentException;

class NotificationChannelManager
{
    /**
     * @var array<string, class-string<NotificationChannel>>
     */
    private array $channels = [
        'email' => EmailNotificationChannel::class,
        'telegram' => TelegramNotificationChannel::class,
    ];

    public function resolve(string $channel): NotificationChannel
    {
        $className = $this->channels[$channel] ?? null;

        if ($className === null) {
            throw new InvalidArgumentException("Unsupported channel [{$channel}]");
        }

        return app($className);
    }
}
