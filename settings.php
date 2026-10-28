<?php
/**
 * Photo Station — role (save folder) settings.
 *
 * Only the five role selectors are accepted. No free-text paths, so the
 * operator can never see or mistype an upper folder.
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

$cfg     = ps_config();
$role    = strtolower(trim((string) ($_POST['role'] ?? '')));
$allowed = array_keys($cfg['folders']);

if (! in_array($role, $allowed, true)) {
    ps_json(['ok' => false, 'error' => 'Pick one of the four folders.'], 400);
}

$dir = rtrim((string) $cfg['base_dir'], "/\\") . '/' . $cfg['folders'][$role];

if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
    ps_json(['ok' => false, 'error' => 'Folder could not be created.'], 422);
}

if (! is_writable($dir)) {
    ps_json(['ok' => false, 'error' => 'Folder is not writable by the web server.'], 422);
}

$content = "<?php\n"
    . "// Written by the Photo Station settings UI.\n"
    . "return " . var_export(['role' => $role], true) . ";\n";

if (@file_put_contents(__DIR__ . '/config.local.php', $content, LOCK_EX) === false) {
    ps_json(['ok' => false, 'error' => 'Could not save the selection.'], 500);
}

ps_json([
    'ok'     => true,
    'role'   => $role,
    'label'  => $cfg['labels'][$role] ?? ucfirst($role),
    'prefix' => ps_role_prefix($role),
]);
