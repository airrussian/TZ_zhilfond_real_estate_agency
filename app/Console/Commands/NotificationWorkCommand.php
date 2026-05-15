<?php

namespace App\Console\Commands;

use App\Models\NotificationDelivery;
use App\Services\NotificationDeliveryProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('notifications:work {--once : Process only one queue item} {--sleep=2 : Sleep seconds between polls}')]
#[Description('Process notifications from MySQL table queue')]
class NotificationWorkCommand extends Command
{
    public function __construct(
        private readonly NotificationDeliveryProcessor $processor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runOnce = (bool) $this->option('once');
        $sleepSeconds = max(1, (int) $this->option('sleep'));

        do {
            $processed = $this->processNext();

            if ($runOnce) {
                break;
            }

            if (! $processed) {
                sleep($sleepSeconds);
            }
        } while (true);

        return self::SUCCESS;
    }

    private function processNext(): bool
    {        
        $deliveryId = DB::transaction(function () {

            $delivery = NotificationDelivery::query()
                ->where('status', 'queued')
                ->where('available_at', '<=', now())
                ->orderBy('id')
                ->lock('for update')
                ->first();

            if ($delivery === null) {
                return null;
            }

            $delivery->update([
                'status' => 'processing',
                'reserved_at' => now(),
            ]);

            return $delivery->id;
        });

        if ($deliveryId === null) {
            return false;
        }

        $delivery = NotificationDelivery::query()
            ->with('notification')
            ->find($deliveryId);

        if ($delivery === null) {
            return false;
        }

        $this->processor->process($delivery);

        return true;
    }
}
