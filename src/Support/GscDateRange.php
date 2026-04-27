<?php

namespace Wonchoe\GscManager\Support;

use Carbon\CarbonImmutable;

class GscDateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
    }

    /**
     * @param array{from?: string|null, to?: string|null, days?: int|string|null} $options
     */
    public static function fromOptions(array $options = []): self
    {
        if (! empty($options['from']) && ! empty($options['to'])) {
            return new self(
                CarbonImmutable::parse((string) $options['from'], 'America/Los_Angeles')->startOfDay(),
                CarbonImmutable::parse((string) $options['to'], 'America/Los_Angeles')->startOfDay(),
            );
        }

        $days = max(1, (int) ($options['days'] ?? config('gsc-manager.analytics.days_back', 3)));
        $to = CarbonImmutable::now('America/Los_Angeles')->subDay()->startOfDay();

        return new self($to->subDays($days - 1), $to);
    }

    public function startDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function endDate(): string
    {
        return $this->to->format('Y-m-d');
    }
}
