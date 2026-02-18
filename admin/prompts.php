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
<div class="panel stack">
    <p>Подсказка по переменным: нажмите на бейдж, чтобы вставить переменную в конец промпта.</p>
    <div>
        <?php foreach (['{{audience}}','{{tone}}','{{category}}','{{season}}','{{cta}}'] as $v): ?>
            <button type="button" class="secondary" data-insert="<?= e($v) ?>" style="display:inline-block;width:auto;margin:0 6px 6px 0;"><?= e($v) ?></button>
        <?php endforeach; ?>
    </div>
    <form method="post" class="stack">
        <input type="hidden" name="_csrf" value="<?= e(CsrfGuard::token()) ?>">
        <div class="row">
            <div><label>Тема</label><select name="theme_id"><?php foreach($themes as $t):?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach;?></select></div>
            <div><label>Название версии</label><input name="title" placeholder="Например: продажа/дружелюбный тон" required></div>
        </div>
        <div>
            <label>Тело промпта (ожидается строго JSON-ориентированный контент)</label>
            <textarea id="prompt-body" name="body" rows="10" placeholder="Сформируй только JSON..." required></textarea>
        </div>
        <button>Сохранить новую версию</button>
    </form>
</div>

<div class="panel">
    <h3>История версий</h3>
    <table>
        <tr><th>ID</th><th>Тема</th><th>Версия</th><th>Название</th><th>Создано</th></tr>
        <?php foreach($rows as $r): ?>
            <tr><td>#<?= (int)$r['id'] ?></td><td><?= e($r['theme']) ?></td><td><?= (int)$r['version'] ?></td><td><?= e($r['title']) ?></td><td class="mono"><?= e($r['created_at']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<script>
const promptBody = document.getElementById('prompt-body');
document.querySelectorAll('[data-insert]').forEach(btn => {
  btn.addEventListener('click', () => {
    promptBody.value = (promptBody.value + ' ' + btn.dataset.insert).trim();
    promptBody.focus();
  });
});
</script>
<?php renderLayout('Менеджер промптов', ob_get_clean());
