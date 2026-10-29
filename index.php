<?php
/**
 * Photo Station — standalone picture-taking app.
 *
 * Part 2 of registration: staff types the student id, takes the shot,
 * retakes, saves. Output file is {student_id}.jpg in the configured folder
 * (by default scan2fetch/public/uploads/students), so scan2fetch picks it up
 * by filename with no import step.
 */

require __DIR__ . '/lib.php';

$cfg = ps_config();

// ---- simple PIN gate ----
ps_session();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    if (hash_equals((string) $cfg['pin'], (string) $_POST['pin'])) {
        session_regenerate_id(true);
        $_SESSION['ps_authed'] = true;
    } else {
        $loginError = 'Wrong PIN.';
    }
}

if (! ps_authed()) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%234361ee'/%3E%3Cpath d='M20 24h6l3-4h6l3 4h6a3 3 0 0 1 3 3v14a3 3 0 0 1-3 3H20a3 3 0 0 1-3-3V27a3 3 0 0 1 3-3z' fill='%23fff'/%3E%3Ccircle cx='32' cy='34' r='6' fill='%234361ee'/%3E%3C/svg%3E">
        <meta name="theme-color" content="#4361ee">
        <title>SCAN2FETCH Photo Station | Sign In</title>
        <style>
            :root { color-scheme: dark; }
            * { box-sizing: border-box; }
            body { margin:0; min-height:100vh; display:grid; place-items:center;
                   font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                   background:#0d1020; color:#e8eaf6; }
            form { background:#151a33; border:1px solid #26305c; border-radius:16px;
                   padding:32px; width:320px; box-shadow:0 20px 60px rgba(0,0,0,.5); }
            h1 { font-size:18px; margin:0 0 6px; letter-spacing:.5px; }
            p  { font-size:13px; color:#8c93b8; margin:0 0 20px; }
            input { width:100%; padding:14px; border-radius:10px; border:1px solid #2c3766;
                    background:#0f1430; color:#fff; font-size:18px; letter-spacing:6px;
                    text-align:center; }
            button { width:100%; margin-top:14px; padding:13px; border:0; border-radius:10px;
                     background:#4361ee; color:#fff; font-weight:700; font-size:15px;
                     cursor:pointer; }
            .err { color:#ff6b81; font-size:13px; margin-top:12px; text-align:center; }
        </style>
    </head>
    <body>
        <form method="post">
            <h1>PHOTO STATION</h1>
            <p>Enter the station PIN to start.</p>
            <input type="password" name="pin" inputmode="numeric" autocomplete="off" autofocus required>
            <button type="submit">Unlock</button>
            <?php if ($loginError !== ''): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
        </form>
    </body>
    </html>
    <?php
    exit;
}

$csrf   = ps_csrf();
$recent = ps_recent();
$target = ps_target_dir() ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%234361ee'/%3E%3Cpath d='M20 24h6l3-4h6l3 4h6a3 3 0 0 1 3 3v14a3 3 0 0 1-3 3H20a3 3 0 0 1-3-3V27a3 3 0 0 1 3-3z' fill='%23fff'/%3E%3Ccircle cx='32' cy='34' r='6' fill='%234361ee'/%3E%3C/svg%3E">
<meta name="theme-color" content="#4361ee">
<title>SCAN2FETCH Photo Station | Student Pictures</title>
<style>
    :root { color-scheme: dark; --bg:#0d1020; --card:#151a33; --line:#26305c;
            --ink:#e8eaf6; --muted:#8c93b8; --accent:#4361ee; --ok:#22c55e; --bad:#ff6b81; }
    * { box-sizing:border-box; }
    html, body { height:100%; }
    body { margin:0; overflow:hidden; display:flex; flex-direction:column;
           font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
           background:var(--bg); color:var(--ink); }
    header { flex:0 0 auto; display:flex; align-items:center; justify-content:space-between;
             padding:8px 16px; border-bottom:1px solid var(--line); background:#10142a; }
    header h1 { font-size:13px; margin:0; letter-spacing:1px; font-weight:800; }
    header .right { font-size:11px; color:var(--muted); display:flex; gap:14px; align-items:center; }
    header a { color:var(--muted); text-decoration:none; }
    header a:hover { color:var(--ink); }

    .wrap { flex:1 1 auto; min-height:0; display:grid;
            grid-template-columns: minmax(0,1fr) 260px; gap:10px; padding:10px 14px; }
    .card { min-height:0; display:flex; flex-direction:column; overflow:hidden;
            background:var(--card); border:1px solid var(--line); border-radius:12px; padding:10px 12px; }
    .card h2 { flex:0 0 auto; font-size:10px; text-transform:uppercase; letter-spacing:1.2px;
               color:var(--muted); margin:0 0 8px; }

    label { flex:0 0 auto; display:block; font-size:11px; color:var(--muted); margin-bottom:4px; }
    .idrow { flex:0 0 auto; display:flex; align-items:stretch; width:100%; max-width:100%; }
    .idprefix { flex:0 0 auto; display:flex; align-items:center; padding:0 10px; font-size:16px;
                font-weight:800; letter-spacing:1px; color:var(--accent); background:#171d3a;
                border:1px solid #2c3766; border-right:0; border-radius:9px 0 0 9px;
                white-space:nowrap; }
    .idrow.noprefix .idprefix { display:none; }
    .idprefix:empty { display:none; }
    .slots { flex:0 0 auto; display:flex; gap:6px; margin-top:6px; }
    .slots .slot { flex:1 1 0; padding:7px 10px; font-size:12px; font-weight:700; border-radius:8px;
                   background:#0f1430; border:1px solid #2c3766; color:var(--muted); }
    .slots .slot.on { background:var(--accent); border-color:var(--accent); color:#fff; }
    #sid { flex:1 1 auto; min-width:0; width:auto; padding:8px 12px; font-size:18px;
           letter-spacing:3px; font-weight:700; border-radius:0 9px 9px 0;
           border:1px solid #2c3766; background:#0f1430; color:#fff; }
    .idrow.noprefix #sid { border-radius:9px; }
    #sid:focus { outline:2px solid var(--accent); outline-offset:-1px; border-color:transparent; }
    #sid:focus { outline:2px solid var(--accent); border-color:transparent; }
    .hint { flex:0 0 auto; font-size:11px; color:var(--muted); margin:5px 0 0; min-height:14px; }
    .hint.ok { color:var(--ok); } .hint.bad { color:var(--bad); }

    .stage { flex:0 0 auto; align-self:center; margin:8px auto 0; max-width:100%;
             width: clamp(220px, calc(100vh - 270px), 520px);
             height: clamp(220px, calc(100vh - 270px), 520px);
             aspect-ratio:1 / 1; position:relative; border-radius:10px; overflow:hidden;
             background:#070914; border:1px solid var(--line); display:grid; place-items:center; }
    .stage video, .stage canvas, .stage img { width:100%; height:100%; object-fit:cover; display:none; }
    .stage .active { display:block; }
    .stage .placeholder { color:#4b5686; font-size:12px; }

    .controls { flex:0 0 auto; display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
    button { border:0; border-radius:9px; padding:9px 14px; font-size:12px; font-weight:700;
             cursor:pointer; color:#fff; background:#2c3766; }
    button.primary { background:var(--accent); }
    button.ok { background:var(--ok); }
    button:disabled { opacity:.4; cursor:not-allowed; }

    .status { flex:0 0 auto; margin-top:6px; font-size:11px; min-height:14px; color:var(--muted); }
    .status.ok { color:var(--ok); } .status.bad { color:var(--bad); }

    .existing { flex:0 0 auto; margin-top:6px; display:none; align-items:center; gap:8px;
                border:1px dashed var(--line); border-radius:9px; padding:6px; }
    .existing img { width:36px; height:36px; border-radius:7px; object-fit:cover; }
    .existing .t { font-size:11px; color:var(--muted); }
    .existing .t strong { display:block; color:var(--ink); font-size:12px; margin-bottom:1px; }

    #btnSettings { background:transparent; border:1px solid var(--line); padding:4px 9px;
                   font-size:15px; line-height:1; border-radius:8px; color:var(--muted); }
    #btnSettings:hover { color:var(--ink); }
    #btnSettings.on { background:var(--accent); border-color:var(--accent); color:#fff; }

    .settings-pop { position:fixed; top:46px; right:14px; width:320px; z-index:60;
                    display:none; background:var(--card); border:1px solid var(--line);
                    border-radius:12px; padding:12px; box-shadow:0 18px 50px rgba(0,0,0,.65); }
    .settings-pop.open { display:block; }
    .settings-pop h3 { margin:0 0 8px; font-size:10px; letter-spacing:1.2px;
                       text-transform:uppercase; color:var(--muted); }

    .roles { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
    .roles .role { padding:9px 8px; font-size:12px; border-radius:8px; background:#0f1430;
                   border:1px solid #2c3766; color:var(--muted); }
    .roles .role.on { background:var(--accent); border-color:var(--accent); color:#fff; }
    .roles .role:nth-child(5) { grid-column:1 / -1; }
    .folder label { margin-bottom:3px; }
    .folder input { width:100%; padding:6px 8px; font-size:11px; border-radius:7px;
                    border:1px solid #2c3766; background:#0f1430; color:var(--ink); }
    .folder select { flex:1 1 auto; min-width:0; padding:6px 8px; font-size:11px;
                     border-radius:7px; border:1px solid #2c3766; background:#0f1430; color:var(--ink); }
    .folder .row { display:flex; gap:6px; margin-top:6px; }
    .folder button { padding:6px 10px; font-size:11px; border-radius:7px; }
    .folder .fstat { font-size:10px; margin-top:4px; min-height:12px; color:var(--muted); }
    .folder .fstat.ok { color:var(--ok); } .folder .fstat.bad { color:var(--bad); }

    .grid { flex:1 1 auto; min-height:0; overflow-y:auto; align-content:start;
            display:grid; grid-template-columns:repeat(auto-fill,minmax(56px,1fr)); gap:8px; }
    .thumb { position:relative; border-radius:8px; overflow:hidden; border:1px solid var(--line);
             background:#0f1430; }
    .thumb img { width:100%; height:56px; object-fit:cover; display:block; }
    .thumb span { position:absolute; left:0; right:0; bottom:0; font-size:9px; padding:2px 4px;
                  background:rgba(7,9,20,.8); text-align:center; letter-spacing:.4px; }
    .thumb .del { position:absolute; top:2px; right:2px; width:17px; height:17px; padding:0;
                  border-radius:50%; font-size:12px; line-height:15px; text-align:center;
                  background:rgba(255,107,129,.92); color:#fff; opacity:.8; }
    .thumb .del:hover { opacity:1; background:#ff6b81; }
    .path { flex:0 0 auto; font-size:10px; color:#5d6796; word-break:break-all; margin-top:6px; }

    @media (max-width: 900px) {
        body { overflow:auto; }
        .wrap { grid-template-columns:1fr; }
        .stage { width:100%; height:auto; aspect-ratio:1 / 1; }
        .grid { max-height:180px; }
    }
</style>
</head>
<body>
<header>
    <h1>PHOTO STATION</h1>
    <div class="right">
        <span id="recentCount"><?= count($recent) ?> recent</span>
        <button type="button" id="btnSettings" title="Settings" aria-expanded="false">&#9881;</button>
        <a href="?logout=1">Sign out</a>
    </div>
</header>

<div class="settings-pop" id="settingsPop">
    <h3>Save into</h3>
    <div class="roles" id="rolePick">
        <?php foreach (($cfg['labels'] ?? []) as $rk => $rlabel): ?>
            <button type="button" class="role<?= (($cfg['role'] ?? 'students') === $rk) ? ' on' : '' ?>" data-role="<?= $rk ?>"><?= $rlabel ?></button>
        <?php endforeach; ?>
    </div>
    <div class="fstat" id="dirStatus"></div>
</div>

<div class="wrap">
    <div class="card">
        <h2>Student Picture</h2>

        <label for="sid">Student ID (numbers only)</label>
        <div class="idrow">
            <span class="idprefix" id="idPrefix"><?= htmlspecialchars(ps_role_prefix($cfg['role'] ?? 'students')) ?></span>
            <input id="sid" inputmode="numeric" autocomplete="off" placeholder="1001" maxlength="20" autofocus>
        </div>
        <div class="hint" id="hint">Type the ID from the information part.</div>

        <div class="existing" id="existing">
            <img id="existingImg" alt="">
            <div class="t"><strong id="existingName">Existing picture</strong><span id="existingStamp"></span></div>
        </div>

        <div class="stage" id="stage">
            <div class="placeholder" id="placeholder">Camera is off</div>
            <video id="video" playsinline muted></video>
            <canvas id="canvas" width="512" height="512"></canvas>
            <img id="shot" alt="">
        </div>

        <div class="controls">
            <button type="button" class="primary" id="btnStart">Open camera</button>
            <button type="button" id="btnStop" disabled>Close camera</button>
            <button type="button" class="ok" id="btnCapture" disabled>Capture</button>
            <button type="button" id="btnRetake" disabled>Retake</button>
            <button type="button" class="primary" id="btnSave" disabled>Save picture</button>
        </div>

        <div class="status" id="status"></div>

    </div>

    <div class="card">
        <h2>Recent pictures</h2>

        <div class="grid" id="recent">
            <?php if ($recent === []): ?>
                <div style="color:#5d6796;font-size:12px;">No Pictures.</div>
            <?php else: foreach ($recent as $r): ?>
                <div class="thumb" title="<?= htmlspecialchars($r['id']) ?>">
                    <img src="<?= ps_preview_src($r['path']) ?>" alt="<?= htmlspecialchars($r['id']) ?>">
                    <span><?= htmlspecialchars($r['id']) ?></span>
                    <button type="button" class="del" data-id="<?= htmlspecialchars($r['id']) ?>" title="Delete">&#215;</button>
                </div>
            <?php endforeach; endif; ?>
        </div>

    </div>
</div>

<script>
(() => {
    const csrf   = <?= json_encode($csrf) ?>;
    const postTo = 'save.php';

    const $sid  = document.getElementById('sid');
    const $idPrefix = document.getElementById('idPrefix');
    const $hint = document.getElementById('hint');
    const $video = document.getElementById('video');
    const $canvas = document.getElementById('canvas');
    const $shot = document.getElementById('shot');
    const $placeholder = document.getElementById('placeholder');
    const $status = document.getElementById('status');
    const $existing = document.getElementById('existing');
    const $existingImg = document.getElementById('existingImg');
    const $existingStamp = document.getElementById('existingStamp');

    const btnStart = document.getElementById('btnStart');
    const btnStop = document.getElementById('btnStop');
    const btnCapture = document.getElementById('btnCapture');
    const btnRetake = document.getElementById('btnRetake');
    const btnSave = document.getElementById('btnSave');

    let stream = null;
    let captured = null;   // data-url

    const setStatus = (msg, kind = '') => {
        $status.textContent = msg;
        $status.className = 'status ' + kind;
    };

    const setHint = (msg, kind = '') => {
        $hint.textContent = msg;
        $hint.className = 'hint ' + kind;
    };

    const digitsOnly = (v) => v.replace(/[^0-9]/g, '');

    // Prefix comes from the active role (P- parents, F- fetchers, T- teachers,
    // S- staffs, none for students).
    const $idRow = document.querySelector('.idrow');

    // Single place that swaps the prefix — keeps the border radius and the
    // flex width correct whether or not a prefix is shown.
    function applyPrefix(prefix) {
        if ($idPrefix) $idPrefix.textContent = prefix || '';
        if ($idRow) $idRow.classList.toggle('noprefix', !prefix);
    }
    applyPrefix($idPrefix ? $idPrefix.textContent : '');



    const rolePrefix = () => ($idPrefix ? $idPrefix.textContent : '');
    const fullId = () => {
        const d = digitsOnly($sid.value);
        return d ? rolePrefix() + d : '';
    };
    $sid.addEventListener('input', () => {
        const clean = digitsOnly($sid.value);
        if (clean !== $sid.value) $sid.value = clean;
        refresh();
    });

    let checkTimer = null;
    function refresh() {
        clearTimeout(checkTimer);
        const id = fullId();
        $existing.style.display = 'none';
        if (!id) { setHint('Type the ID from the information part.'); return; }
        setHint('Checking…');
        checkTimer = setTimeout(async () => {
            try {
                const res = await fetch(postTo + '?check=1&id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) { setHint(data.error || 'Invalid ID.', 'bad'); return; }
                if (data.exists) {
                    $existing.style.display = 'flex';
                    $existingImg.src = data.preview;
                    $existingStamp.textContent = 'Taken ' + data.stamp;
                    setHint('ID ' + id + ' already has a picture — saving will replace it.', 'ok');
                } else {
                    setHint('ID ' + id + ' — no picture yet.', 'ok');
                }
            } catch (e) {
                setHint('Could not check the ID.', 'bad');
            }
        }, 220);
    }

    async function startCamera() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false
            });
            $video.srcObject = stream;
            await $video.play();
            $placeholder.style.display = 'none';
            $video.classList.add('active');
            $shot.classList.remove('active');
            $canvas.classList.remove('active');
            captured = null;
            btnCapture.disabled = false;
            btnRetake.disabled = true;
            btnSave.disabled = true;
            btnStart.disabled = true;
            btnStop.disabled = false;
            setStatus('Camera ready. Press Capture.', 'ok');
        } catch (e) {
            setStatus('Camera error: ' + e.message + ' (use http://localhost, not an IP)', 'bad');
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        $video.srcObject = null;
        $video.classList.remove('active');
        if (!captured) {
            $shot.classList.remove('active');
            $canvas.classList.remove('active');
            $placeholder.style.display = '';
            btnRetake.disabled = true;
            btnSave.disabled = true;
        }
        btnStart.disabled = false;
        btnStop.disabled = true;
        btnCapture.disabled = true;
        setStatus('Camera closed.');
    }

    function capture() {
        if (!stream) { setStatus('Open the camera first.', 'bad'); return; }
        const vw = $video.videoWidth || 1280;
        const vh = $video.videoHeight || 960;
        const side = Math.min(vw, vh);
        const sx = (vw - side) / 2;
        const sy = (vh - side) / 2;

        $canvas.width = 512;
        $canvas.height = 512;
        $canvas.getContext('2d').drawImage($video, sx, sy, side, side, 0, 0, 512, 512);
        captured = $canvas.toDataURL('image/jpeg', 0.9);

        $shot.src = captured;
        $shot.classList.add('active');
        $video.classList.remove('active');

        btnCapture.disabled = true;
        btnRetake.disabled = false;
        btnSave.disabled = false;
        setStatus('Captured. Save it, or Retake for a new shot.');
    }

    function retake() {
        if (!stream) { setStatus('Open the camera first.', 'bad'); return; }
        captured = null;
        $shot.classList.remove('active');
        $video.classList.add('active');
        btnCapture.disabled = false;
        btnRetake.disabled = true;
        btnSave.disabled = true;
        setStatus('Ready for a new shot.');
    }

    async function save() {
        const id = fullId();
        if (!id) { setHint('Student ID is required.', 'bad'); $sid.focus(); return; }
        if (!captured) { setStatus('Nothing captured yet.', 'bad'); return; }

        btnSave.disabled = true;
        setStatus('Saving…');

        const body = new FormData();
        body.append('id', id);
        body.append('image', captured);
        body.append('csrf', csrf);

        try {
            const res = await fetch(postTo, { method: 'POST', body });
            const data = await res.json();
            if (!data.ok) {
                setStatus(data.error || 'Save failed.', 'bad');
                btnSave.disabled = false;
                return;
            }
            addRecent(id, data.preview);

            // ---- reset the station for the next student ----
            setStatus('Saved as ' + data.name + ' — ready for the next student.', 'ok');
            $sid.value = '';
            $existing.style.display = 'none';
            $existingImg.removeAttribute('src');
            $existingStamp.textContent = '';
            captured = null;
            $shot.classList.remove('active');
            $canvas.classList.remove('active');
            if (stream) {
                $video.classList.add('active');
                $placeholder.style.display = 'none';
                btnCapture.disabled = false;
            } else {
                $video.classList.remove('active');
                $placeholder.style.display = '';
                btnCapture.disabled = true;
            }
            btnRetake.disabled = true;
            btnSave.disabled = true;
            setHint('Type the next student ID.');
            $sid.focus();
        } catch (e) {
            setStatus('Save failed: ' + e.message, 'bad');
            btnSave.disabled = false;
        }
    }

    function addRecent(id, src) {
        const grid = document.getElementById('recent');
        const empty = grid.querySelector('div[style]');
        if (empty) empty.remove();
        const el = document.createElement('div');
        el.className = 'thumb';
        el.title = id;
        el.innerHTML = '<img src="' + src + '" alt="' + id + '"><span>' + id + '</span>'
            + '<button type="button" class="del" data-id="' + id + '" title="Delete">&#215;</button>';
        grid.prepend(el);
        bumpRecentCount();
    }

    const $recentCount = document.getElementById('recentCount');
    function bumpRecentCount() {
        const n = document.querySelectorAll('#recent .thumb').length;
        if ($recentCount) $recentCount.textContent = n + ' recent';
    }

    // Pull the current role folder's pictures without reloading the page,
    // so the camera stream is never interrupted.
    async function loadRecent() {
        const grid = document.getElementById('recent');
        try {
            const res = await fetch(postTo + '?list=1', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.ok) return;

            grid.innerHTML = '';
            if (!data.items || data.items.length === 0) {
                grid.innerHTML = '<div style="color:#5d6796;font-size:12px;">No Pictures.</div>';
            } else {
                data.items.forEach((it) => addRecent(it.id, it.preview));
            }
            bumpRecentCount();
        } catch (e) {
            grid.innerHTML = '<div style="color:#5d6796;font-size:12px;">Could not load the folder.</div>';
        }
    }

    btnStart.addEventListener('click', startCamera);
    btnStop.addEventListener('click', stopCamera);
    btnCapture.addEventListener('click', capture);
    btnRetake.addEventListener('click', retake);
    btnSave.addEventListener('click', save);

    const $dirStatus = document.getElementById('dirStatus');

    // ---- settings panel (hidden until the gear is pressed) ----
    const $settingsBtn = document.getElementById('btnSettings');
    const $settingsPop = document.getElementById('settingsPop');

    const closeSettings = () => {
        $settingsPop.classList.remove('open');
        $settingsBtn.classList.remove('on');
        $settingsBtn.setAttribute('aria-expanded', 'false');
    };

    $settingsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = $settingsPop.classList.toggle('open');
        $settingsBtn.classList.toggle('on', open);
        $settingsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if ($settingsPop.classList.contains('open') && !$settingsPop.contains(e.target)) {
            closeSettings();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSettings();
    });

    // ---- role selector: which folder the pictures go into ----
    document.getElementById('rolePick').addEventListener('click', async (e) => {
        const btn = e.target.closest('.role');
        if (!btn) return;

        $dirStatus.textContent = 'Saving…';
        $dirStatus.className = 'fstat';

        const body = new FormData();
        body.append('role', btn.dataset.role);
        body.append('csrf', csrf);

        try {
            const res = await fetch('settings.php', { method: 'POST', body });
            const data = await res.json();
            if (!data.ok) {
                $dirStatus.textContent = data.error || 'Could not switch.';
                $dirStatus.className = 'fstat bad';
                return;
            }

            document.querySelectorAll('#rolePick .role').forEach((b) => b.classList.remove('on'));
            btn.classList.add('on');
            $dirStatus.textContent = 'Now saving into ' + data.label + '.';
            $dirStatus.className = 'fstat ok';

            // Swap the ID prefix for the new role (no page reload).
            applyPrefix(data.prefix);
            $sid.value = '';
            setHint(data.prefix
                ? 'Type the number after ' + data.prefix
                : 'Type the next student ID.');

            // Load the new folder's pictures in place — no reload, camera stays on.
            await loadRecent();
            $existing.style.display = 'none';
            $existingImg.removeAttribute('src');
        } catch (err) {
            $dirStatus.textContent = 'Failed: ' + err.message;
            $dirStatus.className = 'fstat bad';
        }
    });

    // ---- delete a picture (retake) ----
    document.getElementById('recent').addEventListener('click', async (e) => {
        const btn = e.target.closest('.del');
        if (!btn) return;

        const id = btn.dataset.id;
        if (!confirm('Delete the picture for student ' + id + '? You can retake it after.')) return;

        const body = new FormData();
        body.append('id', id);
        body.append('csrf', csrf);

        try {
            const res = await fetch('delete.php', { method: 'POST', body });
            const data = await res.json();
            if (!data.ok) {
                setStatus(data.error || 'Delete failed.', 'bad');
                return;
            }

            const thumb = btn.closest('.thumb');
            if (thumb) thumb.remove();
            bumpRecentCount();

            setStatus('Deleted ' + id + ' — ready for a retake.', 'ok');

            // If the deleted picture belongs to the ID on screen, reset it.
            if (fullId() === id) {
                $existing.style.display = 'none';
                $existingImg.removeAttribute('src');
                captured = null;
                $shot.classList.remove('active');
                if (stream) {
                    $video.classList.add('active');
                    btnCapture.disabled = false;
                }
                btnRetake.disabled = true;
                btnSave.disabled = true;
                setHint('ID ' + id + ' has no picture now — take a new one.', 'ok');
            }
        } catch (err) {
            setStatus('Delete failed: ' + err.message, 'bad');
        }
    });

    $sid.focus();
})();
</script>
</body>
</html>
