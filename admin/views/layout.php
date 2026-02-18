<?php
/** @var string $view */
use AutoPoster\Security\Csrf;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>AutoPoster Admin</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <h2>AutoPoster</h2>
    <nav>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/dashboard">Dashboard</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/channels">Channels</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/themes">Themes</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/prompts">Prompt Manager</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/schedule">Schedule Builder</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/jobs">Jobs Monitor</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/testlab">Test Lab</a>
      <a href="<?= htmlspecialchars((AutoPoster\Core\Env::get('ADMIN_PATH','/admin'))) ?>/audit">Audit Log</a>
    </nav>
  </aside>
  <main class="content">
    <?php require $view; ?>
  </main>
</div>
<input type="hidden" id="csrfToken" value="<?= htmlspecialchars(Csrf::token()) ?>">
</body>
</html>
