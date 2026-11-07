<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Runs against a real Wasabi bucket. Skipped unless credentials are present:
 *
 *   WASABI_ACCESS_KEY_ID=... WASABI_SECRET_ACCESS_KEY=... \
 *   WASABI_DEFAULT_REGION=eu-central-1 WASABI_BUCKET=... composer test:integration
 */

beforeEach(function () {
    if (! env('WASABI_ACCESS_KEY_ID') || ! env('WASABI_BUCKET')) {
        $this->markTestSkipped('Wasabi credentials not configured.');
    }

    config()->set('filesystems.disks.wasabi.root', 'flysystem-wasabi-tests/'.Str::uuid());
    config()->set('filesystems.disks.wasabi.throw', true);
});

afterEach(function () {
    if (env('WASABI_ACCESS_KEY_ID') && env('WASABI_BUCKET')) {
        Storage::disk('wasabi')->deleteDirectory('');
    }
});

it('performs a full read/write lifecycle', function () {
    $disk = Storage::disk('wasabi');

    $disk->put('a/hello.txt', 'hello wasabi');

    expect($disk->exists('a/hello.txt'))->toBeTrue()
        ->and($disk->get('a/hello.txt'))->toBe('hello wasabi')
        ->and($disk->size('a/hello.txt'))->toBe(12)
        ->and($disk->mimeType('a/hello.txt'))->toBe('text/plain')
        ->and($disk->files('a'))->toBe(['a/hello.txt']);

    $disk->copy('a/hello.txt', 'b/copy.txt');
    $disk->move('b/copy.txt', 'c/moved.txt');

    expect($disk->exists('b/copy.txt'))->toBeFalse()
        ->and($disk->get('c/moved.txt'))->toBe('hello wasabi');

    $disk->writeStream('d/stream.txt', fopen('data://text/plain,streamed', 'r'));
    expect(stream_get_contents($disk->readStream('d/stream.txt')))->toBe('streamed');

    $disk->delete(['a/hello.txt', 'c/moved.txt', 'd/stream.txt']);
    expect($disk->allFiles())->toBe([]);
})->group('integration');

it('serves objects through presigned urls', function () {
    $disk = Storage::disk('wasabi');
    $disk->put('signed.txt', 'signed');

    $response = Http::get($disk->temporaryUrl('signed.txt', now()->addMinutes(5)));

    expect($response->ok())->toBeTrue()->and($response->body())->toBe('signed');
})->group('integration');

it('accepts uploads through presigned upload urls', function () {
    $disk = Storage::disk('wasabi');

    ['url' => $url, 'headers' => $headers] = $disk->temporaryUploadUrl('presigned-upload.txt', now()->addMinutes(5));

    Http::withHeaders($headers)->withBody('uploaded', 'text/plain')->put($url)->throw();

    expect($disk->get('presigned-upload.txt'))->toBe('uploaded');
})->group('integration');
