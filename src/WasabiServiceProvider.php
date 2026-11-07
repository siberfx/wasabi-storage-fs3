<?php

declare(strict_types=1);

namespace Siberfx\WasabiStorage;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Siberfx\WasabiStorage\Console\WasabiCheckCommand;

class WasabiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('wasabi', function (Application $app, array $config) {
            /** @var FilesystemManager $manager */
            $manager = $app->make('filesystem');

            // Reuse Laravel's own S3 driver so the disk is a full
            // Illuminate\Filesystem\AwsS3V3Adapter: url(), temporaryUrl(),
            // temporaryUploadUrl(), Flysystem v3, streams, visibility, etc.
            return $manager->createS3Driver(WasabiConfig::toS3($config));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([WasabiCheckCommand::class]);
        }
    }
}
