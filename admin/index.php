<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Guards\CsrfGuard;
use App\Guards\RateLimiter;
use App\Guards\Totp;
use App\Services\AuditService;

require_once __DIR__ . '/../src/Core/bootstrap.php';

$secure = Env::bool('SESSION_SECURE', true);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$allow = array_filter(array_map('trim', explode(',', Env::get('ADMIN_IP_ALLOWLIST', '') ?? '')));
if ($allow && !in_array($ip, $allow, true)) {
    http_response_code(403);
    exit('Forbidden');
}

$db = Database::connection();
$path = $_GET['page'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'];

$requireAuth = static function () use ($ip): int {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ?page=login');
        exit;
    }
    $idleTimeout = Env::int('SESSION_IDLE_TIMEOUT', 1800);
    $absTimeout = Env::int('SESSION_ABSOLUTE_TIMEOUT', 43200);
    $loginAt = (int) ($_SESSION['login_at'] ?? time());
    $lastSeen = (int) ($_SESSION['last_seen'] ?? time());
    if (time() - $lastSeen > $idleTimeout || time() - $loginAt > $absTimeout) {
        session_destroy();
        header('Location: ?page=login&expired=1');
        exit;
    }
    $_SESSION['last_seen'] = time();
    return (int) $_SESSION['admin_id'];
};

if ($path === 'login' && $method === 'POST') {
    $lock = RateLimiter::hit('admin_login', $ip);
    if ($lock > 0) {
        exit('Too many attempts. Retry later.');
    }
    if (!CsrfGuard::validate($_POST['csrf'] ?? null)) {
        exit('Invalid CSRF token');
    }

    $stmt = $db->prepare('SELECT * FROM admin_users WHERE username=:u AND enabled=true');
    $stmt->execute(['u' => $_POST['username'] ?? '']);
    $user = $stmt->fetch();

    $ok = $user && password_verify((string) ($_POST['password'] ?? ''), (string) $user['password_hash'])
        && Totp::verify((string) $user['totp_secret'], preg_replace('/\D/', '', (string) ($_POST['totp'] ?? '')));

    AuditService::log($user['id'] ?? null, $ok ? 'auth.login.success' : 'auth.login.failed', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', ['username' => $_POST['username'] ?? '']);

    if (!$ok) {
        exit('Invalid credentials or TOTP code.');
    }

    RateLimiter::clear('admin_login', $ip);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['login_at'] = time();
    $_SESSION['last_seen'] = time();
    header('Location: ?page=dashboard');
    exit;
}

if ($path === 'logout') {
    $id = $_SESSION['admin_id'] ?? null;
    if ($id) {
        AuditService::log((int) $id, 'auth.logout', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');
    }
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($path === 'setup-2fa') {
    $adminId = $requireAuth();
    $admin = $db->query('SELECT * FROM admin_users WHERE id=' . (int) $adminId)->fetch();
    if ($method === 'POST') {
        if (!CsrfGuard::validate($_POST['csrf'] ?? null)) {
            exit('Invalid CSRF token');
        }
        if (!Totp::verify((string) $admin['totp_secret'], (string) ($_POST['totp'] ?? ''))) {
            exit('Invalid TOTP');
        }
        $db->prepare('UPDATE admin_users SET totp_confirmed_at=NOW() WHERE id=:id')->execute(['id' => $adminId]);
        header('Location: ?page=dashboard');
        exit;
    }
    echo '<h1>Confirm 2FA</h1><p>Secret: ' . htmlspecialchars((string) $admin['totp_secret']) . '</p><p>URI: ' . htmlspecialchars(Totp::qrUri('AutoPoster', (string) $admin['username'], (string) $admin['totp_secret'])) . '</p><form method="post"><input type="hidden" name="csrf" value="' . CsrfGuard::token() . '"><input name="totp" placeholder="123456"><button>Verify</button></form>';
    exit;
}

if ($path === 'login') {
    echo '<h1>Admin Login</h1><form method="post"><input type="hidden" name="csrf" value="' . CsrfGuard::token() . '"><input name="username" placeholder="Username"><input type="password" name="password" placeholder="Password"><input name="totp" placeholder="TOTP"><button>Login</button></form>';
    exit;
}

$adminId = $requireAuth();
$admin = $db->query('SELECT * FROM admin_users WHERE id=' . (int) $adminId)->fetch();
if (!$admin['totp_confirmed_at']) {
    header('Location: ?page=setup-2fa');
    exit;
}

if ($method === 'POST') {
    if (!CsrfGuard::validate($_POST['csrf'] ?? null)) {
        exit('Invalid CSRF token');
    }
    switch ($path) {
        case 'channels':
            $db->prepare('INSERT INTO channels(name,target,enabled,created_at,updated_at) VALUES(:n,:t,true,NOW(),NOW())')->execute([
                'n' => trim((string) $_POST['name']),
                't' => trim((string) $_POST['target']),
            ]);
            AuditService::log($adminId, 'channel.create', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', ['name' => $_POST['name'] ?? '']);
            break;
        case 'themes':
            $db->prepare('INSERT INTO themes(name,description,enabled,created_at,updated_at) VALUES(:n,:d,true,NOW(),NOW())')->execute([
                'n' => trim((string) $_POST['name']),
                'd' => trim((string) $_POST['description']),
            ]);
            AuditService::log($adminId, 'theme.create', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', ['name' => $_POST['name'] ?? '']);
            break;
        case 'prompts':
            $v = (int) $db->query('SELECT COALESCE(MAX(version),0)+1 AS v FROM prompts WHERE theme_id=' . (int) $_POST['theme_id'])->fetch()['v'];
            $db->prepare('INSERT INTO prompts(theme_id,version,title,body,is_active,created_by,created_at) VALUES(:theme,:v,:title,:body,true,:admin,NOW())')->execute([
                'theme' => (int) $_POST['theme_id'], 'v' => $v, 'title' => trim((string) $_POST['title']), 'body' => trim((string) $_POST['body']), 'admin' => $adminId,
            ]);
            AuditService::log($adminId, 'prompt.create', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', ['theme_id' => (int) $_POST['theme_id'], 'version' => $v]);
            break;
    }
    header('Location: ?page=' . urlencode($path));
    exit;
}

$channels = $db->query('SELECT * FROM channels ORDER BY id DESC LIMIT 100')->fetchAll();
$themes = $db->query('SELECT * FROM themes ORDER BY id DESC LIMIT 100')->fetchAll();
$prompts = $db->query('SELECT p.*, t.name as theme_name FROM prompts p JOIN themes t ON t.id=p.theme_id ORDER BY p.id DESC LIMIT 100')->fetchAll();
$jobs = $db->query('SELECT * FROM jobs ORDER BY id DESC LIMIT 50')->fetchAll();
$audit = $db->query('SELECT a.*, u.username FROM audit_log a LEFT JOIN admin_users u ON u.id=a.admin_user_id ORDER BY a.id DESC LIMIT 100')->fetchAll();

?><!doctype html><html><head><meta charset="utf-8"><title>AutoPoster Admin</title><style>
body{font-family:Inter,Arial;background:#0b1020;color:#e5ecff;margin:0;display:flex}.side{width:240px;background:#121a33;min-height:100vh;padding:20px}.side a{display:block;color:#b5c7ff;text-decoration:none;padding:10px;border-radius:8px}.side a:hover{background:#1d2a50}.main{flex:1;padding:24px}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.card{background:#121a33;padding:16px;border-radius:12px}.panel{background:#121a33;padding:16px;border-radius:12px;margin-top:16px}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #243259}input,textarea,select,button{background:#0f1730;color:#e5ecff;border:1px solid #32477b;border-radius:8px;padding:8px}</style></head><body>
<div class="side"><h2>AutoPoster</h2><a href="?page=dashboard">Dashboard</a><a href="?page=channels">Channels</a><a href="?page=themes">Themes</a><a href="?page=prompts">Prompt Manager</a><a href="?page=schedule">Schedule Builder</a><a href="?page=jobs">Jobs Monitor</a><a href="?page=test-lab">Test Lab</a><a href="?page=audit">Audit Log</a><a href="?page=logout">Logout</a></div>
<div class="main">
<h1><?=htmlspecialchars(ucwords(str_replace('-', ' ', $path)))?></h1>
<div class="cards"><div class="card">Channels: <?=count($channels)?></div><div class="card">Themes: <?=count($themes)?></div><div class="card">Jobs: <?=count($jobs)?></div><div class="card">Audit events: <?=count($audit)?></div></div>
<?php if ($path==='channels'): ?><div class="panel"><h3>Add channel</h3><form method="post"><input type="hidden" name="csrf" value="<?=CsrfGuard::token()?>"><input name="name" placeholder="Name" required><input name="target" placeholder="@channel or -100..." required><button>Save</button></form><table><tr><th>ID</th><th>Name</th><th>Target</th></tr><?php foreach($channels as $c): ?><tr><td><?=$c['id']?></td><td><?=htmlspecialchars($c['name'])?></td><td><?=htmlspecialchars($c['target'])?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($path==='themes'): ?><div class="panel"><h3>Add theme</h3><form method="post"><input type="hidden" name="csrf" value="<?=CsrfGuard::token()?>"><input name="name" required><input name="description"><button>Save</button></form><table><tr><th>ID</th><th>Name</th><th>Description</th></tr><?php foreach($themes as $t): ?><tr><td><?=$t['id']?></td><td><?=htmlspecialchars($t['name'])?></td><td><?=htmlspecialchars($t['description'])?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($path==='prompts'): ?><div class="panel"><h3>Prompt manager with versioning</h3><p>Variables: {{audience}}, {{tone}}, {{category}}, {{channel_name}}</p><form method="post"><input type="hidden" name="csrf" value="<?=CsrfGuard::token()?>"><select name="theme_id"><?php foreach($themes as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select><input name="title" placeholder="Prompt title"><textarea name="body" rows="8" cols="80" placeholder="Strict JSON prompt"></textarea><button>Publish new version</button></form><table><tr><th>Theme</th><th>Version</th><th>Title</th><th>Created</th></tr><?php foreach($prompts as $p): ?><tr><td><?=htmlspecialchars($p['theme_name'])?></td><td><?=$p['version']?></td><td><?=htmlspecialchars($p['title'])?></td><td><?=$p['created_at']?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($path==='jobs'): ?><div class="panel"><h3>Jobs monitor</h3><table><tr><th>ID</th><th>Status</th><th>Channel</th><th>Scheduled</th><th>Error</th></tr><?php foreach($jobs as $j): ?><tr><td><?=$j['id']?></td><td><?=$j['status']?></td><td><?=$j['channel_id']?></td><td><?=$j['scheduled_at']?></td><td><?=htmlspecialchars((string)$j['last_error'])?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($path==='schedule'): ?><div class="panel"><h3>Visual schedule builder</h3><p>Weekly grid, campaigns, blackouts and drag-drop slots are modeled via <code>schedule_slots</code>, <code>schedule_campaigns</code>, <code>schedule_exceptions</code> tables. Add/edit through SQL/API extension hooks for now.</p></div><?php endif; ?>
<?php if ($path==='test-lab'): ?><div class="panel"><h3>Test Lab</h3><p>Use <code>bin/health.php</code> and <code>bin/worker.php --once</code> for generate→validate→preview→send workflow. JSON editor and telegram-like preview can be extended in this page.</p></div><?php endif; ?>
<?php if ($path==='audit'): ?><div class="panel"><h3>Audit log viewer</h3><table><tr><th>Time</th><th>User</th><th>Action</th><th>IP</th></tr><?php foreach($audit as $a): ?><tr><td><?=$a['created_at']?></td><td><?=htmlspecialchars((string)$a['username'])?></td><td><?=htmlspecialchars($a['action'])?></td><td><?=htmlspecialchars($a['ip'])?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php if ($path==='dashboard'): ?><div class="panel"><h3>Operations</h3><ul><li>Scheduler service builds jobs idempotently.</li><li>Worker executes strict JSON pipeline and posts to Telegram types text/photo/video/album/card.</li><li>WireGuard enforcement mode configured by env and installer.</li></ul></div><?php endif; ?>
</div></body></html>
