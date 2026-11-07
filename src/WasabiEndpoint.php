<?php

declare(strict_types=1);

namespace Siberfx\WasabiStorage;

use InvalidArgumentException;

/**
 * Resolves Wasabi S3 service URLs.
 *
 * @see https://docs.wasabi.com/docs/what-are-the-service-urls-for-wasabis-different-storage-regions
 */
final class WasabiEndpoint
{
    /**
     * Regions known at the time of writing. Unknown but well-formed regions are
     * still accepted so new Wasabi regions work without a package release.
     */
    public const array REGIONS = [
        // Americas
        'us-east-1' => 'N. Virginia',
        'us-east-2' => 'N. Virginia',
        'us-central-1' => 'Plano, Texas',
        'us-west-1' => 'Hillsboro, Oregon',
        'us-west-2' => 'San Jose, California',
        'ca-central-1' => 'Toronto',
        // EMEA
        'eu-central-1' => 'Amsterdam',
        'eu-central-2' => 'Frankfurt',
        'eu-west-1' => 'London',
        'eu-west-2' => 'Paris',
        'eu-west-3' => 'London',
        'eu-south-1' => 'Milan',
        // APAC
        'ap-northeast-1' => 'Tokyo',
        'ap-northeast-2' => 'Osaka',
        'ap-southeast-1' => 'Singapore',
        'ap-southeast-2' => 'Sydney',
    ];

    public static function forRegion(string $region): string
    {
        $region = strtolower(trim($region));

        if (! preg_match('/^[a-z]{2}-[a-z]+-\d+$/', $region)) {
            throw new InvalidArgumentException(
                "Invalid Wasabi region [{$region}]. Expected something like [eu-central-1]."
            );
        }

        return "https://s3.{$region}.wasabisys.com";
    }

    public static function isKnownRegion(string $region): bool
    {
        return array_key_exists(strtolower(trim($region)), self::REGIONS);
    }
}
