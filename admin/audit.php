<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Guards\AuthGuard;

require __DIR__ . '/bootstrap.php';
AuthGuard::requireAuth();
$pdo = App::db()->getConnection();
$action=$_GET['action'] ?? '';
$sql='SELECT a.*,u.email FROM audit_log a LEFT JOIN admin_users u ON u.id=a.admin_user_id';
$params=[];
if($action){$sql.=' WHERE a.action=:a';$params[':a']=$action;}
$sql.=' ORDER BY a.id DESC LIMIT 200';
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
require __DIR__ . '/layout.php';
ob_start();?>
<h1>Audit Log</h1>
<form><input name="action" placeholder="Filter by action" value="<?= e($action) ?>"><button>Filter</button></form>
<table><tr><th>At</th><th>User</th><th>Action</th><th>Status</th><th>IP</th><th>UA</th></tr><?php foreach($rows as $r):?><tr><td><?= e($r['created_at']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['action']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['ip']) ?></td><td><?= e($r['user_agent']) ?></td></tr><?php endforeach;?></table>
<?php renderLayout('Audit', ob_get_clean());
