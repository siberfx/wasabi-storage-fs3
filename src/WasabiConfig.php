<?php

declare(strict_types=1);

namespace Siberfx\WasabiStorage;

use InvalidArgumentException;

/**
 * Turns a `wasabi` disk definition into a configuration Laravel's S3 driver
 * (and therefore the AWS SDK + Flysystem v3 S3 adapter) understands.
 */
final class WasabiConfig
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function toS3(array $config): array
    {
        if (empty($config['bucket'])) {
            throw new InvalidArgumentException('The [wasabi] disk requires a [bucket] option.');
        }

        $region = (string) ($config['region'] ?? '') ?: 'us-east-1';

        $defaults = [
            'region' => $region,
            'endpoint' => WasabiEndpoint::forRegion($region),

            // Virtual-hosted style (bucket.s3.region.wasabisys.com). Switch to
            // path style for bucket names containing dots (TLS wildcard issue).
            'use_path_style_endpoint' => false,

            // Wasabi buckets are private and public access is blocked on most
            // accounts, so never send `public-read` ACLs unless asked to.
            'visibility' => 'private',

            // aws-sdk-php >= 3.337 adds CRC32 checksums to every upload by
            // default. Only send them when an operation requires them, which is
            // the safe setting for S3-compatible providers.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',

            'throw' => false,
        ];

        $config = array_replace($defaults, array_filter(
            $config,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        ));

        $config['driver'] = 's3';
        $config['endpoint'] = rtrim((string) $config['endpoint'], '/');
        $config['root'] = trim((string) ($config['root'] ?? ''), '/');
        $config['use_path_style_endpoint'] = filter_var($config['use_path_style_endpoint'], FILTER_VALIDATE_BOOL);

        return $config;
    }
}
