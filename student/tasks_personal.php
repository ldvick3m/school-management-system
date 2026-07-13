<?php
/**
 * صفحه کارهای شخصی و برنامه‌ریزی دانش‌آموز
 */

require_once '../includes/header.php';
check_auth(['student']);

$viewMode = 'personal';
require_once '../common/tasks_view.php';
require_once '../includes/footer.php';
