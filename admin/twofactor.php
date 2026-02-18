<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\CsrfGuard;
use Autoposter\Services\TotpService;

require __DIR__ . '/bootstrap.php';
$pdo = App::db()->getConnection();
$totp = new TotpService();
$error = null;

$pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$pendingId) {
    header('Location: /admin/login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id=:id');
$stmt->execute([':id' => $pendingId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: /admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfGuard::validate($_POST['_csrf'] ?? null)) {
        $error = 'Недействительный CSRF';
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        if ($totp->verifyCode($user['totp_secret'], $code)) {
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = (int)$user['id'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['created_at'] = time();
            $_SESSION['last_seen'] = time();
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_at']);

            App::audit()->log((int)$user['id'], 'login_2fa', 'success', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
            header('Location: /admin/index.php');
            exit;
        }

        App::audit()->log((int)$user['id'], 'login_2fa', 'fail', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
        $error = 'Неверный TOTP-код';
    }
}

require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Двухфакторная проверка</h1>
<?php if ($user['must_setup_totp']): ?>
<p>Отсканируйте этот URI в приложении-аутентификаторе:</p>
<pre><?= e($totp->otpauthUri('AutoPoster', $user['email'], $user['totp_secret'])) ?></pre>
<?php endif; ?>
<?php if ($error): ?><p style="color:#f87171"><?= e($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>">
<label>Код аутентификатора</label><input name="code" required pattern="\d{6}">
<button>Войти</button>
</form>
<?php renderLayout('2FA', ob_get_clean());
