<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemS3Adapter;
use League\Flysystem\Filesystem;

it('registers the wasabi driver as a laravel s3 disk on flysystem v3', function () {
    $disk = Storage::disk('wasabi');

    expect($disk)->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and($disk->getDriver())->toBeInstanceOf(Filesystem::class)
        ->and($disk->getAdapter())->toBeInstanceOf(FlysystemS3Adapter::class)
        ->and($disk->getClient())->toBeInstanceOf(S3Client::class);
});

it('points the s3 client at the regional wasabi endpoint', function () {
    $client = Storage::disk('wasabi')->getClient();

    expect((string) $client->getEndpoint())->toBe('https://s3.eu-central-1.wasabisys.com')
        ->and($client->getRegion())->toBe('eu-central-1');
});

it('generates virtual-hosted object urls', function () {
    expect(Storage::disk('wasabi')->url('avatars/1.jpg'))
        ->toBe('https://my-bucket.s3.eu-central-1.wasabisys.com/avatars/1.jpg');
});

it('generates path-style urls when configured', function () {
    config()->set('filesystems.disks.wasabi.bucket', 'my.dotted.bucket');
    config()->set('filesystems.disks.wasabi.use_path_style_endpoint', true);

    expect(Storage::disk('wasabi')->url('a.txt'))
        ->toBe('https://s3.eu-central-1.wasabisys.com/my.dotted.bucket/a.txt');
});

it('prefixes the root and honours a custom public url', function () {
    config()->set('filesystems.disks.wasabi.root', 'tenant-1');
    config()->set('filesystems.disks.wasabi.url', 'https://cdn.example.com');

    expect(Storage::disk('wasabi')->url('a.txt'))->toBe('https://cdn.example.com/tenant-1/a.txt');
});

it('signs temporary download urls offline', function () {
    $url = Storage::disk('wasabi')->temporaryUrl('reports/q1.pdf', now()->addMinutes(10));

    expect($url)
        ->toStartWith('https://my-bucket.s3.eu-central-1.wasabisys.com/reports/q1.pdf?')
        ->toContain('X-Amz-Algorithm=AWS4-HMAC-SHA256')
        ->toContain('X-Amz-Credential=test-key%2F')
        ->toContain('%2Feu-central-1%2Fs3%2Faws4_request')
        ->toContain('X-Amz-Expires=600');
});

it('signs temporary upload urls offline', function () {
    ['url' => $url, 'headers' => $headers] = Storage::disk('wasabi')
        ->temporaryUploadUrl('uploads/file.bin', now()->addMinutes(5));

    expect($url)->toStartWith('https://my-bucket.s3.eu-central-1.wasabisys.com/uploads/file.bin?')
        ->and($url)->toContain('X-Amz-Signature=')
        ->and($headers)->toBeArray();
});

it('can be built on demand', function () {
    $disk = Storage::build([
        'driver' => 'wasabi',
        'key' => 'k',
        'secret' => 's',
        'region' => 'ap-northeast-1',
        'bucket' => 'jp-bucket',
    ]);

    expect((string) $disk->getClient()->getEndpoint())->toBe('https://s3.ap-northeast-1.wasabisys.com');
});

it('registers the wasabi:check command', function () {
    $this->artisan('list')->expectsOutputToContain('wasabi:check');
});

it('fails the check command for a missing disk', function () {
    $this->artisan('wasabi:check', ['disk' => 'nope'])->assertFailed();
});
