<?php
/**
 * صفحه وظایف مدرسه تخصیص‌یافته به دانش‌آموز
 */

require_once '../includes/header.php';
check_auth(['student']);

$viewMode = 'assigned';
require_once '../common/tasks_view.php';
require_once '../includes/footer.php';
