<?php
/**
 * Admin auth middleware — include at top of every admin page
 */
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/_nav.php';

startSecureSession();
requireAdminSession();
