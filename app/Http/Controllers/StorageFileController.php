<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class StorageFileController extends Controller
{
    /**
     * Serve files from storage/app/public through PHP.
     *
     * This is a fallback for environments (e.g. Hostinger shared hosting)
     * where the public/storage symlink is missing or cannot be created.
     * When the symlink exists, the web server serves the file directly and
     * this controller is never reached.
     */
    public function show(string $path): BinaryFileResponse|Response
    {
        $baseDir = storage_path('app/public');
        $path = str_replace(['../', '..\\'], '', $path);

        // When PUBLIC_STORAGE_PATH=app/public, generated URLs include that segment
        // (e.g. /storage/app/public/uploads/file.jpg). Strip it before resolving on disk.
        $publicSegment = trim((string) config('filesystems.public_storage_path', ''), '/');
        if ($publicSegment !== '') {
            $prefix = $publicSegment . '/';
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            } elseif ($path === $publicSegment) {
                $path = '';
            }
        }

        $requested = $baseDir . DIRECTORY_SEPARATOR . ltrim($path, '/');

        $realBase = realpath($baseDir);
        $realPath = realpath($requested);

        if ($realBase === false || $realPath === false) {
            abort(404);
        }

        // Prevent directory traversal outside the public storage directory.
        if (!str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        if (!is_file($realPath)) {
            abort(404);
        }

        return response()->file($realPath, [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
