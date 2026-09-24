<?php
namespace App\Helper;

class FileHelper
{
    /**
     * MIME types we consider valid images, mapped to their canonical extension.
     */
    private const IMAGE_MIME_MAP = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Handle an uploaded file: validate, move it into $directory, and return
     * the relative path. Returns null on any failure.
     *
     * @param array  $file       A single entry from $_FILES (not the whole array).
     * @param string $directory  Absolute or relative target directory.
     * @param string $prefix     Optional filename prefix (e.g. 'avatar_').
     * @return string|null       Relative path of the stored file, or null on failure.
     */
    public static function upload(array $file, string $directory, string $prefix = ''): ?string
    {
        // 1. Basic structural checks on the $_FILES entry.
        if (
            empty($file) ||
            !isset($file['tmp_name'], $file['error'], $file['name']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // 2. Ensure the target directory exists and is writable.
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                return null;
            }
        }

        if (!is_writable($directory)) {
            return null;
        }

        // 3. Build a safe, collision-resistant filename.
        $filename = self::generateFilename($file['name'], $prefix);
        $destination = rtrim($directory, DIRECTORY_SEPARATOR)
                     . DIRECTORY_SEPARATOR
                     . $filename;

        // 4. Move the file. move_uploaded_file() verifies it was an HTTP upload.
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return null;
        }

        // 5. Lock down permissions (0644 = owner rw, others r).
        @chmod($destination, 0644);

        return $destination;
    }

    /**
     * Delete a file if it exists. Returns true if the file is gone afterwards.
     */
    public static function delete(string $path): bool
    {
        if (!is_file($path)) {
            // Already gone → treat as success (idempotent).
            return true;
        }

        if (!is_writable($path)) {
            return false;
        }

        return @unlink($path);
    }

    /**
     * Check whether a regular file exists at the given path.
     */
    public static function exists(string $path): bool
    {
        return is_file($path);
    }

    /**
     * Generate a safe, unique filename that preserves the original extension.
     */
    public static function generateFilename(string $originalName, string $prefix = ''): string
    {
        // Extract extension, lowercase it, and whitelist to alphanumerics.
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if ($ext === '' || $ext === null) {
            $ext = 'bin';
        }

        // Sanitize the prefix (allow letters, digits, underscore, hyphen).
        $prefix = preg_replace('/[^A-Za-z0-9_\-]/', '', $prefix);

        // Unique component: time + random bytes, both hex-encoded.
        $unique = bin2hex(random_bytes(8));

        $name = ($prefix !== '' ? $prefix . '_' : '') . $unique . '.' . $ext;

        return $name;
    }

    /**
     * Validate an uploaded image file.
     *
     * @return array{valid: bool, error: ?string, mime: ?string, width: ?int, height: ?int}
     */
    public static function validateImage(array $file, int $maxBytes = 2097152): array
    {
        $result = [
            'valid'  => false,
            'error'  => null,
            'mime'   => null,
            'width'  => null,
            'height' => null,
        ];

        // 1. Structural checks.
        if (
            empty($file) ||
            !isset($file['tmp_name'], $file['error'], $file['size']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            $result['error'] = 'Invalid upload payload.';
            return $result;
        }

        // 2. Upload-level error code.
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Upload failed with code ' . (int) $file['error'] . '.';
            return $result;
        }

        // 3. Size check.
        if ($file['size'] <= 0) {
            $result['error'] = 'File is empty.';
            return $result;
        }

        if ($file['size'] > $maxBytes) {
            $result['error'] = sprintf(
                'File exceeds maximum size of %d bytes.',
                $maxBytes
            );
            return $result;
        }

        // 4. Real MIME type check — never trust $_FILES['type'] (client-provided).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';

        if (!isset(self::IMAGE_MIME_MAP[$mime])) {
            $result['error'] = 'Unsupported image type: ' . $mime;
            return $result;
        }

        // 5. Confirm the bytes actually decode as an image.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            $result['error'] = 'File is not a valid image.';
            return $result;
        }

        // Double check getimagesize's reported MIME matches finfo.
        if (!empty($info['mime']) && $info['mime'] !== $mime) {
            $result['error'] = 'Image MIME mismatch — possible spoofed file.';
            return $result;
        }

        $result['valid']  = true;
        $result['mime']   = $mime;
        $result['width']  = $info[0] ?? null;
        $result['height'] = $info[1] ?? null;

        return $result;
    }
}