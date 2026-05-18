<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Support;

use Illuminate\Support\Facades\Cache;

final class BatchLogReporter
{
    private const MAX_LOG_ENTRIES = 500;

    public function __construct(
        private readonly string $trackingId,
    ) {}

    public function logSizeResult(string $name, int $sizeBefore, int $sizeAfter): void
    {
        $percent = $sizeBefore > 0
            ? round((1 - $sizeAfter / $sizeBefore) * 100, 2)
            : 0;

        $this->log('success', sprintf(
            '%s (%s → %s, %s)',
            $name,
            FileSizeFormatter::format($sizeBefore),
            FileSizeFormatter::format($sizeAfter),
            $this->formatPercent($percent),
        ));
    }

    public function logConversion(string $format, string $baseName, int $referenceSize, int $convertedSize, string $vsLabel = 'original'): void
    {
        $percent = $referenceSize > 0
            ? round((1 - $convertedSize / $referenceSize) * 100, 2)
            : 0;

        $this->log('success', sprintf(
            '→ %s.%s (%s → %s, %s, vs %s)',
            $baseName,
            $format,
            FileSizeFormatter::format($referenceSize),
            FileSizeFormatter::format($convertedSize),
            $this->formatPercent($percent),
            $vsLabel,
        ));
    }

    public function log(string $type, string $message): void
    {
        $cacheKey = "image-editor-batch-log-{$this->trackingId}";
        $logs = Cache::get($cacheKey, []);

        $logs[] = [
            'type' => $type,
            'message' => $message,
            'time' => now()->format('H:i:s'),
        ];

        if (count($logs) > self::MAX_LOG_ENTRIES) {
            $logs = array_slice($logs, -self::MAX_LOG_ENTRIES);
        }

        Cache::put($cacheKey, $logs, now()->addHours(6));
    }

    private function formatPercent(float $percent): string
    {
        if ($percent > 0) {
            return "-{$percent}%";
        }

        if ($percent < 0) {
            return '+'.abs($percent).'%';
        }

        return '0%';
    }
}
