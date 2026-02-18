<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();
$stats = [
    'channels' => (int)$pdo->query('SELECT COUNT(*) FROM channels')->fetchColumn(),
    'themes' => (int)$pdo->query('SELECT COUNT(*) FROM themes')->fetchColumn(),
    'jobs_new' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status='NEW'")->fetchColumn(),
    'jobs_error' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status='ERROR'")->fetchColumn(),
];
$nextRuns = $pdo->query("SELECT j.id,c.name AS channel,t.name AS theme,j.run_at FROM jobs j JOIN channels c ON c.id=j.channel_id JOIN themes t ON t.id=j.theme_id WHERE j.status='NEW' ORDER BY j.run_at ASC LIMIT 10")->fetchAll();

require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Панель</h1>
<div class="cards">
<div class="card"><h3>Каналы</h3><p><?= $stats['channels'] ?></p></div>
<div class="card"><h3>Темы</h3><p><?= $stats['themes'] ?></p></div>
<div class="card"><h3>Задачи в очереди</h3><p><?= $stats['jobs_new'] ?></p></div>
<div class="card"><h3>Задачи с ошибкой</h3><p><?= $stats['jobs_error'] ?></p></div>
</div>
<h2>Ближайшие запуски</h2>
<table><tr><th>ID</th><th>Канал</th><th>Тема</th><th>Время запуска</th></tr>
<?php foreach ($nextRuns as $row): ?><tr><td><?= (int)$row['id'] ?></td><td><?= e($row['channel']) ?></td><td><?= e($row['theme']) ?></td><td><?= e($row['run_at']) ?></td></tr><?php endforeach; ?>
</table>
<?php renderLayout('Панель', ob_get_clean());
