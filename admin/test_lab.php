<?php

declare(strict_types=1);

use Autoposter\Clients\GrokClient;
use Autoposter\Clients\TelegramClient;
use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;
use Autoposter\Guards\CsrfGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();
$channels=$pdo->query('SELECT id,name,chat_id FROM channels WHERE is_enabled=TRUE')->fetchAll();
$themes=$pdo->query('SELECT id,name FROM themes WHERE is_enabled=TRUE')->fetchAll();
$message='';$preview='';
if ($_SERVER['REQUEST_METHOD']==='POST' && CsrfGuard::validate($_POST['_csrf'] ?? null)) {
    $action=$_POST['action'] ?? '';
    if ($action==='generate') {
        $prompt=(string)$_POST['prompt'];
        $res=(new GrokClient())->generate($prompt);
        $preview=json_encode($res['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        $_SESSION['test_lab_json']=$res['json'];
    }
    if ($action==='send' && isset($_SESSION['test_lab_json'])) {
        $chatId=(string)$_POST['chat_id'];
        (new TelegramClient())->publish($chatId, $_SESSION['test_lab_json']);
        $message='Успешно отправлено';
    }
}
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Тестовая лаборатория</h1><?php if($message):?><p><?= e($message) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><label>Канал</label><select name="chat_id"><?php foreach($channels as $c):?><option value="<?= e($c['chat_id']) ?>"><?= e($c['name']) ?></option><?php endforeach;?></select><label>Тема</label><select name="theme_id"><?php foreach($themes as $t):?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach;?></select><label>Промпт</label><textarea name="prompt" rows="8" placeholder="Сгенерируйте строгий JSON для типа поста text/photo/video/album/card" required></textarea><button name="action" value="generate">Сгенерировать + проверить</button><button name="action" value="send">Отправить предпросмотр</button></form>
<h2>JSON предпросмотра в стиле Telegram</h2><pre><?= e($preview) ?></pre>
<?php renderLayout('Тестовая лаборатория', ob_get_clean());
