<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $id = (int)$_POST['job_id'];
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'retry') {
        $pdo->prepare("UPDATE jobs SET status='NEW', run_at=NOW(), finished_at=NULL, last_error=NULL WHERE id=:id")->execute([':id' => $id]);
        App::audit()->log((int)$_SESSION['admin_user_id'], 'job_retry', 'success', ['job_id' => $id]);
    }

    if ($action === 'run_now') {
        $pdo->prepare("UPDATE jobs SET status='NEW', run_at=NOW() WHERE id=:id")->execute([':id' => $id]);
        App::audit()->log((int)$_SESSION['admin_user_id'], 'job_run_now', 'success', ['job_id' => $id]);
    }
}

$status = (string)($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$sql = "SELECT j.*,c.name channel,t.name theme FROM jobs j JOIN channels c ON c.id=j.channel_id JOIN themes t ON t.id=j.theme_id";
$countSql = "SELECT COUNT(*) FROM jobs";
$params = [];
if ($status) {
    $sql .= " WHERE j.status=:s";
    $countSql .= " WHERE status=:s";
    $params[':s'] = $status;
}
$sql .= " ORDER BY j.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

require __DIR__ . '/layout.php';
ob_start(); ?>
<div class="panel stack">
    <form class="row" method="get">
        <div>
            <label>Статус</label>
            <select name="status">
                <option value="">Все</option>
                <?php foreach (['NEW', 'RUNNING', 'DONE', 'ERROR'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;align-items:flex-end;gap:8px;">
            <button>Фильтровать</button>
            <a href="/admin/jobs.php" class="chip" style="text-decoration:none;padding:10px 14px;">Сбросить</a>
        </div>
    </form>
    <div class="hint">Найдено задач: <?= $total ?> · Страница <?= $page ?> из <?= $totalPages ?></div>
</div>

<div class="panel">
<table>
    <tr><th>ID</th><th>Статус</th><th>Канал</th><th>Тема</th><th>Запуск</th><th>Ошибка</th><th>Действия</th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><span class="status <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td><?= e($r['channel']) ?></td>
            <td><?= e($r['theme']) ?></td>
            <td class="mono"><?= e($r['run_at']) ?></td>
            <td class="error"><?= e((string)$r['last_error']) ?></td>
            <td>
                <button type="button" class="secondary" data-open-details
                    data-id="<?= (int)$r['id'] ?>"
                    data-llm="<?= e((string)$r['llm_raw']) ?>"
                    data-telegram="<?= e((string)$r['telegram_raw']) ?>">Детали</button>
                <div style="height:8px"></div>
                <form method="post" style="display:grid;gap:8px;">
                    <input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>">
                    <input type="hidden" name="job_id" value="<?= (int)$r['id'] ?>">
                    <button name="action" value="retry">Retry</button>
                    <button class="secondary" name="action" value="run_now">Run now</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<div class="panel" style="display:flex;justify-content:space-between;align-items:center;">
    <a class="chip" style="text-decoration:none;<?= $page <= 1 ? 'opacity:.4;pointer-events:none;' : '' ?>" href="?status=<?= urlencode($status) ?>&page=<?= $page - 1 ?>">← Назад</a>
    <a class="chip" style="text-decoration:none;<?= $page >= $totalPages ? 'opacity:.4;pointer-events:none;' : '' ?>" href="?status=<?= urlencode($status) ?>&page=<?= $page + 1 ?>">Вперёд →</a>
</div>

<dialog id="job-details" style="width:min(900px,95vw);background:#0b1220;border:1px solid #334155;color:#e2e8f0;border-radius:12px;">
    <h3>Job <span id="job-id"></span></h3>
    <h4>Сырой ответ LLM</h4>
    <pre id="job-llm" style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#020617;padding:10px;border-radius:8px;"></pre>
    <h4>Сырой ответ Telegram API</h4>
    <pre id="job-telegram" style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#020617;padding:10px;border-radius:8px;"></pre>
    <form method="dialog"><button class="secondary">Закрыть</button></form>
</dialog>
<script>
const modal = document.getElementById('job-details');
document.querySelectorAll('[data-open-details]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('job-id').textContent = '#' + btn.dataset.id;
    document.getElementById('job-llm').textContent = btn.dataset.llm || 'пусто';
    document.getElementById('job-telegram').textContent = btn.dataset.telegram || 'пусто';
    modal.showModal();
  });
});
</script>
<?php renderLayout('Монитор задач', ob_get_clean());
