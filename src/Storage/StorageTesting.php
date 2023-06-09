<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Storage;

use PHPUnit\Framework\Attributes\DataProvider;
use Zlodes\PrometheusClient\Storage\DTO\MetricNameWithLabels;
use Zlodes\PrometheusClient\Storage\DTO\MetricValue; // phpcs:ignore
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertSameSize;

trait StorageTesting
{
    public function testGetSet(): void
    {
        $storage = $this->createStorage();

        assertEmpty($this->fetchList($storage));

        $storage->setValue(
            new MetricValue(
                new MetricNameWithLabels('cpu_temp', ['core' => '0']),
                60
            )
        );

        $storage->setValue(
            new MetricValue(
                new MetricNameWithLabels('cpu_temp', ['core' => '1']),
                62
            )
        );

        $storage->incrementValue(
            new MetricValue(
                new MetricNameWithLabels('system_restarts_total'),
                1
            )
        );

        $storage->incrementValue(
            new MetricValue(
                new MetricNameWithLabels('system_restarts_total'),
                1
            )
        );

        $storage->setValue(
            new MetricValue(
                new MetricNameWithLabels('days_left'),
                10
            )
        );

        $storage->incrementValue(
            new MetricValue(
                new MetricNameWithLabels('days_left'),
                -1
            )
        );

        $fetched = $this->fetchList($storage);
        $expected = [
            new MetricValue(
                new MetricNameWithLabels('cpu_temp', ['core' => '0']),
                60
            ),
            new MetricValue(
                new MetricNameWithLabels('cpu_temp', ['core' => '1']),
                62
            ),
            new MetricValue(
                new MetricNameWithLabels('system_restarts_total'),
                2
            ),
            new MetricValue(
                new MetricNameWithLabels('days_left'),
                9
            ),
        ];

        assertEquals($expected, $fetched);
    }

    public function testGetAllAndEmpty(): void
    {
        $storage = $this->createStorage();

        $storage->clear();
        assertEmpty($this->fetchList($storage));

        $storage->setValue(
            new MetricValue(
                new MetricNameWithLabels('cpu_temp'),
                70
            )
        );

        $storage->persistHistogram(
            new MetricValue(
                new MetricNameWithLabels('response_time'),
                0.5
            ),
            [0.1, 0.5, 1]
        );

        // 1 of cpu_temp gauge
        // 3 of response_time histogram
        // 1 +Inf of response_time histogram
        // 2 (sum and count) of response_time histogram
        assertCount(7, $this->fetchList($storage));

        $storage->clear();
        assertEmpty($this->fetchList($storage));
    }

    #[DataProvider('histogramDataProvider')]
    public function testHistogram(array $buckets, array $values, array $expectedFetched): void
    {
        $storage = $this->createStorage();

        $metricNameWithLabels = new MetricNameWithLabels('response_time');

        foreach ($values as $value) {
            $storage->persistHistogram(
                new MetricValue(
                    $metricNameWithLabels,
                    $value
                ),
                $buckets
            );
        }

        $fetched = $this->fetchList($storage);
        assertSameSize($expectedFetched, $fetched);

        $actualFetched = [];

        foreach ($fetched as $metricValue) {
            $name = $metricValue->metricNameWithLabels->metricName;
            $labels = $metricValue->metricNameWithLabels->labels;
            $value = $metricValue->value;

            if (str_ends_with($name, '_sum')) {
                $actualFetched['sum'] = $value;

                continue;
            }

            if (str_ends_with($name, '_count')) {
                $actualFetched['count'] = $value;

                continue;
            }

            $actualFetched[$labels['le']] = $value;
        }

        assertEquals($expectedFetched, $actualFetched);
    }

    /**
     * @codeCoverageIgnore
     */
    public static function histogramDataProvider(): iterable
    {
        yield 'all zeroes' => [
            [0, 1, 2, 3, 4],
            [0, 0, 0, 0, 0],
            [
                "0" => 5,
                "1" => 5,
                "2" => 5,
                "3" => 5,
                "4" => 5,
                "+Inf" => 5,
                "sum" => 0,
                "count" => 5,
            ],
        ];

        yield 'simple' => [
            [0, 1, 2, 3, 4, 5],
            [0, 1, 1, 2, 3, 3, 4, 5, 6],
            [
                "0" => 1,
                "1" => 3,
                "2" => 4,
                "3" => 6,
                "4" => 7,
                "5" => 8,
                "+Inf" => 9,
                "sum" => 25,
                "count" => 9,
            ],
        ];

        yield 'complex' => [
            [0, 1, 2, 3, 3.5, 5, 10],
            [1, 1, 1, 2, 0, 5, 7.5, 10, 30, 0.7, 53, 3.5, 4, 4, 8],
            [
                "0" => 1,
                "1" => 5,
                "2" => 6,
                "3" => 6,
                "3.5" => 7,
                "5" => 10,
                "10" => 13,
                "+Inf" => 15,
                "sum" => 130.7,
                "count" => 15,
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testHistogramWithLabels(): void
    {
        $storage = $this->createStorage();

        $storage->persistHistogram(
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['method' => 'GET']),
                1
            ),
            [0, 1, 2, 3, 4, 5]
        );

        $storage->persistHistogram(
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['method' => 'GET']),
                2
            ),
            [0, 1, 2, 3, 4, 5]
        );

        $storage->persistHistogram(
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['method' => 'GET']),
                5
            ),
            [0, 1, 2, 3, 4, 5]
        );

        $storage->persistHistogram(
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['method' => 'POST']),
                2
            ),
            [0, 1, 2, 3, 4, 5]
        );

        $storage->persistHistogram(
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['method' => 'POST']),
                3
            ),
            [0, 1, 2, 3, 4, 5]
        );

        $actualFetched = $this->fetchList($storage);

        $expectedFetched = [
            // GET
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '0', 'method' => 'GET']),
                0.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '1', 'method' => 'GET']),
                1.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '2', 'method' => 'GET']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '3', 'method' => 'GET']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '4', 'method' => 'GET']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '5', 'method' => 'GET']),
                3.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '+Inf', 'method' => 'GET']),
                3.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric_sum', ['method' => 'GET']),
                8.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric_count', ['method' => 'GET']),
                3.0
            ),

            // POST
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '0', 'method' => 'POST']),
                0.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '1', 'method' => 'POST']),
                0.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '2', 'method' => 'POST']),
                1.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '3', 'method' => 'POST']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '4', 'method' => 'POST']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '5', 'method' => 'POST']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric', ['le' => '+Inf', 'method' => 'POST']),
                2.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric_sum', ['method' => 'POST']),
                5.0
            ),
            new MetricValue(
                new MetricNameWithLabels('some_metric_count', ['method' => 'POST']),
                2.0
            ),
        ];

        assertEquals($expectedFetched, $actualFetched);
    }

    abstract protected function createStorage(): Storage;

    /**
     * @return list<MetricValue>
     */
    protected function fetchList(Storage $storage): array
    {
        $fetched = [];

        foreach ($storage->fetch() as $value) {
            $fetched[] = $value;
        }

        return $fetched;
    }
}
