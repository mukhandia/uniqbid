<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ReportDateRange
{
    public const TIMEZONE = 'Africa/Nairobi';

    public const MAX_DAYS = 366;

    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($this->start->greaterThan($this->end)) {
            throw new InvalidArgumentException('Report start must be on or before end.');
        }
    }

    public static function fromFilter(string $period, string $from = '', string $to = ''): self
    {
        if ($period === 'custom') {
            return self::fromCustom($from, $to);
        }

        $daysBack = match ($period) {
            '7d' => 6,
            '90d' => 89,
            default => 29,
        };

        $today = now()->timezone(self::TIMEZONE);

        return new self(
            $today->startOfDay()->subDays($daysBack),
            $today->endOfDay(),
        );
    }

    public static function fromCustom(string $from, string $to): self
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return self::fromFilter('30d');
        }

        $start = CarbonImmutable::parse($from.' 00:00:00', self::TIMEZONE)->startOfDay();
        $end = CarbonImmutable::parse($to.' 23:59:59', self::TIMEZONE)->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $span = (int) round($start->startOfDay()->diffInDays($end->startOfDay()));

        if ($span > self::MAX_DAYS - 1) {
            $start = $end->startOfDay()->subDays(self::MAX_DAYS - 1);
        }

        return new self($start, $end);
    }

    public function startUtc(): CarbonImmutable
    {
        return $this->start->timezone('UTC');
    }

    public function endUtc(): CarbonImmutable
    {
        return $this->end->timezone('UTC');
    }

    public function fromDate(): string
    {
        return $this->start->toDateString();
    }

    public function toDate(): string
    {
        return $this->end->toDateString();
    }

    public function label(): string
    {
        return $this->start->format('d M Y').' – '.$this->end->format('d M Y').' EAT';
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function days(): array
    {
        $days = [];
        $cursor = $this->start->startOfDay();
        $last = $this->end->startOfDay();

        while ($cursor->lte($last)) {
            $days[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    public function dayLabel(CarbonImmutable $day): string
    {
        return $this->start->year === $this->end->year
            ? $day->format('j M')
            : $day->format('j M Y');
    }
}
