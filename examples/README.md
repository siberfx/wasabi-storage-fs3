# Examples

These are full upload, list, download and delete examples for a `wasabi` disk, with one shared Laravel backend and four
frontends. Copy what you need into your app. The files live in the GitHub repository and are not shipped in the
Composer package.

| Folder                  | What it is                                                     | Copy to |
|-------------------------|----------------------------------------------------------------|---------|
| [`laravel/`](laravel)   | Controller + routes used by every frontend                     | `app/Http/Controllers/`, `routes/web.php` |
| [`blade/`](blade)       | Blade page: form upload, direct upload, file list              | `resources/views/wasabi/index.blade.php` |
| [`twig/`](twig)         | Same page in Twig (Laravel + [TwigBridge](https://github.com/rcrowe/TwigBridge)) | `resources/views/wasabi/index.twig` |
| [`js/`](js)             | Framework-agnostic upload helper used by Vue and React         | `resources/js/wasabiUpload.js` |
| [`vue/`](vue)           | Vue 3 drag & drop uploader with progress + file list           | `resources/js/components/WasabiUploader.vue` |
| [`react/`](react)       | React 18/19 drag & drop uploader with progress + file list     | `resources/js/components/WasabiUploader.jsx` |

## The two upload strategies

**1. Through Laravel (classic form).** The browser posts a `multipart/form-data` form, and Laravel stores the file with
`$request->file('file')->store($dir, 'wasabi')`. This is the simplest option and needs no CORS, but files pass
through PHP, so `upload_max_filesize`, `post_max_size` and request timeouts apply.

**2. Directly to Wasabi (presigned URL).** Recommended for large files and SPAs.

```
Browser                     Laravel                               Wasabi
   |  POST /wasabi/presign     |                                     |
   |-------------------------->|  temporaryUploadUrl($key, 10 min)   |
   |   { key, url, headers }   |  (offline signature, no request)    |
   |<--------------------------|                                     |
   |  PUT url  (file body, progress events)                          |
   |---------------------------------------------------------------->|
   |  200 OK                                                         |
   |<----------------------------------------------------------------|
   |  POST /wasabi/confirm {key}                                     |
   |-------------------------->|  exists($key) -> save on your model |
   |   { key, name, size, url }|------------------------------------>|
   |<--------------------------|                                     |
```

The server always chooses the object key under the user's own prefix (`uploads/{user_id}/`), and `confirm`/`destroy`
reject keys outside that prefix.

## Requirements

1. A working `wasabi` disk (see the main [README](../README.md#configuration)) and the
   [policy](../README.md#3-attach-a-policy-that-grants-exactly-what-laravel-needs) on your access key.
2. Direct uploads and in-browser fetches need a bucket CORS rule that allows your app's origin with `PUT`, `GET` and
   `HEAD`. See [CORS](../README.md#5-cors-browser-uploads-and-fetches).
3. The routes sit behind the `auth` middleware, and the layout has `<meta name="csrf-token" content="{{ csrf_token() }}">`.

Every displayed link is a `temporaryUrl()` that is valid for 15 minutes, so the examples also work on accounts where
Wasabi public access is disabled.

## Using the components

```js
// Vue (resources/js/app.js)
import { createApp } from 'vue';
import WasabiUploader from './components/WasabiUploader.vue';

createApp({}).component('WasabiUploader', WasabiUploader).mount('#app');
```

```blade
<div id="app"><wasabi-uploader accept="image/*,application/pdf"></wasabi-uploader></div>
```

```jsx
// React (resources/js/app.jsx)
import { createRoot } from 'react-dom/client';
import WasabiUploader from './components/WasabiUploader';

createRoot(document.getElementById('app')).render(
    <WasabiUploader accept="image/*,application/pdf" onUploaded={(file) => console.log(file.key)} />,
);
```

Both components work as Inertia page children too. Only the endpoints in `wasabiUpload.js` need to match your
routes; you can pass `presignUrl` and `confirmUrl` to `uploadToWasabi()`.
