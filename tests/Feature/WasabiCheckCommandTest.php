<?php

declare(strict_types=1);

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;

/**
 * A tiny in-memory "bucket" served through the AWS SDK mock handler, so the
 * command runs its real S3 calls without the network.
 */
function fakeWasabiBucket(bool $denyWrites = false): MockHandler
{
    $objects = [];
    $handler = new MockHandler;

    $respond = function (CommandInterface $command) use (&$objects, $denyWrites, &$respond, $handler) {
        $handler->append($respond); // keep answering

        $key = $command['Key'] ?? null;

        return match ($command->getName()) {
            'ListObjectsV2' => new Result(['Contents' => []]),
            'PutObject' => $denyWrites
                ? new AwsException('Access Denied', $command, [
                    'code' => 'AccessDenied',
                    'message' => 'Access Denied',
                    'response' => new Response(403),
                ])
                : (function () use (&$objects, $key, $command) {
                    $objects[$key] = (string) $command['Body'];

                    return new Result([]);
                })(),
            'HeadObject' => new Result(['ContentLength' => strlen($objects[$key] ?? '')]),
            'GetObject' => new Result(['Body' => Utils::streamFor($objects[$key] ?? '')]),
            'DeleteObject' => (function () use (&$objects, $key) {
                unset($objects[$key]);

                return new Result([]);
            })(),
            default => new Result([]),
        };
    };

    $handler->append($respond);

    Http::fake(function ($request) use (&$objects) {
        $key = ltrim(parse_url($request->url(), PHP_URL_PATH), '/');

        return isset($objects[$key]) ? Http::response($objects[$key]) : Http::response('', 404);
    });

    return $handler;
}

it('passes against a correctly permissioned bucket', function () {
    config()->set('filesystems.disks.wasabi.handler', fakeWasabiBucket());

    $this->artisan('wasabi:check')
        ->expectsOutputToContain('https://s3.eu-central-1.wasabisys.com')
        ->expectsOutputToContain('is fully working')
        ->assertSuccessful();
});

it('reports the s3 error code when a permission is missing', function () {
    config()->set('filesystems.disks.wasabi.handler', fakeWasabiBucket(denyWrites: true));

    $this->artisan('wasabi:check')
        ->expectsOutputToContain('AccessDenied (HTTP 403)')
        ->assertFailed();
});
