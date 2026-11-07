<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backend for every frontend example (Blade, Twig, Vue, React).
 *
 * Two upload strategies are shown:
 *  1. Classic form upload: browser -> Laravel -> Wasabi (store()).
 *  2. Direct upload: Laravel signs a URL, browser PUTs straight to Wasabi.
 *     Large files never pass through PHP. Needs bucket CORS (see README).
 *
 * Files stay private; they are shown with short-lived presigned URLs.
 */
class WasabiFileController extends Controller
{
    private const int MAX_KB = 51200; // 50 MB

    private const array MIMES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'mp4', 'zip'];

    /** Blade / Twig page: list the user's files with temporary links. */
    public function index(Request $request): View
    {
        return view('wasabi.index', ['files' => $this->files($request)]);
    }

    /** JSON list for Vue / React. */
    public function list(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->files($request)]);
    }

    /** 1. Classic multipart form upload, stored through the wasabi disk. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES)],
        ]);

        $path = $request->file('file')->store($this->prefix($request), 'wasabi');

        return $request->expectsJson()
            ? response()->json(['path' => $path], 201)
            : back()->with('status', "Uploaded {$path}");
    }

    /** 2a. Direct upload: hand the browser a presigned PUT URL. */
    public function presign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1', 'max:'.(self::MAX_KB * 1024)],
        ]);

        $extension = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        abort_unless(in_array($extension, self::MIMES, true), 422, 'File type not allowed.');

        // The server picks the key, never the browser.
        $key = $this->prefix($request).'/'.Str::uuid().'.'.$extension;

        ['url' => $url, 'headers' => $headers] = Storage::disk('wasabi')->temporaryUploadUrl(
            $key,
            now()->addMinutes(10),
            ['ContentType' => $data['type']],
        );

        return response()->json([
            'key' => $key,
            'url' => $url,
            // Browsers set Host themselves; PSR-7 header values are arrays.
            'headers' => collect($headers)
                ->except(['Host', 'host'])
                ->map(fn ($value) => is_array($value) ? implode(', ', $value) : $value),
        ]);
    }

    /** 2b. Direct upload finished: verify the object really exists. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);

        // Only accept keys inside this user's prefix.
        abort_unless(Str::startsWith($data['key'], $this->prefix($request).'/'), 403);
        abort_unless(Storage::disk('wasabi')->exists($data['key']), 404, 'Upload not found.');

        // Persist $data['key'] on your model here, e.g. $request->user()->update(['avatar' => $data['key']]).

        return response()->json($this->present($data['key']), 201);
    }

    /** Download through Laravel (streams, works without public access). */
    public function download(Request $request, string $key): StreamedResponse
    {
        $key = $this->ownedKey($request, $key);

        return Storage::disk('wasabi')->download($key, basename($key));
    }

    public function destroy(Request $request, string $key): RedirectResponse|JsonResponse
    {
        Storage::disk('wasabi')->delete($this->ownedKey($request, $key));

        return $request->expectsJson() ? response()->json(status: 204) : back()->with('status', 'Deleted.');
    }

    /** @return list<array{key: string, name: string, size: int, url: string}> */
    private function files(Request $request): array
    {
        return collect(Storage::disk('wasabi')->files($this->prefix($request)))
            ->map(fn (string $key) => $this->present($key))
            ->values()
            ->all();
    }

    /** @return array{key: string, name: string, size: int, url: string} */
    private function present(string $key): array
    {
        $disk = Storage::disk('wasabi');

        return [
            'key' => $key,
            'name' => basename($key),
            'size' => $disk->size($key),
            'url' => $disk->temporaryUrl($key, now()->addMinutes(15)),
        ];
    }

    private function prefix(Request $request): string
    {
        return 'uploads/'.$request->user()->getKey();
    }

    private function ownedKey(Request $request, string $key): string
    {
        abort_if(str_contains($key, '..'), 400);
        abort_unless(Str::startsWith($key, $this->prefix($request).'/'), 403);

        return $key;
    }
}
