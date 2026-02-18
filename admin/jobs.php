<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();

if ($_SERVER['REQUEST_METHOD']==='POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $id=(int)$_POST['job_id'];
    if (($_POST['action'] ?? '')==='retry') {
        $pdo->prepare("UPDATE jobs SET status='NEW', run_at=NOW(), finished_at=NULL, last_error=NULL WHERE id=:id")->execute([':id'=>$id]);
        App::audit()->log((int)$_SESSION['admin_user_id'], 'job_retry', 'success', ['job_id'=>$id]);
    }
}
$status = $_GET['status'] ?? '';
$page=max(1,(int)($_GET['page'] ?? 1));$limit=25;$offset=($page-1)*$limit;
$sql="SELECT j.*,c.name channel,t.name theme FROM jobs j JOIN channels c ON c.id=j.channel_id JOIN themes t ON t.id=j.theme_id";
$params=[];
if ($status) {$sql.=" WHERE j.status=:s";$params[':s']=$status;}
$sql.=" ORDER BY j.id DESC LIMIT $limit OFFSET $offset";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Монитор задач</h1>
<form><select name="status"><option value="">Все</option><option <?= $status==='NEW'?'selected':'' ?>>NEW</option><option <?= $status==='RUNNING'?'selected':'' ?>>RUNNING</option><option <?= $status==='DONE'?'selected':'' ?>>DONE</option><option <?= $status==='ERROR'?'selected':'' ?>>ERROR</option></select><button>Фильтровать</button></form>
<table><tr><th>ID</th><th>Статус</th><th>Канал</th><th>Тема</th><th>Запуск</th><th>Ошибка</th><th>Действия</th></tr><?php foreach($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['status']) ?></td><td><?= e($r['channel']) ?></td><td><?= e($r['theme']) ?></td><td><?= e($r['run_at']) ?></td><td><?= e($r['last_error']) ?></td><td><details><summary>детали</summary><pre><?= e($r['llm_raw']) ?></pre><pre><?= e($r['telegram_raw']) ?></pre></details><form method="post"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><input type="hidden" name="job_id" value="<?= (int)$r['id'] ?>"><button name="action" value="retry">Повторить</button></form></td></tr><?php endforeach;?></table>
<?php renderLayout('Задачи', ob_get_clean());
