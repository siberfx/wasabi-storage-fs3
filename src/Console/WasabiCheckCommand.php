<?php

declare(strict_types=1);

namespace Siberfx\WasabiStorage\Console;

use Aws\Exception\AwsException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WasabiCheckCommand extends Command
{
    protected $signature = 'wasabi:check
        {disk=wasabi : The filesystem disk to check}
        {--public : Also test public-read visibility and anonymous access}';

    protected $description = 'Verify credentials, bucket policy and permissions of a Wasabi disk';

    private bool $failed = false;

    public function handle(): int
    {
        $diskName = (string) $this->argument('disk');
        $diskConfig = config("filesystems.disks.{$diskName}");

        if (! is_array($diskConfig)) {
            $this->components->error("Disk [{$diskName}] is not configured in config/filesystems.php.");

            return self::FAILURE;
        }

        // Build an on-demand copy that throws, so the real S3 error
        // (AccessDenied, SignatureDoesNotMatch, ...) is shown instead of `false`.
        $disk = Storage::build(['throw' => true] + $diskConfig);

        if (! $disk instanceof AwsS3V3Adapter) {
            $this->components->error("Disk [{$diskName}] is not an S3/Wasabi disk.");

            return self::FAILURE;
        }

        $config = $disk->getConfig();
        $this->components->twoColumnDetail('Endpoint', (string) ($config['endpoint'] ?? '-'));
        $this->components->twoColumnDetail('Region', (string) ($config['region'] ?? '-'));
        $this->components->twoColumnDetail('Bucket', (string) ($config['bucket'] ?? '-'));
        $this->components->twoColumnDetail('Root', ($config['root'] ?? '') ?: '/');
        $this->newLine();

        $path = '.wasabi-check/'.Str::uuid().'.txt';
        $contents = 'wasabi-check '.now()->toIso8601String();

        $this->step('s3:ListBucket', fn () => $disk->files('.wasabi-check'));
        $this->step('s3:PutObject', fn () => $this->assert($disk->put($path, $contents), 'put() returned false'));
        $this->step('s3:GetObject (head)', fn () => $this->assert($disk->exists($path), 'object not found after upload'));
        $this->step('s3:GetObject (read)', fn () => $this->assert($disk->get($path) === $contents, 'contents mismatch'));
        $this->step('Presigned URL (temporaryUrl)', function () use ($disk, $path, $contents) {
            $url = $disk->temporaryUrl($path, now()->addMinutes(5));
            $response = Http::timeout(15)->get($url);
            $this->assert($response->ok() && $response->body() === $contents, "HTTP {$response->status()}");
        });

        if ($this->option('public')) {
            $this->step('s3:PutObjectAcl (public-read)', fn () => $this->assert(
                $disk->setVisibility($path, 'public'), 'setVisibility() returned false'
            ));
            $this->step('Anonymous GET of url()', function () use ($disk, $path) {
                $response = Http::timeout(15)->get($disk->url($path));
                $this->assert($response->ok(), "HTTP {$response->status()} - public access is probably disabled for this bucket/account");
            });
        }

        $this->step('s3:DeleteObject', fn () => $this->assert($disk->delete($path), 'delete() returned false'));

        $this->newLine();

        if ($this->failed) {
            $this->components->error('Some checks failed. See the "Policies & permissions" section of the README.');

            return self::FAILURE;
        }

        $this->components->info("Disk [{$diskName}] is fully working.");

        return self::SUCCESS;
    }

    private function step(string $label, callable $callback): void
    {
        $error = null;

        $this->components->task($label, function () use ($callback, &$error) {
            try {
                $callback();

                return true;
            } catch (Throwable $e) {
                $error = $this->describe($e);

                return false;
            }
        });

        if ($error !== null) {
            $this->failed = true;
            $this->components->bulletList([Str::limit($error, 300)]);
        }
    }

    private function describe(Throwable $e): string
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof AwsException && $current->getAwsErrorCode()) {
                return trim(sprintf(
                    '%s (HTTP %s): %s',
                    $current->getAwsErrorCode(),
                    $current->getStatusCode() ?? '?',
                    $current->getAwsErrorMessage() ?? '',
                ));
            }
        }

        return $e->getMessage();
    }

    private function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException($message);
        }
    }
}
