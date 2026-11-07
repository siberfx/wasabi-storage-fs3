{{-- resources/views/wasabi/index.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Wasabi files</title>
</head>
<body>
    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    {{-- 1. Classic upload: browser -> Laravel -> Wasabi --}}
    <h2>Upload through Laravel</h2>
    <form method="POST" action="{{ route('wasabi.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" required>
        <button type="submit">Upload</button>
        @error('file') <p>{{ $message }}</p> @enderror
    </form>

    {{-- 2. Direct upload: browser -> Wasabi with a presigned URL (needs bucket CORS) --}}
    <h2>Direct upload to Wasabi</h2>
    <input type="file" id="direct-file">
    <progress id="direct-progress" value="0" max="100" hidden></progress>
    <p id="direct-status"></p>

    <h2>Your files</h2>
    <ul>
        @forelse ($files as $file)
            <li>
                {{-- Presigned link: valid 15 minutes, works on private buckets --}}
                <a href="{{ $file['url'] }}" target="_blank" rel="noopener">{{ $file['name'] }}</a>
                ({{ Illuminate\Support\Number::fileSize($file['size']) }})

                @if (Str::endsWith(strtolower($file['name']), ['.jpg', '.jpeg', '.png', '.webp', '.gif']))
                    <img src="{{ $file['url'] }}" alt="" width="80" loading="lazy">
                @endif

                <a href="{{ route('wasabi.download', $file['key']) }}">Download</a>

                <form method="POST" action="{{ route('wasabi.destroy', $file['key']) }}" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Delete</button>
                </form>
            </li>
        @empty
            <li>No files yet.</li>
        @endforelse
    </ul>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const post = (url, body) => fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        }).then(r => r.ok ? r.json() : Promise.reject(r));

        document.getElementById('direct-file').addEventListener('change', async (event) => {
            const file = event.target.files[0];
            if (!file) return;

            const status = document.getElementById('direct-status');
            const progress = document.getElementById('direct-progress');
            const type = file.type || 'application/octet-stream';

            try {
                // 1. Ask Laravel for a presigned PUT URL.
                const { key, url, headers } = await post(@js(route('wasabi.presign')), { name: file.name, type, size: file.size });

                // 2. PUT the file straight to Wasabi (XHR gives upload progress).
                progress.hidden = false;
                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', url);
                    Object.entries(headers).forEach(([name, value]) => xhr.setRequestHeader(name, value));
                    xhr.setRequestHeader('Content-Type', type);
                    xhr.upload.onprogress = (e) => { if (e.lengthComputable) progress.value = (e.loaded / e.total) * 100; };
                    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`Wasabi: HTTP ${xhr.status}`)));
                    xhr.onerror = () => reject(new Error('Network/CORS error. Check the bucket CORS configuration.'));
                    xhr.send(file);
                });

                // 3. Tell Laravel the upload finished.
                await post(@js(route('wasabi.confirm')), { key });
                window.location.reload();
            } catch (error) {
                status.textContent = error.message ?? 'Upload failed.';
            }
        });
    </script>
</body>
</html>
