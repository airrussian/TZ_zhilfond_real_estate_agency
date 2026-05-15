<?php

namespace App\Services;

use App\Models\Notification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class UserNotificationReportGenerator
{
    public function __construct(
        private readonly NotificationChannelManager $channelManager,
    ) {}

    /**
     * @return array<string, array{total: int, errors: int}>
     */
    public function aggregateByChannel(int $userId, CarbonInterface $dateFrom, CarbonInterface $dateTo): array
    {
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();

        $rows = Notification::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('channel, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as errors', ['error'])
            ->groupBy('channel')
            ->get();

        $byChannel = [];
        foreach ($rows as $row) {
            $byChannel[(string) $row->channel] = [
                'total' => (int) $row->total,
                'errors' => (int) $row->errors,
            ];
        }

        foreach ($this->channelManager->supportedChannels() as $channel) {
            $byChannel += [$channel => ['total' => 0, 'errors' => 0]];
        }

        ksort($byChannel);

        return $byChannel;
    }

    public function renderText(int $userId, CarbonInterface $dateFrom, CarbonInterface $dateTo, array $byChannel): string
    {
        $from = Carbon::parse($dateFrom)->toDateString();
        $to = Carbon::parse($dateTo)->toDateString();

        $lines = [
            'Отчёт по уведомлениям',
            "Пользователь (id): {$userId}",
            "Период: {$from} — {$to}",
            '',
        ];

        foreach ($byChannel as $channel => $stats) {
            $lines[] = "Канал: {$channel}";
            $lines[] = '  Уведомлений: '.$stats['total'];
            $lines[] = '  Ошибок: '.$stats['errors'];
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }
}
