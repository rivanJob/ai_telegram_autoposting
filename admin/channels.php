<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $name = trim((string)$_POST['name']);
    $chatId = trim((string)$_POST['chat_id']);
    $pdo->prepare('INSERT INTO channels(name,chat_id,is_enabled) VALUES(:n,:c,:e) ON CONFLICT(chat_id) DO UPDATE SET name=EXCLUDED.name,is_enabled=EXCLUDED.is_enabled')
        ->execute([':n' => $name, ':c' => $chatId, ':e' => isset($_POST['is_enabled'])]);
    App::audit()->log((int)$_SESSION['admin_user_id'], 'channels_upsert', 'success', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
}
$rows = $pdo->query('SELECT * FROM channels ORDER BY id DESC')->fetchAll();
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Каналы</h1>
<form method="post" class="row"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><input name="name" placeholder="Название" required><input name="chat_id" placeholder="@канал или -100..." required><label><input type="checkbox" name="is_enabled" checked> Включено</label><button>Сохранить канал</button></form>
<table><tr><th>ID</th><th>Название</th><th>ID чата</th><th>Статус</th></tr><?php foreach($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['chat_id']) ?></td><td><span class="pill"><?= $r['is_enabled']?'включен':'выключен' ?></span></td></tr><?php endforeach; ?></table>
<?php renderLayout('Каналы', ob_get_clean());
