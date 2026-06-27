<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('image_url')) {
    /**
     * Resolve any image-path string to a browser-loadable URL.
     *
     * Catalog/CMS images are uploaded by Filament FileUpload to the public
     * disk and stored as relative paths like "catalog/products/abc.webp".
     * Static template images live in the document root and may be stored
     * as "/images/foo.webp" or "images/foo.webp". External assets may be
     * absolute (https://...). This helper unifies all three.
     *
     *  - null/empty → null
     *  - "https?://..." → returned unchanged
     *  - "/foo.webp" or "foo.webp" with a leading "images/", "fonts/",
     *     "build/" segment → returned via asset() (document root)
     *  - everything else → resolved through the public disk so it picks up
     *     the relative /storage/... URL configured in filesystems.php
     */
    function image_url(?string $path, string $disk = 'public'): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        // Static-asset prefixes that ship under public/. Return a
        // host-relative URL so the same path renders correctly whether
        // the request is to localhost, the dev IP, staging, or prod.
        if (preg_match('#^(images|fonts|videos|favicon)\b#', $normalized)) {
            return '/'.$normalized;
        }

        // Vite-built assets — let asset() handle hashed manifest URLs.
        if (str_starts_with($normalized, 'build/')) {
            return asset($normalized);
        }

        return Storage::disk($disk)->url($normalized);
    }
}
