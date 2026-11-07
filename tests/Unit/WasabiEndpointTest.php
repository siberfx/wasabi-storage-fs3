<?php

declare(strict_types=1);

use Siberfx\WasabiStorage\WasabiEndpoint;

it('builds the regional service url', function (string $region, string $expected) {
    expect(WasabiEndpoint::forRegion($region))->toBe($expected);
})->with([
    ['us-east-1', 'https://s3.us-east-1.wasabisys.com'],
    ['eu-central-1', 'https://s3.eu-central-1.wasabisys.com'],
    ['ap-southeast-2', 'https://s3.ap-southeast-2.wasabisys.com'],
    [' EU-WEST-2 ', 'https://s3.eu-west-2.wasabisys.com'],
]);

it('accepts unknown but well formed regions', function () {
    expect(WasabiEndpoint::forRegion('sa-east-9'))->toBe('https://s3.sa-east-9.wasabisys.com')
        ->and(WasabiEndpoint::isKnownRegion('sa-east-9'))->toBeFalse()
        ->and(WasabiEndpoint::isKnownRegion('eu-central-2'))->toBeTrue();
});

it('rejects malformed regions', function (string $region) {
    WasabiEndpoint::forRegion($region);
})->throws(InvalidArgumentException::class)->with(['', 'frankfurt', 'eu.central.1', 'evil.com/x-1']);
