<?php
/**
 * صفحه مدیریت وظایف ارجاع داده شده به دانش‌آموزان توسط معلم
 */

require_once '../includes/header.php';
check_auth(['teacher']);

$viewMode = 'student_tasks';
require_once '../common/tasks_view.php';
require_once '../includes/footer.php';
