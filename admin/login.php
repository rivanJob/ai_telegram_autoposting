<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\CsrfGuard;
use Autoposter\Guards\RateLimiter;

require __DIR__ . '/bootstrap.php';
$pdo = App::db()->getConnection();
$rateLimiter = new RateLimiter($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfGuard::validate($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $ip . ':' . $email;

        if (!$rateLimiter->allow('login', $key)) {
            $error = 'Too many attempts. Try later.';
            App::audit()->log(null, 'login', 'blocked', ['ip' => $ip, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'email' => $email]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email=:email AND is_active=TRUE');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
                $rateLimiter->clear('login', $key);
                session_regenerate_id(true);
                $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
                $_SESSION['pending_2fa_at'] = time();
                App::audit()->log((int)$user['id'], 'login_password', 'success', ['ip' => $ip, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
                header('Location: /admin/twofactor.php');
                exit;
            }

            $rateLimiter->hit('login', $key);
            App::audit()->log($user['id'] ?? null, 'login_password', 'fail', ['ip' => $ip, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'email' => $email]);
            $error = 'Invalid credentials';
        }
    }
}

require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Admin Login</h1>
<?php if ($error): ?><p style="color:#f87171"><?= e($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>">
<label>Email</label><input name="email" type="email" required>
<label>Password</label><input name="password" type="password" required>
<button>Continue</button>
</form>
<?php renderLayout('Login', ob_get_clean());
