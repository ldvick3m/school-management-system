<?php
/**
 * صفحه مدیریت وظایف پنل معلم
 */

require_once '../includes/header.php';
check_auth(['teacher']);

$viewMode = 'normal';
require_once '../common/tasks_view.php';
require_once '../includes/footer.php';
