<?php

declare(strict_types=1);

use Siberfx\WasabiStorage\WasabiConfig;

it('maps a wasabi disk onto an s3 config with safe defaults', function () {
    $config = WasabiConfig::toS3([
        'driver' => 'wasabi',
        'key' => 'k',
        'secret' => 's',
        'region' => 'eu-central-2',
        'bucket' => 'b',
        'root' => '/uploads/',
    ]);

    expect($config)
        ->driver->toBe('s3')
        ->endpoint->toBe('https://s3.eu-central-2.wasabisys.com')
        ->region->toBe('eu-central-2')
        ->bucket->toBe('b')
        ->root->toBe('uploads')
        ->visibility->toBe('private')
        ->use_path_style_endpoint->toBeFalse()
        ->request_checksum_calculation->toBe('when_required')
        ->response_checksum_validation->toBe('when_required')
        ->throw->toBeFalse();
});

it('defaults to us-east-1 when region is empty or null', function (?string $region) {
    expect(WasabiConfig::toS3(['bucket' => 'b', 'region' => $region]))
        ->region->toBe('us-east-1')
        ->endpoint->toBe('https://s3.us-east-1.wasabisys.com');
})->with([null, '']);

it('lets every default be overridden', function () {
    $config = WasabiConfig::toS3([
        'bucket' => 'my.dotted.bucket',
        'region' => 'eu-central-1',
        'endpoint' => 'https://s3.de-1.wasabisys.com/',
        'use_path_style_endpoint' => 'true',
        'visibility' => 'public',
        'request_checksum_calculation' => 'when_supported',
        'throw' => true,
    ]);

    expect($config)
        ->endpoint->toBe('https://s3.de-1.wasabisys.com')
        ->use_path_style_endpoint->toBeTrue()
        ->visibility->toBe('public')
        ->request_checksum_calculation->toBe('when_supported')
        ->throw->toBeTrue();
});

it('requires a bucket', function () {
    WasabiConfig::toS3(['region' => 'eu-central-1']);
})->throws(InvalidArgumentException::class, 'requires a [bucket]');
