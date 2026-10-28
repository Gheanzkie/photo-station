<?php
/**
 * Photo Station — save / check endpoint.
 *
 * POST  id, image(data-url), csrf   -> writes {id}.jpg
 * GET   check=1&id=1001             -> {"exists":bool,"preview":data-url|null}
 */

require __DIR__ . '/lib.php';

if (! ps_authed()) {
    ps_json(['ok' => false, 'error' => 'Not signed in.'], 401);
}

$id = ps_id($_REQUEST['id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ?list=1 -> the current role folder contents, for live refresh (no reload)
    if (isset($_GET['list'])) {
        $items = [];
        foreach (ps_recent() as $row) {
            $items[] = [
                'id'      => $row['id'],
                'preview' => ps_preview_src($row['path']),
                'stamp'   => date('M d, Y h:i A', $row['mtime']),
            ];
        }

        ps_json([
            'ok'    => true,
            'role'  => (string) ps_config()['role'],
            'items' => $items,
        ]);
    }

    if ($id === '') {
        ps_json(['ok' => false, 'error' => 'Student ID required.'], 400);
    }

    $path = ps_existing($id);

    ps_json([
        'ok'      => true,
        'id'      => $id,
        'exists'  => $path !== null,
        'preview' => $path !== null ? ps_preview_src($path) : null,
        'stamp'   => $path !== null ? date('M d, Y h:i A', filemtime($path)) : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ps_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (! ps_csrf_ok($_POST['csrf'] ?? '')) {
    ps_json(['ok' => false, 'error' => 'Session expired. Reload the page.'], 419);
}

if ($id === '') {
    ps_json(['ok' => false, 'error' => 'Student ID is required (numbers only).'], 400);
}

if (strlen($id) > 24) {
    ps_json(['ok' => false, 'error' => 'Student ID is too long.'], 400);
}

$image = (string) ($_POST['image'] ?? '');
if ($image === '') {
    ps_json(['ok' => false, 'error' => 'No captured picture was sent.'], 400);
}

$result = ps_save_dataurl($image, $id);

if (! $result['ok']) {
    ps_json(['ok' => false, 'error' => $result['error']], 422);
}

ps_json([
    'ok'      => true,
    'id'      => $id,
    'file'    => $result['file'],
    'name'    => basename($result['file']),
    'dir'     => dirname($result['file']),
    'preview' => ps_preview_src($result['file']),
    'message' => 'Saved as ' . basename($result['file']),
]);
