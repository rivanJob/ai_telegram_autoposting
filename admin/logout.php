<?php
require __DIR__ . '/bootstrap.php';
session_unset();
session_destroy();
header('Location: /admin/login.php');
