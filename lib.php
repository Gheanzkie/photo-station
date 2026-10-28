<?php
/**
 * Photo Station — shared helpers.
 */

if (! function_exists('ps_config')) {
    function ps_config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = require __DIR__ . '/config.php';
        }

        return $cfg;
    }
}

if (! function_exists('ps_role')) {
    function ps_role(): string
    {
        return (string) (ps_config()['role'] ?? 'students');
    }
}

if (! function_exists('ps_slot')) {
    /** Sub-fetcher slot: 1 or 2 (max two per family, min one). */
    function ps_slot(): int
    {
        ps_session();
        $slot = (int) ($_SESSION['ps_slot'] ?? 1);

        return in_array($slot, [1, 2], true) ? $slot : 1;
    }
}

if (! function_exists('ps_role_prefix')) {
    /** students = plain number, everyone else gets a prefix. */
    function ps_role_prefix(?string $role = null): string
    {
        $role = $role ?? ps_role();

        $map = [
            'students' => '',
            'parents'  => 'P-',
            'fetchers' => 'P-',   // legacy alias — same guardians folder
            'teachers' => 'T-',
            'staffs'   => 'S-',
        ];

        return $map[$role] ?? '';
    }
}

if (! function_exists('ps_role_prefixes')) {
    /** Every prefix a folder may contain (sub-fetchers keep F1 and F2). */
    function ps_role_prefixes(?string $role = null): array
    {
        $role = $role ?? ps_role();

        $prefix = ps_role_prefix($role);

        return $prefix === '' ? [] : [$prefix];
    }
}

if (! function_exists('ps_digits')) {
    /** Numbers only, max 20 digits. */
    function ps_digits($value): string
    {
        return substr(preg_replace('/[^0-9]/', '', (string) $value), 0, 20);
    }
}

if (! function_exists('ps_id')) {
    /**
     * Normalised identifier for the CURRENT role folder:
     *   students -> 1001
     *   parents  -> P-1001
     *   teachers -> T-1001
     *   staffs   -> S-1001
     *   fetchers -> F-1001
     */
    function ps_id($value): string
    {
        $digits = ps_digits($value);

        return $digits === '' ? '' : ps_role_prefix() . $digits;
    }
}

if (! function_exists('ps_json')) {
    function ps_json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (! function_exists('ps_target_dir')) {
    /** Resolve the output folder, creating it when missing. */
    function ps_target_dir(): ?string
    {
        $cfg  = ps_config();
        $role = (string) ($cfg['role'] ?? 'students');
        $sub  = $cfg['folders'][$role] ?? 'students';

        $dir = rtrim((string) $cfg['base_dir'], "/\\") . '/' . ltrim((string) $sub, '/');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return null;
        }

        return $dir;
    }
}

if (! function_exists('ps_candidate_ids')) {
    /**
     * Every filename stem an id may exist under.
     *
     * Older files were saved without the role prefix (1234.jpg) while new
     * ones use it (P-1234.jpg), so lookups must try both.
     */
    function ps_candidate_ids($value): array
    {
        $digits = ps_digits($value);
        if ($digits === '') {
            return [];
        }

        $out = [$digits];

        foreach (ps_role_prefixes() as $prefix) {
            $out[] = $prefix . $digits;
        }

        return array_values(array_unique($out));
    }
}

if (! function_exists('ps_existing')) {
    /** Absolute path of an existing photo for this id, or null. */
    function ps_existing(string $id): ?string
    {
        $dir = ps_target_dir();
        if ($dir === null) {
            return null;
        }

        foreach (ps_candidate_ids($id) as $candidate) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $path = $dir . DIRECTORY_SEPARATOR . $candidate . '.' . $ext;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}

if (! function_exists('ps_save_dataurl')) {
    /**
     * Decode data:image/...;base64 and write {id}.jpg.
     * Centre-crops to a square and resizes to config['size'].
     */
    function ps_save_dataurl(string $dataUrl, string $id): array
    {
        $cfg = ps_config();

        if (! preg_match('#^data:image/(?:jpeg|jpg|png|webp);base64,#i', $dataUrl, $m)) {
            return ['ok' => false, 'error' => 'Unsupported image payload.'];
        }

        $binary = base64_decode(substr($dataUrl, strlen($m[0])), true);
        if ($binary === false || $binary === '') {
            return ['ok' => false, 'error' => 'Image could not be decoded.'];
        }

        if (strlen($binary) > (int) $cfg['max_bytes']) {
            return ['ok' => false, 'error' => 'Image is too large.'];
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return ['ok' => false, 'error' => 'Payload is not a valid image.'];
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return ['ok' => false, 'error' => 'Image format not supported by GD.'];
        }

        [$width, $height] = $info;
        $side = min($width, $height);
        $sx   = (int) (($width - $side) / 2);
        $sy   = (int) (($height - $side) / 2);

        $max = max(64, (int) $cfg['size']);
        $dst = imagecreatetruecolor($max, $max);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $max, $max, $side, $side);

        $dir = ps_target_dir();
        if ($dir === null) {
            imagedestroy($src);
            imagedestroy($dst);

            return ['ok' => false, 'error' => 'Output folder is not writable.'];
        }

        // Remove stale variants so a old .png never shadows the new .jpg.
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $old = $dir . DIRECTORY_SEPARATOR . $id . '.' . $ext;
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $out = $dir . DIRECTORY_SEPARATOR . $id . '.jpg';
        $ok  = imagejpeg($dst, $out, max(1, min(100, (int) $cfg['jpeg_quality'])));

        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok) {
            return ['ok' => false, 'error' => 'Could not write the image file.'];
        }

        return ['ok' => true, 'file' => $out];
    }
}

if (! function_exists('ps_recent')) {
    /** Newest {digits}.jpg files in the target folder. */
    function ps_recent(int $limit = 0): array
    {
        $dir = ps_target_dir();
        if ($dir === null) {
            return [];
        }

        $limit = $limit > 0 ? $limit : (int) ps_config()['recent_limit'];
        $files = [];

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.jpg') ?: [] as $path) {
            $base = basename($path, '.jpg');
            if (! preg_match('/^(?:[A-Z]-)?[0-9]+$/', $base)) {
                continue;
            }
            $files[] = ['id' => $base, 'path' => $path, 'mtime' => filemtime($path)];
        }

        usort($files, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return array_slice($files, 0, $limit);
    }
}

if (! function_exists('ps_preview_src')) {
    /** Inline base64 preview so the station works even if the target folder is not web-served. */
    function ps_preview_src(string $path): string
    {
        $bin = @file_get_contents($path);
        if ($bin === false) {
            return '';
        }

        return 'data:image/jpeg;base64,' . base64_encode($bin);
    }
}

if (! function_exists('ps_session')) {
    function ps_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }
}

if (! function_exists('ps_authed')) {
    function ps_authed(): bool
    {
        ps_session();

        return ! empty($_SESSION['ps_authed']);
    }
}

if (! function_exists('ps_csrf')) {
    function ps_csrf(): string
    {
        ps_session();
        if (empty($_SESSION['ps_csrf'])) {
            $_SESSION['ps_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['ps_csrf'];
    }
}

if (! function_exists('ps_csrf_ok')) {
    function ps_csrf_ok($token): bool
    {
        ps_session();

        return is_string($token)
            && ! empty($_SESSION['ps_csrf'])
            && hash_equals($_SESSION['ps_csrf'], $token);
    }
}
