<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $pdo->prepare('INSERT INTO themes(name,description,is_enabled) VALUES(:n,:d,:e)')
        ->execute([':n' => trim((string)$_POST['name']), ':d' => trim((string)$_POST['description']), ':e' => isset($_POST['is_enabled'])]);
    App::audit()->log((int)$_SESSION['admin_user_id'], 'themes_create', 'success', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
}
$rows = $pdo->query('SELECT * FROM themes ORDER BY id DESC')->fetchAll();
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Themes</h1>
<form method="post" class="row"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><input name="name" required placeholder="Theme name"><input name="description" placeholder="Description"><label><input type="checkbox" name="is_enabled" checked> Enabled</label><button>Create Theme</button></form>
<table><tr><th>ID</th><th>Name</th><th>Description</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td><td><?= $r['is_enabled']?'enabled':'disabled' ?></td></tr><?php endforeach; ?></table>
<?php renderLayout('Themes', ob_get_clean());
