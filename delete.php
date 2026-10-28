<?php
/**
 * Photo Station — delete a saved picture so it can be retaken.
 *
 * Only files that live inside the configured save folder are removable,
 * and only when the name is a plain student id.
 */

require __DIR__ . '/lib.php';

if (! ps_authed()) {
    ps_json(['ok' => false, 'error' => 'Not signed in.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ps_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (! ps_csrf_ok($_POST['csrf'] ?? '')) {
    ps_json(['ok' => false, 'error' => 'Session expired. Reload the page.'], 419);
}

$id = ps_id($_POST['id'] ?? '');
if ($id === '' || strlen($id) > 24) {
    ps_json(['ok' => false, 'error' => 'Invalid student ID.'], 400);
}

$dir  = ps_target_dir();
$root = $dir !== null ? realpath($dir) : false;

if ($root === false) {
    ps_json(['ok' => false, 'error' => 'Save folder is not available.'], 500);
}

// Remove every variant (1234.jpg and P-1234.jpg) so old and new files both go.
$removed = 0;

foreach (ps_candidate_ids($id) as $candidate) {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $path = $dir . DIRECTORY_SEPARATOR . $candidate . '.' . $ext;
        if (! is_file($path)) {
            continue;
        }

        $real = realpath($path);
        if ($real === false || strpos($real, $root) !== 0) {
            continue;
        }

        if (@unlink($real)) {
            $removed++;
        }
    }
}

if ($removed === 0) {
    ps_json(['ok' => false, 'error' => 'No picture found for ' . $id . '.'], 404);
}

ps_json([
    'ok'      => true,
    'id'      => $id,
    'message' => 'Picture for student ' . $id . ' deleted. Ready for a retake.',
]);
