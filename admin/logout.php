<?php
require_once __DIR__ . '/../lib/security.php';
destroyAdminSession();
header('Location: index.php');
exit;
