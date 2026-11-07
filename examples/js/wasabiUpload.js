// resources/js/wasabiUpload.js
// Shared by the Vue and React examples. Framework-agnostic, no dependencies.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.message ?? `HTTP ${response.status}`);
    }

    return response.json();
}

/**
 * Upload a File straight to Wasabi:
 *   1. POST /wasabi/presign  -> Laravel returns a presigned PUT url
 *   2. PUT file to Wasabi    -> no PHP upload limits, progress events
 *   3. POST /wasabi/confirm  -> Laravel verifies and returns { key, name, size, url }
 *
 * @param {File} file
 * @param {{ presignUrl?: string, confirmUrl?: string, onProgress?: (percent: number) => void, signal?: AbortSignal }} options
 */
export async function uploadToWasabi(file, options = {}) {
    const { presignUrl = '/wasabi/presign', confirmUrl = '/wasabi/confirm', onProgress, signal } = options;
    const type = file.type || 'application/octet-stream';

    const { key, url, headers } = await postJson(presignUrl, { name: file.name, type, size: file.size });

    await new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url);
        Object.entries(headers ?? {}).forEach(([name, value]) => xhr.setRequestHeader(name, value));
        xhr.setRequestHeader('Content-Type', type); // stored as the object's Content-Type

        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress?.(Math.round((e.loaded / e.total) * 100));
        xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`Wasabi: HTTP ${xhr.status}`)));
        xhr.onerror = () => reject(new Error('Network/CORS error. Check the bucket CORS configuration.'));
        xhr.onabort = () => reject(new DOMException('Upload aborted', 'AbortError'));
        signal?.addEventListener('abort', () => xhr.abort());

        xhr.send(file);
    });

    return postJson(confirmUrl, { key });
}

/** GET /wasabi/files -> [{ key, name, size, url }] */
export async function listWasabiFiles(listUrl = '/wasabi/files') {
    const response = await fetch(listUrl, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    return (await response.json()).data;
}

export async function deleteWasabiFile(key, baseUrl = '/wasabi/files') {
    const response = await fetch(`${baseUrl}/${key}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
}
