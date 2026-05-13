<?php

namespace App\Contracts;

use App\Models\NotificationDelivery;

interface NotificationChannel
{
    public function send(NotificationDelivery $delivery): void;
}
