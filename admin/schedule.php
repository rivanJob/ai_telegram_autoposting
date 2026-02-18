<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $pdo->prepare('INSERT INTO schedule_slots(channel_id,theme_id,day_of_week,slot_time,post_type,prompt_override,is_enabled) VALUES(:c,:t,:dow,:tm,:pt,:po,:e)')
        ->execute([
            ':c' => (int)$_POST['channel_id'], ':t' => (int)$_POST['theme_id'], ':dow' => (int)$_POST['day_of_week'], ':tm' => $_POST['slot_time'],
            ':pt' => $_POST['post_type'], ':po' => trim((string)$_POST['prompt_override']), ':e' => isset($_POST['is_enabled'])
        ]);
    App::audit()->log((int)$_SESSION['admin_user_id'], 'schedule_create', 'success', []);
}
$channels = $pdo->query('SELECT id,name FROM channels WHERE is_enabled=TRUE ORDER BY name')->fetchAll();
$themes = $pdo->query('SELECT id,name FROM themes WHERE is_enabled=TRUE ORDER BY name')->fetchAll();
$rows = $pdo->query('SELECT s.*,c.name channel,t.name theme FROM schedule_slots s JOIN channels c ON c.id=s.channel_id JOIN themes t ON t.id=s.theme_id ORDER BY day_of_week,slot_time')->fetchAll();
$days=['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
$dayStats = array_fill_keys(array_keys($days), 0);
foreach ($rows as $r) { $dayStats[(int)$r['day_of_week']]++; }

require __DIR__ . '/layout.php';
ob_start(); ?>
<div class="panel stack">
    <div class="hint">Редактор создаёт недельные слоты публикаций. Для каждой записи укажите канал, тему, день/время и тип поста.</div>
    <form method="post" class="stack">
        <input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>">
        <div class="row">
            <div><label>Канал</label><select name="channel_id"><?php foreach($channels as $c):?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach;?></select></div>
            <div><label>Тема</label><select name="theme_id"><?php foreach($themes as $t):?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach;?></select></div>
        </div>
        <div class="row">
            <div><label>День недели</label><select name="day_of_week"><?php foreach($days as $i=>$d):?><option value="<?= $i ?>"><?= $d ?></option><?php endforeach;?></select></div>
            <div><label>Время</label><input type="time" name="slot_time" required></div>
        </div>
        <div class="row">
            <div><label>Тип поста</label><select name="post_type"><option>text</option><option>photo</option><option>video</option><option>album</option><option>card</option></select></div>
            <div><label>Переопределение промпта</label><input name="prompt_override" placeholder="Опционально, только для этого слота"></div>
        </div>
        <label><input type="checkbox" name="is_enabled" checked style="width:auto"> Слот активен</label>
        <button>Добавить слот</button>
    </form>
</div>

<div class="panel">
    <h3>Нагрузка по дням</h3>
    <?php foreach($days as $i=>$d): ?><span class="chip"><?= e($d) ?>: <?= $dayStats[$i] ?></span><?php endforeach; ?>
</div>

<div class="panel">
    <h3>Недельная сетка</h3>
    <table>
        <tr><th>День</th><th>Время</th><th>Канал</th><th>Тема</th><th>Тип</th><th>Переопределение</th><th>Активен</th></tr>
        <?php foreach($rows as $r): ?>
            <tr>
                <td><?= e($days[(int)$r['day_of_week']] ?? '?') ?></td>
                <td class="mono"><?= e($r['slot_time']) ?></td>
                <td><?= e($r['channel']) ?></td>
                <td><?= e($r['theme']) ?></td>
                <td><span class="chip"><?= e($r['post_type']) ?></span></td>
                <td><?= e((string)$r['prompt_override']) ?></td>
                <td><?= (bool)$r['is_enabled'] ? 'Да' : 'Нет' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php renderLayout('Конструктор расписания', ob_get_clean());
