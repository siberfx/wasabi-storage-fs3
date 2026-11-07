# Flysystem Wasabi

[![Packagist Version](https://img.shields.io/packagist/v/siberfx/flysystem-wasabi?style=flat-square&logo=packagist&logoColor=white&label=version)](https://packagist.org/packages/siberfx/flysystem-wasabi)
[![Tests](https://img.shields.io/github/actions/workflow/status/siberfx/flysystem-wasabi/tests.yml?branch=main&style=flat-square&logo=github&label=tests)](https://github.com/siberfx/flysystem-wasabi/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/siberfx/flysystem-wasabi?style=flat-square&label=downloads)](https://packagist.org/packages/siberfx/flysystem-wasabi/stats)
[![PHP](https://img.shields.io/packagist/dependency-v/siberfx/flysystem-wasabi/php?style=flat-square&logo=php&logoColor=white&label=php&color=777BB4)](https://packagist.org/packages/siberfx/flysystem-wasabi)
[![Laravel](https://img.shields.io/packagist/dependency-v/siberfx/flysystem-wasabi/illuminate%2Fsupport?style=flat-square&logo=laravel&logoColor=white&label=laravel&color=FF2D20)](https://laravel.com)
[![Flysystem](https://img.shields.io/packagist/dependency-v/siberfx/flysystem-wasabi/league%2Fflysystem?style=flat-square&label=flysystem&color=4F5B93)](https://flysystem.thephpleague.com)
[![Pest](https://img.shields.io/badge/tested_with-pest-F472B6?style=flat-square)](https://pestphp.com)
[![License](https://img.shields.io/packagist/l/siberfx/flysystem-wasabi?style=flat-square&label=license)](LICENSE.md)

A [Wasabi Hot Cloud Storage](https://wasabi.com/) filesystem driver for Laravel 12 and 13.

The `wasabi` driver is built on Laravel's own S3 driver (AWS SDK for PHP v3 + Flysystem v3), so a Wasabi disk is a regular
`Illuminate\Filesystem\AwsS3V3Adapter`. Everything the `Storage` facade offers for S3 works: `url()`, `temporaryUrl()`,
`temporaryUploadUrl()`, streams, visibility, directories, and `$request->file()->store()`. You only set the region and
the package works out the Wasabi endpoint.

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Wasabi account setup: policies & permissions](#wasabi-account-setup-policies--permissions)
- [Verifying your setup](#verifying-your-setup-php-artisan-wasabicheck)
- [Usage](#usage)
- [Frontend examples (Blade, Twig, Vue, React)](#frontend-examples)
- [Troubleshooting](#troubleshooting)
- [Testing](#testing)

## Requirements

| Package | Version                  |
|---------|--------------------------|
| PHP     | ^8.3                     |
| Laravel | 12.x, 13.x               |
| Storage | Flysystem v3, AWS SDK v3 |

## Installation

```bash
composer require siberfx/flysystem-wasabi
```

Laravel auto-discovers the service provider. You don't need to require `league/flysystem-aws-s3-v3` yourself because
this package depends on it.

## Configuration

Add a disk to `config/filesystems.php`:

```php
'disks' => [

    // ...

    'wasabi' => [
        'driver' => 'wasabi',
        'key' => env('WASABI_ACCESS_KEY_ID'),
        'secret' => env('WASABI_SECRET_ACCESS_KEY'),
        'region' => env('WASABI_DEFAULT_REGION', 'eu-central-1'),
        'bucket' => env('WASABI_BUCKET'),
        'root' => env('WASABI_ROOT'),                 // optional prefix, e.g. "production"
        'url' => env('WASABI_URL'),                   // optional CDN / custom domain for url()
        'endpoint' => env('WASABI_ENDPOINT'),         // optional, overrides the region endpoint
        'use_path_style_endpoint' => env('WASABI_USE_PATH_STYLE_ENDPOINT', false),
        'visibility' => 'private',
        'throw' => false,
        'report' => false,
    ],

],
```

Then set your `.env`:

```dotenv
WASABI_ACCESS_KEY_ID=your-access-key
WASABI_SECRET_ACCESS_KEY=your-secret-key
WASABI_DEFAULT_REGION=eu-central-1
WASABI_BUCKET=your-bucket-name

# Optional: make Wasabi the default disk
FILESYSTEM_DISK=wasabi
```

### Options

| Option                         | Default                            | Description |
|--------------------------------|------------------------------------|-------------|
| `key` / `secret`               | -                                  | Access key pair of a Wasabi sub-user (see [policies](#wasabi-account-setup-policies--permissions)). |
| `region`                       | `us-east-1`                        | The bucket's region. **It has to match the region the bucket was created in.** |
| `bucket`                       | - (required)                       | Bucket name. |
| `root`                         | `''`                               | Prefix applied to every path, which lets several apps or environments share one bucket. |
| `url`                          | Wasabi object URL                  | Base URL used by `url()`, e.g. a CDN in front of the bucket. |
| `endpoint`                     | `https://s3.{region}.wasabisys.com` | Override the service URL, e.g. a regional alias. |
| `use_path_style_endpoint`      | `false`                            | `true` gives `s3.region.wasabisys.com/bucket/key` instead of `bucket.s3.region.wasabisys.com/key`. **Required for bucket names that contain dots.** |
| `visibility`                   | `private`                          | Default visibility for new files. `public` sends a `public-read` ACL (see [public access](#4-public-files-public-access-is-disabled-by-default)). |
| `request_checksum_calculation` | `when_required`                    | AWS SDK checksum behaviour (see [checksums](#checksum--501-notimplemented-errors-on-upload)). |
| `response_checksum_validation` | `when_required`                    | Same as above, for downloads. |
| `throw`                        | `false`                            | Throw exceptions instead of returning `false` (Laravel default behaviour). |
| `options`                      | `[]`                               | Extra S3 options for every write, e.g. `['ServerSideEncryption' => 'AES256']`. |

Any other [S3Client option](https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.S3.S3Client.html#method___construct)
(`http`, `retries`, ...) is passed through to the AWS SDK.

### Regions

| Region           | Location             | Endpoint                          |
|------------------|----------------------|-----------------------------------|
| `us-east-1`      | N. Virginia          | `s3.us-east-1.wasabisys.com`      |
| `us-east-2`      | N. Virginia          | `s3.us-east-2.wasabisys.com`      |
| `us-central-1`   | Plano, Texas         | `s3.us-central-1.wasabisys.com`   |
| `us-west-1`      | Hillsboro, Oregon    | `s3.us-west-1.wasabisys.com`      |
| `us-west-2`      | San Jose, California | `s3.us-west-2.wasabisys.com`      |
| `ca-central-1`   | Toronto              | `s3.ca-central-1.wasabisys.com`   |
| `eu-central-1`   | Amsterdam            | `s3.eu-central-1.wasabisys.com`   |
| `eu-central-2`   | Frankfurt            | `s3.eu-central-2.wasabisys.com`   |
| `eu-west-1`      | London               | `s3.eu-west-1.wasabisys.com`      |
| `eu-west-2`      | Paris                | `s3.eu-west-2.wasabisys.com`      |
| `eu-west-3`      | London               | `s3.eu-west-3.wasabisys.com`      |
| `eu-south-1`     | Milan                | `s3.eu-south-1.wasabisys.com`     |
| `ap-northeast-1` | Tokyo                | `s3.ap-northeast-1.wasabisys.com` |
| `ap-northeast-2` | Osaka                | `s3.ap-northeast-2.wasabisys.com` |
| `ap-southeast-1` | Singapore            | `s3.ap-southeast-1.wasabisys.com` |
| `ap-southeast-2` | Sydney               | `s3.ap-southeast-2.wasabisys.com` |

The package also accepts new regions that follow the same naming pattern. See Wasabi's
[service URL list](https://docs.wasabi.com/docs/what-are-the-service-urls-for-wasabis-different-storage-regions) for the
current list.

> [!IMPORTANT]
> Wasabi requires the **region-specific** endpoint. With the wrong region, `GET` requests may still work but `PUT` and
> `DELETE` fail. Check your bucket's region in the Wasabi console under *Buckets*.

## Wasabi account setup: policies & permissions

Most "it doesn't work" reports come down to account setup rather than code. Do these steps once per bucket.

### 1. Create the bucket

In the [Wasabi console](https://console.wasabisys.com): **Buckets → Create Bucket**. Choose a globally unique name. If
you can, avoid dots in the name; otherwise set `use_path_style_endpoint` to `true`. Then pick the region and write it into
`WASABI_DEFAULT_REGION`.

Optional bucket settings and how they affect Laravel:

- **Versioning**: `delete()` adds a delete marker and keeps the old versions, and those versions count toward billed
  storage.
- **Object Lock**: locked objects can't be overwritten or deleted until the retention period ends, so Laravel's
  `put()`/`delete()` on them fail. Enable it only for audit or backup buckets.

### 2. Create a dedicated sub-user (don't use root keys)

Go to **Users → Create User**, tick **Programmatic (create API key)**, and skip the groups step. On the *Policies* step,
attach the policy from step 3. When you finish, the console shows the access key and secret **once**. Put them into
`WASABI_ACCESS_KEY_ID` and `WASABI_SECRET_ACCESS_KEY`.

### 3. Attach a policy that grants exactly what Laravel needs

Go to **Policies → Create Policy**, paste the JSON below, replace `your-bucket-name`, and attach the policy to the user
from step 2. Wasabi uses AWS IAM policy syntax, which is why the ARNs start with `arn:aws:s3`.

**Read/write access to one bucket (recommended for apps):**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "LaravelBucketLevel",
      "Effect": "Allow",
      "Action": [
        "s3:ListBucket",
        "s3:GetBucketLocation",
        "s3:ListBucketMultipartUploads"
      ],
      "Resource": "arn:aws:s3:::your-bucket-name"
    },
    {
      "Sid": "LaravelObjectLevel",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:GetObjectAcl",
        "s3:PutObjectAcl",
        "s3:AbortMultipartUpload",
        "s3:ListMultipartUploadParts"
      ],
      "Resource": "arn:aws:s3:::your-bucket-name/*"
    }
  ]
}
```

> [!NOTE]
> The bucket-level actions use the bucket ARN (`arn:aws:s3:::bucket`). The object-level actions use the objects ARN
> (`arn:aws:s3:::bucket/*`). If you mix them up, you get `AccessDenied` even though the action is listed.

**Limit the user to a prefix** (matches `root` in the disk config, here `production/`):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": "arn:aws:s3:::your-bucket-name",
      "Condition": { "StringLike": { "s3:prefix": ["production/*", "production"] } }
    },
    {
      "Effect": "Allow",
      "Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
      "Resource": "arn:aws:s3:::your-bucket-name"
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject", "s3:PutObject", "s3:DeleteObject",
        "s3:GetObjectAcl", "s3:PutObjectAcl",
        "s3:AbortMultipartUpload", "s3:ListMultipartUploadParts"
      ],
      "Resource": "arn:aws:s3:::your-bucket-name/production/*"
    }
  ]
}
```

**Read-only access** (e.g. a reporting app): keep `s3:ListBucket`, `s3:GetBucketLocation` and `s3:GetObject` only.

#### Which Laravel call needs which permission

| Laravel `Storage` method                                     | Required S3 action(s)                        |
|--------------------------------------------------------------|----------------------------------------------|
| `files()`, `allFiles()`, `directories()`, `deleteDirectory()` | `s3:ListBucket` (+ `s3:DeleteObject`)       |
| `exists()`, `size()`, `lastModified()`, `mimeType()`         | `s3:GetObject` (HEAD request)                |
| `get()`, `readStream()`, `download()`, `temporaryUrl()`      | `s3:GetObject`                               |
| `put()`, `putFile()`, `writeStream()`, `store()`, `temporaryUploadUrl()` | `s3:PutObject` (+ `s3:PutObjectAcl` if visibility is set) |
| `copy()`, `move()`                                           | `s3:GetObject`, `s3:PutObject` (+ `s3:DeleteObject` for move) |
| `delete()`                                                   | `s3:DeleteObject`                            |
| `getVisibility()` / `setVisibility()`                        | `s3:GetObjectAcl` / `s3:PutObjectAcl`        |
| Large uploads (multipart)                                    | `s3:PutObject`, `s3:AbortMultipartUpload`, `s3:ListMultipartUploadParts`, `s3:ListBucketMultipartUploads` |

A presigned URL (`temporaryUrl` / `temporaryUploadUrl`) has the permissions of the key that signed it. If the user
lacks `s3:GetObject`, the URL is generated fine but returns `403` when someone opens it.

### 4. Public files: public access is disabled by default

Wasabi buckets are **private**. Wasabi also restricts public access at the account level:

- **Accounts created after 13 March 2023** and **trial accounts** can't enable public access themselves, either in the
  console or through the API. Wasabi Support has to enable it for the account or bucket.
- Older paid accounts can turn on public access per bucket under **Bucket → Settings → Public Access**.

See Wasabi's notes on
[the change in public access](https://docs.wasabi.com/docs/change-in-ability-to-configure-public-access-to-objects-and-buckets)
and the [Public Access Override setting](https://docs.wasabi.com/docs/public-access-enabledisable).

What this means in Laravel:

- **Recommended:** keep the disk `private` and hand out short-lived links with `temporaryUrl()`. This works on every
  account with no extra setup.
- `Storage::disk('wasabi')->url($path)` builds the plain object URL. That URL only opens in a browser if the object is
  publicly readable, or if `url` points at a CDN or proxy that has access.
- `visibility => 'public'` or `setVisibility($path, 'public')` sends a `public-read` ACL. If public access isn't enabled
  for the account, the request fails or anonymous `GET`s still return `403`.

If Wasabi has enabled public access for you, a bucket policy that allows anonymous reads of one prefix looks like this
(**Bucket → Settings → Policies**):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadForPublicPrefix",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::your-bucket-name/public/*"
    }
  ]
}
```

### 5. CORS (browser uploads and fetches)

If the browser talks to Wasabi directly, the bucket needs a CORS configuration. That's the case for `temporaryUploadUrl()`
direct uploads, `fetch()` of `temporaryUrl()` links, and fonts or video served from the bucket. You can set it once
through the SDK client the disk exposes, for example from `php artisan tinker`:

```php
Storage::disk('wasabi')->getClient()->putBucketCors([
    'Bucket' => config('filesystems.disks.wasabi.bucket'),
    'CORSConfiguration' => [
        'CORSRules' => [[
            'AllowedOrigins' => ['https://your-app.com'],
            'AllowedMethods' => ['GET', 'PUT', 'HEAD'],
            'AllowedHeaders' => ['*'],
            'ExposeHeaders' => ['ETag'],
            'MaxAgeSeconds' => 3600,
        ]],
    ],
]);
```

The user that runs this needs `s3:PutBucketCORS` on the bucket ARN. Use an admin key for it rather than adding the
action to the app's policy. The AWS CLI works too:

```bash
aws s3api put-bucket-cors --bucket your-bucket-name \
  --cors-configuration file://cors.json \
  --endpoint-url https://s3.eu-central-1.wasabisys.com
```

### 6. Billing policies worth knowing

These aren't permissions, but they affect how you should use the disk:

- **Minimum storage duration.** Wasabi bills each object for a minimum period (90 days on pay-as-you-go at the time of
  writing). Deleting or overwriting a file earlier is still billed for the full period. Avoid using Wasabi for
  short-lived temp files; keep those on the `local` disk.
- **Egress and API requests** are free under Wasabi's fair-use policy, which expects your monthly egress to stay at or
  below the amount of data you store.

Check [Wasabi's pricing FAQ](https://wasabi.com/pricing/faq) for the terms that apply to your account.

## Verifying your setup: `php artisan wasabi:check`

The package ships a command that exercises the disk end to end and shows the real S3 error code for anything that fails:

```bash
php artisan wasabi:check            # checks the "wasabi" disk
php artisan wasabi:check backups    # checks another disk
php artisan wasabi:check --public   # also tests public-read ACL + anonymous access
```

```
  Endpoint ................................ https://s3.eu-central-1.wasabisys.com
  Region .................................................... eu-central-1
  Bucket ................................................. your-bucket-name
  Root ............................................................... /

  s3:ListBucket .......................................... 120ms DONE
  s3:PutObject ........................................... 210ms DONE
  s3:GetObject (head) ..................................... 95ms DONE
  s3:GetObject (read) ..................................... 98ms DONE
  Presigned URL (temporaryUrl) ........................... 130ms DONE
  s3:DeleteObject ......................................... 90ms DONE

  INFO  Disk [wasabi] is fully working.
```

It writes and then deletes one small file under `.wasabi-check/`.

## Usage

```php
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('wasabi');

// Write
$disk->put('avatars/1.jpg', $contents);
$disk->put('reports/q1.pdf', $contents, 'private');
$disk->writeStream('backups/db.sql.gz', fopen($localPath, 'r'));   // large files: streams + multipart

// Uploaded files
$path = $request->file('avatar')->store('avatars', 'wasabi');
$path = $request->file('avatar')->storeAs('avatars', "{$user->id}.jpg", 'wasabi');

// Read
$contents = $disk->get('avatars/1.jpg');
$stream = $disk->readStream('backups/db.sql.gz');
return $disk->download('reports/q1.pdf', 'Q1 report.pdf');

// Inspect
$disk->exists('avatars/1.jpg');
$disk->size('avatars/1.jpg');
$disk->lastModified('avatars/1.jpg');
$disk->mimeType('avatars/1.jpg');
$disk->files('avatars');
$disk->allFiles();

// Manage
$disk->copy('avatars/1.jpg', 'avatars/archive/1.jpg');
$disk->move('tmp/upload.jpg', 'avatars/2.jpg');
$disk->delete(['avatars/1.jpg', 'avatars/2.jpg']);
$disk->deleteDirectory('tmp');
```

### Private files: presigned URLs (recommended)

```php
// Time-limited download link, works without public access.
$url = Storage::disk('wasabi')->temporaryUrl('reports/q1.pdf', now()->addMinutes(15));

// Force a download filename / content type.
$url = Storage::disk('wasabi')->temporaryUrl('reports/q1.pdf', now()->addMinutes(15), [
    'ResponseContentDisposition' => 'attachment; filename="Q1 report.pdf"',
    'ResponseContentType' => 'application/pdf',
]);

// Direct browser-to-Wasabi upload (needs CORS, see above).
['url' => $url, 'headers' => $headers] = Storage::disk('wasabi')
    ->temporaryUploadUrl('uploads/video.mp4', now()->addMinutes(10));
```

### Public URLs

```php
$url = Storage::disk('wasabi')->url('public/logo.png');
// https://your-bucket-name.s3.eu-central-1.wasabisys.com/public/logo.png
// or https://cdn.your-app.com/public/logo.png when "url" is set
```

### Multiple buckets / on-demand disks

```php
// config/filesystems.php: as many disks as you need
'wasabi-backups' => [
    'driver' => 'wasabi',
    'key' => env('WASABI_ACCESS_KEY_ID'),
    'secret' => env('WASABI_SECRET_ACCESS_KEY'),
    'region' => 'eu-central-2',
    'bucket' => 'my-backups',
],

// or build one at runtime (e.g. per tenant)
$disk = Storage::build([
    'driver' => 'wasabi',
    'key' => $tenant->wasabi_key,
    'secret' => $tenant->wasabi_secret,
    'region' => $tenant->wasabi_region,
    'bucket' => $tenant->wasabi_bucket,
]);
```

### Raw AWS SDK access

For things the `Storage` API doesn't cover (bucket lifecycle rules, object tags, CORS, ...), use the underlying
`Aws\S3\S3Client`, which is already configured for Wasabi:

```php
$client = Storage::disk('wasabi')->getClient();

$client->putObjectTagging([
    'Bucket' => config('filesystems.disks.wasabi.bucket'),
    'Key' => 'reports/q1.pdf',
    'Tagging' => ['TagSet' => [['Key' => 'department', 'Value' => 'finance']]],
]);
```

### Testing your app

Laravel's fake works with any disk name:

```php
Storage::fake('wasabi');

$this->post('/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')]);

Storage::disk('wasabi')->assertExists('avatars/me.jpg');
```

## Frontend examples

The [`examples/`](examples) folder has complete, copy-paste implementations. All of them share one controller and keep
files private, showing them through `temporaryUrl()` links:

| Example | Files | Shows |
|---------|-------|-------|
| **Laravel backend** | [`examples/laravel/WasabiFileController.php`](examples/laravel/WasabiFileController.php), [`routes.php`](examples/laravel/routes.php) | Form upload, presigned direct upload (presign + confirm), list, download, delete, per-user key prefix |
| **Blade** | [`examples/blade/index.blade.php`](examples/blade/index.blade.php) | Upload form, direct upload with progress, file list with image previews |
| **Twig** | [`examples/twig/index.twig`](examples/twig/index.twig) | Same page for Laravel + [TwigBridge](https://github.com/rcrowe/TwigBridge) |
| **Vue 3** | [`examples/vue/WasabiUploader.vue`](examples/vue/WasabiUploader.vue) | Drag & drop, parallel uploads with progress, file list, delete |
| **React** | [`examples/react/WasabiUploader.jsx`](examples/react/WasabiUploader.jsx) | Same component for React 18/19 |
| **JS helper** | [`examples/js/wasabiUpload.js`](examples/js/wasabiUpload.js) | Dependency-free `uploadToWasabi(file, { onProgress })` used by Vue and React |

See [`examples/README.md`](examples/README.md) for the upload flow diagram and setup notes. Direct browser uploads need
the bucket [CORS rule](#5-cors-browser-uploads-and-fetches).

## Troubleshooting

#### `SignatureDoesNotMatch` / `AuthorizationHeaderMalformed`
The key or secret is wrong, or `region` doesn't match the bucket's region. Copy the region from the bucket list in the
console.

#### `AccessDenied` (403)
The user's policy is missing an action, or bucket and object ARNs are mixed up (see the
[permission table](#which-laravel-call-needs-which-permission)). `php artisan wasabi:check` shows which call fails.

#### `url()` returns 403 in the browser
The object isn't publicly readable, which is the default on Wasabi (see [public access](#4-public-files-public-access-is-disabled-by-default)).
Use `temporaryUrl()` instead.

#### SSL error with a bucket name containing dots
Set `'use_path_style_endpoint' => true`.

#### Checksum / `501 NotImplemented` errors on upload
AWS SDK for PHP 3.337+ adds CRC32 checksums to uploads by default, and some S3-compatible services reject them. This
package sets `request_checksum_calculation` and `response_checksum_validation` to `when_required`, which sends checksums
only when an operation needs them. You can set either option to `when_supported` in the disk config if you want them
everywhere.

#### `put()` returns `false` with no error
Laravel disks swallow exceptions by default. Set `'throw' => true` on the disk, or run `php artisan wasabi:check`, to
see the real error.

## Testing

```bash
composer test                # unit + feature tests (no network)
composer test:integration    # runs against a real bucket
```

The integration suite is skipped unless credentials are set:

```bash
WASABI_ACCESS_KEY_ID=... WASABI_SECRET_ACCESS_KEY=... \
WASABI_DEFAULT_REGION=eu-central-1 WASABI_BUCKET=... composer test:integration
```

It writes under a random `flysystem-wasabi-tests/<uuid>/` prefix and removes everything it wrote afterwards.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
