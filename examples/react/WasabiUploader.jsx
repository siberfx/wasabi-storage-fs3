// resources/js/components/WasabiUploader.jsx  (React 18/19)
//
// Works in a plain Vite + React app or with Inertia. Needs a
// <meta name="csrf-token" content="{{ csrf_token() }}"> tag in the layout.
//
// Usage:  <WasabiUploader accept="image/*,application/pdf" onUploaded={(file) => console.log(file)} />

import { useCallback, useEffect, useState } from 'react';
import { deleteWasabiFile, listWasabiFiles, uploadToWasabi } from '../wasabiUpload';

const isImage = (name) => /\.(jpe?g|png|webp|gif)$/i.test(name);
const formatSize = (bytes) => (bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

export default function WasabiUploader({ accept = '', onUploaded }) {
    const [files, setFiles] = useState([]);
    const [uploads, setUploads] = useState([]); // { id, name, progress, error }
    const [loading, setLoading] = useState(true);
    const [dragging, setDragging] = useState(false);

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            setFiles(await listWasabiFiles());
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        refresh();
    }, [refresh]);

    const patchUpload = (id, patch) => setUploads((list) => list.map((u) => (u.id === id ? { ...u, ...patch } : u)));

    const upload = async (fileList) => {
        await Promise.all([...fileList].map(async (file) => {
            const id = crypto.randomUUID();
            setUploads((list) => [...list, { id, name: file.name, progress: 0, error: null }]);

            try {
                const stored = await uploadToWasabi(file, {
                    onProgress: (progress) => patchUpload(id, { progress }),
                });
                setFiles((list) => [stored, ...list]);
                setUploads((list) => list.filter((u) => u.id !== id));
                onUploaded?.(stored);
            } catch (error) {
                patchUpload(id, { error: error.message });
            }
        }));
    };

    const remove = async (file) => {
        if (!window.confirm(`Delete ${file.name}?`)) return;
        await deleteWasabiFile(file.key);
        setFiles((list) => list.filter((f) => f.key !== file.key));
    };

    return (
        <div className="wasabi">
            <label
                style={{
                    display: 'block', padding: '2rem', borderRadius: '.5rem', textAlign: 'center', cursor: 'pointer',
                    border: `2px dashed ${dragging ? '#22c55e' : '#9ca3af'}`,
                    background: dragging ? '#f0fdf4' : 'transparent',
                }}
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={(e) => { e.preventDefault(); setDragging(false); }}
                onDrop={(e) => { e.preventDefault(); setDragging(false); upload(e.dataTransfer.files); }}
            >
                <input
                    type="file"
                    multiple
                    accept={accept}
                    hidden
                    onChange={(e) => { upload(e.target.files); e.target.value = ''; }}
                />
                Drop files here or <u>browse</u>
            </label>

            {uploads.length > 0 && (
                <ul>
                    {uploads.map((u) => (
                        <li key={u.id}>
                            {u.name}{' '}
                            {u.error ? <span style={{ color: '#dc2626' }}>{u.error}</span> : <progress value={u.progress} max="100" />}
                        </li>
                    ))}
                </ul>
            )}

            {loading && <p>Loading…</p>}
            {!loading && files.length === 0 && <p>No files yet.</p>}

            {!loading && files.length > 0 && (
                <ul>
                    {files.map((file) => (
                        <li key={file.key} style={{ display: 'flex', gap: '.75rem', alignItems: 'center' }}>
                            {isImage(file.name) && <img src={file.url} alt="" width={64} loading="lazy" />}
                            {/* file.url is a presigned URL (valid 15 min); refresh() renews it */}
                            <a href={file.url} target="_blank" rel="noopener noreferrer">{file.name}</a>
                            <small>{formatSize(file.size)}</small>
                            <button type="button" onClick={() => remove(file)}>Delete</button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
