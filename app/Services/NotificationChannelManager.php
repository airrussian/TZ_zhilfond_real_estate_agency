<?php

namespace App\Services;

use App\Contracts\NotificationChannel;
use App\Services\NotificationChannels\EmailNotificationChannel;
use App\Services\NotificationChannels\TelegramNotificationChannel;
use InvalidArgumentException;

/**
 * Класс-менеджер для разрешения и получения экземпляра соответствующего
 * канала уведомлений (например, email, telegram) по строковому идентификатору.
 */
class NotificationChannelManager
{
    /**
     * @var array<string, class-string<NotificationChannel>>
     */
    private array $channels = [
        'email' => EmailNotificationChannel::class,
        'telegram' => TelegramNotificationChannel::class,
    ];

    /**
     * @return list<string>
     */
    public function supportedChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Получает экземпляр канала уведомлений по строковому идентификатору.
     *
     * @param  string  $channel  Идентификатор канала уведомлений.
     * @return NotificationChannel Экземпляр канала уведомлений.
     *
     * @throws InvalidArgumentException Если канал не поддерживается.
     */
    public function resolve(string $channel): NotificationChannel
    {
        $className = $this->channels[$channel] ?? null;

        if ($className === null) {
            throw new InvalidArgumentException("Unsupported channel [{$channel}]");
        }

        return app($className);
    }
}
