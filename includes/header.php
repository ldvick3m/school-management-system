<?php
ob_start();
/**
 * هدر مشترک پنل‌های کاربری سامانه مدیریت مدرسه
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// محاسبه مسیر روت پروژه به صورت پویا
$root = "";
if (file_exists("assets/css/style.css")) {
    $root = "";
} elseif (file_exists("../assets/css/style.css")) {
    $root = "../";
} elseif (file_exists("../../assets/css/style.css")) {
    $root = "../../";
}

// فراخوانی کانفیگ دیتابیس و توابع کمکی
require_once $root . 'config/db.php';
require_once $root . 'config/helpers.php';

// اجرای شبیه‌ساز کرون جاب تنبل (Lazy Cron)
require_once $root . 'ajax/cron_routine_generator.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    redirect($root . 'index.php');
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'full_name' => $_SESSION['full_name'],
    'national_code' => $_SESSION['national_code'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?html_entity_decode('سامانه مدیریت مدرسه هوشمند') ?></title>
    
    <!-- Bootstrap 5 RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Jalali Datepicker CSS -->
    <link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $root ?>assets/css/style.css?v=3.0">
</head>
<body>

<div class="app-container">
    <!-- سایدبار پویا بر اساس نقش -->
    <?php include $root . 'includes/sidebar.php'; ?>
    
    <!-- بخش محتوای اصلی صفحه -->
    <div class="app-content">
        <!-- تاپ بار (هدر بالایی) -->
        <header class="app-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-dark d-lg-none" id="sidebarToggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <h4 class="header-title d-none d-sm-block">
                    <?php 
                        // عنوان پویای بالای صفحه بر اساس صفحه جاری
                        $pageName = basename($_SERVER['PHP_SELF'], '.php');
                        switch ($pageName) {
                            case 'dashboard': echo 'داشبورد کاربری'; break;
                            case 'courses': echo 'لیست دروس و کلاس‌ها'; break;
                            case 'course_details': echo 'مباحث و محتوای آموزشی'; break;
                            case 'quiz': echo 'پنل شرکت در آزمون'; break;
                            case 'quiz_builder': echo 'آزمون‌ساز پویا دبیر'; break;
                            case 'grading': echo 'کارتابل ارزیابی و تصحیح'; break;
                            case 'attendance': echo 'دفتر حضور و غیاب دیجیتال'; break;
                            case 'discipline': echo 'رادار انضباطی دانش‌آموزان'; break;
                            case 'notepad': echo 'دفترچه یادداشت دیجیتال'; break;
                            case 'analytics': echo 'گزارش‌های تحلیلی و کارنامه'; break;
                            case 'badges': echo 'مدال‌ها و دستاوردها'; break;
                            case 'users': echo 'مدیریت کاربران مدرسه'; break;
                            case 'classes': echo 'مدیریت کلاس‌ها و تخصیص‌ها'; break;
                            case 'live_scheduler': echo 'برنامه‌ریزی کلاس‌های لایو'; break;
                            case 'tickets': echo 'سیستم تیکتینگ پشتیبانی'; break;
                            case 'announcements': echo 'مرکز اعلانات هوشمند'; break;
                            case 'structures': echo 'ساختارهای کلان ترم و دروس'; break;
                            case 'calendar': echo 'تقویم جامع تداخل‌ها'; break;
                            default: echo 'پنل مدیریت مدرسه';
                        }
                    ?>
                </h4>
            </div>
            
            <div class="header-user-area">
                <?php if ($_SESSION['role'] === 'parent' && isset($_SESSION['active_child_name'])): ?>
                    <span class="badge bg-light text-dark p-2 border">
                        <i class="bi bi-person-fill text-primary"></i>
                        فرزند فعال: <?= htmlspecialchars($_SESSION['active_child_name']) ?>
                    </span>
                <?php endif; ?>
                
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle border" type="button" id="userMenuBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($currentUser['full_name']) ?> 
                        <span class="badge bg-secondary ms-1"><?= get_role_fa($currentUser['role']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenuBtn">
                        <li><a class="dropdown-item text-danger" href="<?= $root ?>logout.php"><i class="bi bi-box-arrow-left me-2"></i>خروج از حساب کاربری</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- بدنه محتوای صفحه -->
        <main class="p-4" style="flex-grow: 1;">
