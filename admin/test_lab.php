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
        $message='Sent successfully';
    }
}
require __DIR__ . '/layout.php';
ob_start(); ?>
<h1>Test Lab</h1><?php if($message):?><p><?= e($message) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>"><label>Channel</label><select name="chat_id"><?php foreach($channels as $c):?><option value="<?= e($c['chat_id']) ?>"><?= e($c['name']) ?></option><?php endforeach;?></select><label>Theme</label><select name="theme_id"><?php foreach($themes as $t):?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach;?></select><label>Prompt</label><textarea name="prompt" rows="8" placeholder="Generate strict JSON for post type text/photo/video/album/card" required></textarea><button name="action" value="generate">Generate + Validate</button><button name="action" value="send">Send Preview</button></form>
<h2>Telegram-like Preview JSON</h2><pre><?= e($preview) ?></pre>
<?php renderLayout('Test Lab', ob_get_clean());
