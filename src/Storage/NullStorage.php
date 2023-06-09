<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Storage;

use Zlodes\PrometheusClient\Storage\DTO\MetricValue;

final class NullStorage implements Storage
{
    public function fetch(): iterable
    {
        return [];
    }

    public function clear(): void
    {
        // Do nothing
    }

    public function setValue(MetricValue $value): void
    {
        // Do nothing
    }

    public function incrementValue(MetricValue $value): void
    {
        // Do nothing
    }

    public function persistHistogram(MetricValue $value, array $buckets): void
    {
        // Do nothing
    }
}
