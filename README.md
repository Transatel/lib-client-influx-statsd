# lib-client-influx-statsd

A single-file lib for sending metrics in PHP.

It only supports [InfluxData's StatsD derivative](https://github.com/influxdata/telegraf/tree/master/plugins/inputs/statsd).

As such, it must be used with [influxdata/telegraf StatsD input plugin](https://github.com/influxdata/telegraf/tree/master/plugins/inputs/statsd).

It is basically a fork of [beberlei/metrics](https://github.com/beberlei/metrics)'s [InfluxDB](https://github.com/beberlei/metrics/blob/master/src/Beberlei/Metrics/Collector/InfluxDB.php) collector.

We recommand using it with simple scripts or legacy code that hasn't been adpated to use autoload / composer.

For bigger and / or modern projects, we recommend that you use [beberlei/metrics](https://github.com/beberlei/metrics).

## Basic Usage

At the top of your script sources:

```
require_once('InfluxStatsdClient.php');

$mtu = 1432;

$statsdBackendAddress = '127.0.0.1';
$statsdBackendPort    = 8086;

$statsdClient = new InfluxStatsdClient($statsdBackendAddress, $statsdBackendPort, $mtu);

// If you want tags to be set for every points measured with this client instance
$globalTags = array(
   'host' => php_uname('n'),
);
$statsdClient->setGlobalTags($globalTags);
```

Then, register points.

```
$statsdClient->increment('counter.ingestedRecords');


$startTime = microtime(1);
// ... do stuff
$durationMs = round((microtime(1) - $startTime) * 1000);
$statsdClient->timing('timer.doStuff', $durationMs);
```

And finally, flush all your collected points:

```
$statsdClient->flush();
```

## Features

### Per measurement tags

Each collection method takes an optionnal final argument to add tags in addition to those eventually defined globally.

For example:

    $statsdClient->increment('counter.ingestedRecords', array('record_type' => $recordType));

### MTU

Our library support measurements aggregation in output packets.

For this to be enabled, you need to pass a non-empty MTU as a third parameter to the client constructor.

Otherwise, each and every point would get flushed at each call to flush().

Here are the recommended values for the MTU, according to the [original StastD documentation](https://github.com/etsy/statsd/blob/master/docs/metric_types.md#multi-metric-packets):
- 512: Commodity Internet, typically for older intranets or if transiting by the Internet
- 1432: Fast Ethernet, typically valid for most recent intranets
- 8932: Gigabit Ethernet, only for dedicated links with possibility to have jumbo frames

Please note that, as timestamps are set at collector-level (StatsD instance), one should use this feature on components with a relatively high processing rate.
