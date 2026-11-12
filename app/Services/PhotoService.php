<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, Settings, UserError};

/** Student photos: validated, re-encoded, stored under storage/student_photos with random names. */
class PhotoService
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_PIXELS = 40000000;

    public static function dir(): string
    {
        return STORAGE_DIR . '/student_photos';
    }

    public static function path(?string $file): ?string
    {
        if (!$file || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $file)) {
            return null;
        }
        $p = self::dir() . '/' . $file;
        return is_file($p) ? $p : null;
    }

    public static function contentType(string $file): string
    {
        return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    }

    /** @param array $upload an entry of $_FILES */
    public static function save(int $studentId, array $upload): void
    {
        Auth::require('students.manage');
        $stu = StudentService::find($studentId);
        if (!$stu) {
            throw new UserError('Student not found.');
        }
        if (!isset($upload['error']) || is_array($upload['error'])) {
            throw new UserError('No file was uploaded.');
        }
        if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
            throw new UserError('Choose a photo to upload.');
        }
        if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new UserError('The photo is too large.');
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new UserError('The upload failed. Please try again.');
        }
        $maxMb = max(1, min(20, (int) Settings::get('photo_max_mb', '5')));
        if ($upload['size'] > $maxMb * 1024 * 1024) {
            throw new UserError("The photo must be $maxMb MB or smaller.");
        }
        $name = self::storeFile($upload['tmp_name'], (int) $upload['size']);
        $old = $stu['photo_file'];
        Db::exec('UPDATE students SET photo_file = ?, updated_at = NOW() WHERE id = ?', [$name, $studentId]);
        if ($old && ($op = self::path($old))) {
            @unlink($op);
        }
        Audit::log($old ? 'student.photo.replace' : 'student.photo.add', 'student', $studentId);
    }

    /** Validate image content (not the client-supplied name/type) and store a clean copy. Returns the stored file name. */
    public static function storeFile(string $tmp, int $size): string
    {
        if ($size <= 0) {
            throw new UserError('The file is empty.');
        }
        $mime = null;
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $tmp);
            finfo_close($fi);
        }
        $info = @getimagesize($tmp);
        if (!$info) {
            throw new UserError('That file is not a valid image. Use a JPEG, PNG or WebP photo.');
        }
        $mime = $mime ?: $info['mime'];
        if (!isset(self::TYPES[$mime]) || !isset(self::TYPES[$info['mime']])) {
            throw new UserError('Only JPEG, PNG and WebP photos are allowed.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS || $info[0] < 20 || $info[1] < 20) {
            throw new UserError('The image dimensions are not acceptable.');
        }
        $ext = self::TYPES[$mime];
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = self::dir() . '/' . $name;
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0775, true);
        }
        $done = false;
        if (extension_loaded('gd')) {
            $done = self::reencode($tmp, $mime, $dest);
        }
        if (!$done && !@copy($tmp, $dest)) {
            throw new UserError('The photo could not be saved. Check that the storage folder is writable.');
        }
        @chmod($dest, 0640);
        return $name;
    }

    /** Re-encoding drops metadata and any trailing payload; it also caps the size at 800 px. */
    private static function reencode(string $src, string $mime, string $dest): bool
    {
        $fn = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefromstring', 'image/webp' => 'imagecreatefromwebp'][$mime] ?? null;
        if (!$fn || !function_exists($fn)) {
            return false;
        }
        $img = $mime === 'image/png' ? @imagecreatefromstring((string) file_get_contents($src)) : @$fn($src);
        if (!$img) {
            return false;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, 800 / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($mime !== 'image/jpeg') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
        }
        $ok = false;
        if ($mime === 'image/jpeg') {
            $ok = imagejpeg($img, $dest, 88);
        } elseif ($mime === 'image/png') {
            imagesavealpha($img, true);
            $ok = imagepng($img, $dest, 6);
        } elseif (function_exists('imagewebp')) {
            $ok = imagewebp($img, $dest, 85);
        }
        imagedestroy($img);
        return $ok;
    }

    public static function remove(int $studentId): void
    {
        Auth::require('students.manage');
        $stu = StudentService::find($studentId);
        if (!$stu || !$stu['photo_file']) {
            throw new UserError('This student has no photo.');
        }
        Db::exec('UPDATE students SET photo_file = NULL, updated_at = NOW() WHERE id = ?', [$studentId]);
        if ($p = self::path($stu['photo_file'])) {
            @unlink($p);
        }
        Audit::log('student.photo.remove', 'student', $studentId);
    }
}
