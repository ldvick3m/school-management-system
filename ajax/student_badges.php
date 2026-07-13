<?php
/**
 * کنترلر ای‌جکس مدیریت نشان‌های مهارتی دانش‌آموزان
 */

header('Content-Type: application/json; charset=utf-8');

// تصحیح مسیر لود برای پوشه ajax
require_once '../config/db.php';
require_once '../config/helpers.php';

// بررسی ورود و سطح دسترسی کاربر
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['operator', 'admin'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'شما دسترسی لازم برای انجام این عملیات را ندارید.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    // ۱. لیست کردن نشان‌های دریافت شده توسط یک دانش‌آموز
    if ($action === 'list') {
        $studentId = (int)($_GET['student_id'] ?? 0);
        if ($studentId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'شناسه دانش‌آموز معتبر نیست.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT b.*, sb.awarded_at 
            FROM student_badges sb
            JOIN badges b ON sb.badge_id = b.id
            WHERE sb.student_id = ?
            ORDER BY sb.awarded_at DESC");
        $stmt->execute([$studentId]);
        $assignedBadges = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'badges' => $assignedBadges
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ۲. لیست نشان‌های موجود در سیستم که دانش‌آموز هنوز دریافت نکرده است
    elseif ($action === 'all_badges') {
        $studentId = (int)($_GET['student_id'] ?? 0);
        if ($studentId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'شناسه دانش‌آموز معتبر نیست.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // واکشی نشان‌هایی که به این دانش‌آموز انتساب نیافته‌اند
        $stmt = $pdo->prepare("SELECT * FROM badges 
            WHERE id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?) 
            ORDER BY type ASC, id ASC");
        $stmt->execute([$studentId]);
        $availableBadges = $stmt->fetchAll();

        echo json_encode([
            'status' => 'success',
            'badges' => $availableBadges
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ۳. اهدای نشان مهارتی جدید به دانش‌آموز
    elseif ($action === 'assign') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $studentId = (int)($_POST['student_id'] ?? 0);
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $awardedBy = $_SESSION['user_id']; // شناسه کاربری فرد وارد شده (اپراتور یا مدیر)

        if ($studentId <= 0 || $badgeId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'پارامترهای ارسالی نامعتبر است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ثبت نشان
        $stmt = $pdo->prepare("INSERT IGNORE INTO `student_badges` (`student_id`, `badge_id`, `awarded_by`, `awarded_at`) 
            VALUES (?, ?, ?, NOW())");
        $stmt->execute([$studentId, $badgeId, $awardedBy]);

        echo json_encode([
            'status' => 'success',
            'message' => 'نشان مهارتی با موفقیت به دانش‌آموز اهدا شد.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ۴. لغو/حذف نشان اهدا شده
    elseif ($action === 'revoke') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $studentId = (int)($_POST['student_id'] ?? 0);
        $badgeId = (int)($_POST['badge_id'] ?? 0);

        if ($studentId <= 0 || $badgeId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'پارامترهای ارسالی نامعتبر است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // حذف انتساب نشان
        $stmt = $pdo->prepare("DELETE FROM `student_badges` WHERE `student_id` = ? AND `badge_id` = ?");
        $stmt->execute([$studentId, $badgeId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'نشان مهارتی با موفقیت لغو شد.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    else {
        echo json_encode(['status' => 'error', 'message' => 'متد درخواستی پشتیبانی نمی‌شود.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'خطا در ارتباط با پایگاه داده: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
