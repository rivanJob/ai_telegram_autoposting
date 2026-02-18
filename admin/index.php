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
    'jobs_running' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status='RUNNING'")->fetchColumn(),
    'jobs_done_24h' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status='DONE' AND finished_at >= NOW() - INTERVAL '24 hour'")->fetchColumn(),
    'jobs_error' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status='ERROR'")->fetchColumn(),
];
$nextRuns = $pdo->query("SELECT j.id,c.name AS channel,t.name AS theme,j.post_type,j.run_at FROM jobs j JOIN channels c ON c.id=j.channel_id JOIN themes t ON t.id=j.theme_id WHERE j.status='NEW' ORDER BY j.run_at ASC LIMIT 10")->fetchAll();
$queueHealth = $stats['jobs_error'] > 0 ? 'Требует внимания' : 'Стабильно';

require __DIR__ . '/layout.php';
ob_start(); ?>
<div class="panel">
    <div class="cards">
        <div class="card"><div class="chip">Каналы</div><div class="metric"><?= $stats['channels'] ?></div></div>
        <div class="card"><div class="chip">Темы</div><div class="metric"><?= $stats['themes'] ?></div></div>
        <div class="card"><div class="chip">Очередь NEW</div><div class="metric"><?= $stats['jobs_new'] ?></div></div>
        <div class="card"><div class="chip">RUNNING сейчас</div><div class="metric"><?= $stats['jobs_running'] ?></div></div>
        <div class="card"><div class="chip">DONE за 24ч</div><div class="metric"><?= $stats['jobs_done_24h'] ?></div></div>
        <div class="card"><div class="chip">Ошибки</div><div class="metric"><?= $stats['jobs_error'] ?></div></div>
    </div>
</div>
<div class="panel">
    <strong>Состояние очереди:</strong> <?= e($queueHealth) ?>
    <p class="hint">Если растёт число ERROR, перейдите в «Монитор задач» и выполните retry/run-now для проблемных задач.</p>
</div>
<div class="panel">
    <h3>Ближайшие запуски</h3>
    <table>
        <tr><th>ID</th><th>Канал</th><th>Тема</th><th>Тип</th><th>Время запуска</th></tr>
        <?php foreach ($nextRuns as $row): ?>
            <tr>
                <td>#<?= (int)$row['id'] ?></td>
                <td><?= e($row['channel']) ?></td>
                <td><?= e($row['theme']) ?></td>
                <td><span class="chip"><?= e($row['post_type']) ?></span></td>
                <td class="mono"><?= e($row['run_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php renderLayout('Панель управления', ob_get_clean());
