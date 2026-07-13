<?php
/**
 * صفحه مدیریت وظایف پنل مدیر کل
 */

require_once '../includes/header.php';
check_auth(['admin']);

$viewMode = 'normal';
require_once '../common/tasks_view.php';
require_once '../includes/footer.php';
