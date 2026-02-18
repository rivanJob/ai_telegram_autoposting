<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $themeId = (int)$_POST['theme_id'];
    $version = (int)$pdo->query("SELECT COALESCE(MAX(version),0)+1 FROM prompts WHERE theme_id=" . $themeId)->fetchColumn();
    $pdo->prepare('INSERT INTO prompts(theme_id,version,title,body,is_active,created_by) VALUES(:t,:v,:title,:body,TRUE,:u)')
        ->execute([':t' => $themeId, ':v' => $version, ':title' => trim((string)$_POST['title']), ':body' => trim((string)$_POST['body']), ':u' => $_SESSION['admin_user_id']]);
    App::audit()->log((int)$_SESSION['admin_user_id'], 'prompt_create', 'success', ['theme_id' => $themeId]);
}
$themes = $pdo->query('SELECT id,name FROM themes ORDER BY name')->fetchAll();
$rows = $pdo->query('SELECT p.*,t.name AS theme FROM prompts p JOIN themes t ON t.id=p.theme_id ORDER BY p.id DESC LIMIT 100')->fetchAll();
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Менеджер промптов</h1>
<p>Подсказка по переменным: <code>{{audience}}</code> <code>{{tone}}</code> <code>{{category}}</code> <code>{{season}}</code></p>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><div class="row"><select name="theme_id"><?php foreach($themes as $t):?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach;?></select><input name="title" placeholder="Название промпта" required></div><textarea name="body" rows="8" placeholder="Промпт строго в формате JSON" required></textarea><button>Сохранить новую версию</button></form>
<table><tr><th>ID</th><th>Тема</th><th>Версия</th><th>Название</th><th>Создано</th></tr><?php foreach($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['theme']) ?></td><td><?= (int)$r['version'] ?></td><td><?= e($r['title']) ?></td><td><?= e($r['created_at']) ?></td></tr><?php endforeach; ?></table>
<?php renderLayout('Менеджер промптов', ob_get_clean());
