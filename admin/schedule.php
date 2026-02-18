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
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Конструктор расписания</h1>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><div class="row"><select name="channel_id"><?php foreach($channels as $c):?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach;?></select><select name="theme_id"><?php foreach($themes as $t):?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach;?></select></div><div class="row"><select name="day_of_week"><?php foreach($days as $i=>$d):?><option value="<?= $i ?>"><?= $d ?></option><?php endforeach;?></select><input type="time" name="slot_time" required></div><div class="row"><select name="post_type"><option>text</option><option>photo</option><option>video</option><option>album</option><option>card</option></select><input name="prompt_override" placeholder="Переопределение промпта для слота"></div><label><input type="checkbox" name="is_enabled" checked> Включено</label><button>Добавить слот</button></form>
<h2>Недельная сетка</h2>
<table><tr><th>День</th><th>Время</th><th>Канал</th><th>Тема</th><th>Тип</th><th>Переопределение</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($days[(int)$r['day_of_week']]??'?') ?></td><td><?= e($r['slot_time']) ?></td><td><?= e($r['channel']) ?></td><td><?= e($r['theme']) ?></td><td><?= e($r['post_type']) ?></td><td><?= e($r['prompt_override']) ?></td></tr><?php endforeach; ?></table>
<?php renderLayout('Конструктор расписания', ob_get_clean());
