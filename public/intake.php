<?php
/* ============================================================
   JJ's Firewood Intake - the ONLY file inside htdocs.
   Everything else (the app, your data, the passcode) lives in
   the folder below, well outside any web-served directory.

   If you ever move the site, change this one line.
   ============================================================ */

/* Find the private folder by walking up from this file, so the app works
   whatever the site user or folder layout happens to be called. */
$JJ_PRIVATE = "/opt/jj-intake/intake-data";
$dir = __DIR__;
for ($i = 0; $i < 6; $i++) {
    if (is_readable($dir . '/intake-data/lib.php')) { $JJ_PRIVATE = $dir . '/intake-data'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if ($JJ_PRIVATE === null) {
    http_response_code(500);
    exit('Could not find the intake-data folder. It should sit in the site user home folder, above htdocs.');
}
require $JJ_PRIVATE . '/lib.php';

$auth   = jj_auth_load();
$action = isset($_GET['a']) ? $_GET['a'] : '';

/* ---------- wrong-passcode lockout ----------
   Five bad tries and the door shuts for 15 minutes, counted per IP. */
define('JJ_MAX_TRIES', 5);
define('JJ_LOCK_SECS', 900);
define('JJ_TRIES_FILE', $JJ_PRIVATE . '/attempts.json');

function jj_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    return preg_replace('/[^0-9a-fA-F:.]/', '', $ip);
}
function jj_tries_load() {
    if (!is_readable(JJ_TRIES_FILE)) return [];
    $d = json_decode(file_get_contents(JJ_TRIES_FILE), true);
    return is_array($d) ? $d : [];
}
function jj_tries_save($d) {
    @file_put_contents(JJ_TRIES_FILE, json_encode($d), LOCK_EX);
}
/* seconds still to wait, or 0 if not locked out */
function jj_locked_for() {
    $d = jj_tries_load();
    $ip = jj_client_ip();
    if (!isset($d[$ip]['until'])) return 0;
    $left = (int)$d[$ip]['until'] - time();
    return $left > 0 ? $left : 0;
}
function jj_note_fail() {
    $d = jj_tries_load();
    $ip = jj_client_ip();
    $now = time();
    /* drop anything older than the window so counts don't build up forever */
    foreach ($d as $k => $v) {
        if (empty($v['seen']) || $now - $v['seen'] > 86400) unset($d[$k]);
    }
    $c = isset($d[$ip]['count']) ? (int)$d[$ip]['count'] + 1 : 1;
    $d[$ip] = ['count' => $c, 'seen' => $now];
    if ($c >= JJ_MAX_TRIES) {
        $d[$ip]['until'] = $now + JJ_LOCK_SECS;
        $d[$ip]['count'] = 0;
    }
    jj_tries_save($d);
}
function jj_note_success() {
    $d = jj_tries_load();
    unset($d[jj_client_ip()]);
    jj_tries_save($d);
}

/* ---------- lock: drop the session so the passcode is needed again ---------- */
if ($action === 'lock') {
    setcookie(JJ_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

/* ---------- data endpoint ---------- */
if ($action === 'sync') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (!jj_is_authed($auth)) {
        http_response_code(403); echo json_encode(['error' => 'not authorised']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 5000000) {
            http_response_code(400); echo json_encode(['error' => 'bad payload']); exit;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['woods'])) {
            http_response_code(400); echo json_encode(['error' => 'not intake data']); exit;
        }
        if (file_exists(JJ_DATA_FILE)) { @copy(JJ_DATA_FILE, JJ_DATA_FILE . '.prev'); }
        if (file_put_contents(JJ_DATA_FILE, $raw, LOCK_EX) === false) {
            http_response_code(500); echo json_encode(['error' => 'could not write']); exit;
        }
        echo json_encode(['ok' => true, 'savedAt' => time()]);
        exit;
    }
    if (file_exists(JJ_DATA_FILE)) {
        echo json_encode(['ok' => true, 'data' => json_decode(file_get_contents(JJ_DATA_FILE), true)]);
    } else {
        echo json_encode(['ok' => true, 'data' => null]);
    }
    exit;
}

/* ---------- passcode change ---------- */
if ($action === 'passcode') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (!jj_is_authed($auth)) {
        http_response_code(403); echo json_encode(['error' => 'not authorised']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['error' => 'post only']); exit;
    }
    $in  = json_decode(file_get_contents('php://input'), true);
    $new = isset($in['passcode']) ? trim($in['passcode']) : '';
    if (strlen($new) < 4 || strlen($new) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Passcode must be at least 4 characters']);
        exit;
    }
    $signOutOthers = !empty($in['signOutOthers']);
    $gen = $signOutOthers ? ((int)$auth['gen'] + 1) : (int)$auth['gen'];
    jj_auth_save(['hash' => password_hash($new, PASSWORD_DEFAULT), 'gen' => $gen]);
    jj_set_cookie($gen);
    echo json_encode(['ok' => true, 'signedOutOthers' => $signOutOthers]);
    exit;
}

/* ---------- unlock ---------- */
$error = false;
$lockLeft = jj_locked_for();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    if ($lockLeft > 0) {
        $error = true;
    } elseif (password_verify(trim($_POST['code']), $auth['hash'])) {
        jj_note_success();
        jj_set_cookie($auth['gen']);
        header('Location: /intake.php');
        exit;
    } else {
        jj_note_fail();
        $lockLeft = jj_locked_for();
        $error = true;
    }
}

/* ---------- serve the app ---------- */
if (jj_is_authed($auth)) {
    if (!is_readable(JJ_APP_FILE)) { http_response_code(500); exit('App file missing.'); }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    readfile(JJ_APP_FILE);
    exit;
}

http_response_code(401);
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FBF8F2">
<title>Firewood Intake</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..700&family=Inter+Tight:wght@400..700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBF8F2;color:#22271F;font-family:"Inter Tight",system-ui,sans-serif;
min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.box{width:100%;max-width:320px;text-align:center}
h1{font-family:"Fraunces",serif;font-size:1.6rem;font-weight:600;margin-bottom:8px}
h1 span{color:#4F7A52}
p{color:#7C866F;font-size:.85rem;line-height:1.5;margin-bottom:24px}
input{width:100%;font-family:inherit;font-size:1.1rem;color:#22271F;text-align:center;
background:#fff;border:1.5px solid #DDD7C7;border-radius:14px;padding:14px;min-height:52px}
input:focus{outline:none;border-color:#4F7A52}
button{width:100%;margin-top:12px;background:#4F7A52;color:#fff;border:none;
border-radius:14px;padding:16px;font-size:1.02rem;font-weight:700;font-family:inherit}
.err{color:#C4623F;font-size:.85rem;margin-top:14px}
</style>
</head>
<body>
<div class="box">
  <h1>JJ's <span>Intake</span></h1>
  <p>Enter the passcode once and this device stays signed in.</p>
  <?php if ($lockLeft <= 0): ?>
  <form method="post">
    <input name="code" type="password" autocomplete="current-password" placeholder="Passcode" autofocus>
    <button type="submit">Unlock</button>
  </form>
  <?php endif; ?>
  <?php if ($lockLeft > 0): ?>
    <div class="err">Too many wrong tries. Locked for <?php echo ceil($lockLeft / 60); ?> more minute<?php echo ceil($lockLeft / 60) === 1 ? '' : 's'; ?>.</div>
  <?php elseif ($error): ?>
    <div class="err">Wrong passcode. Try again.</div>
  <?php endif; ?>
</div>
</body>
</html>
