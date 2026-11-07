<?php

declare(strict_types=1);

namespace Siberfx\WasabiStorage\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\WasabiStorage\WasabiServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WasabiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.wasabi', [
            'driver' => 'wasabi',
            'key' => env('WASABI_ACCESS_KEY_ID', 'test-key'),
            'secret' => env('WASABI_SECRET_ACCESS_KEY', 'test-secret'),
            'region' => env('WASABI_DEFAULT_REGION', 'eu-central-1'),
            'bucket' => env('WASABI_BUCKET', 'my-bucket'),
            'root' => env('WASABI_ROOT'),
            'url' => env('WASABI_URL'),
        ]);
    }
}
