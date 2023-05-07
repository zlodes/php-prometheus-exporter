# PHP Prometheus Exporter

This package gives you an ability to collect and export [Prometheus](https://prometheus.io/) metrics from any modern PHP app.
Now supports only Counter and Gauge metric types.

## Adapters

* For Laravel: [zlodes/prometheus-exporter-laravel](https://github.com/zlodes/php-prometheus-exporter-laravel)

## Installation

```shell
composer require zlodes/php-prometheus-exporter
```

## Class responsibilities

| Interface                                | Description                  | Default implementation                                       |
|------------------------------------------|------------------------------|--------------------------------------------------------------|
| [Collector](src/Collector/Collector.php) | To collect metrics           | [PersistentCollector](src/Collector/PersistentCollector.php) |
| [Registry](src/Registry/Registry.php)    | To declare a specific metric | [ArrayRegistry](src/Registry/ArrayRegistry.php)              |
| [Storage](src/Storage/Storage.php)       | Metrics values storage       | [InMemoryStorage](src/Storage/InMemoryStorage.php)           |
| [Exporter](src/Exporter/Exporter.php)    | Output collected metrics     | [PersistentExporter](src/Exporter/PersistentExporter.php)    |

Each class should be registered as a service. As a `singleton` in Laravel or `shared` service in Symfony.

## Roadmap

- [ ] Histogram metric type
- [ ] Summary metric type

## Simple example

```php
use Psr\Log\NullLogger;
use Zlodes\PrometheusExporter\Collector\PersistentCollector;
use Zlodes\PrometheusExporter\Exporter\PersistentExporter;
use Zlodes\PrometheusExporter\MetricTypes\Counter;
use Zlodes\PrometheusExporter\MetricTypes\Gauge;
use Zlodes\PrometheusExporter\Normalization\JsonMetricKeyDenormalizer;
use Zlodes\PrometheusExporter\Registry\ArrayRegistry;
use Zlodes\PrometheusExporter\Storage\InMemoryStorage;

$registry = new ArrayRegistry();
$storage = ;
$metricKeyDenormalizer = new JsonMetricKeyDenormalizer();

// Register your metrics
$registry
    ->registerMetric(
        new Gauge('body_temperature', 'Body temperature in Celsius')
    )
    ->registerMetric(
        new Counter('steps', 'Steps count')
    );

// Create a Collector
$collector = new PersistentCollector(
    $registry,
    new InMemoryStorage(),
    new JsonMetricKeyDenormalizer(),
    new NullLogger(),
);

// Collect metrics
$collector->gaugeSet(
    gaugeName: 'body_temperature',
    labels: ['source' => 'armpit'],
    value: 36.6
);
$collector->gaugeSet(
    gaugeName: 'body_temperature',
    labels: ['source' => 'ass'],
    value: 37.2
);
$collector->counterIncrement('steps');

// Export metrics
$exporter = new PersistentExporter(
    $registry,
    $storage
);
```

## Testing

### Run tests

```shell
php ./vendor/bin/phpunit
```

### Testing your storage

There is a simple [trait](tests/Storage/StorageTesting.php) to tests any storage you want. Here is an example:

```php
class InMemoryStorageTest extends TestCase
{
    use StorageTesting;

    private function createStorage(): Storage
    {
        return new InMemoryStorage();
    }
}
```
