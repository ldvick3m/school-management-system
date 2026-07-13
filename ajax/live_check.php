<?php
/**
 * هاب ایجکس بررسی کلاس آنلاین زنده فعال در زنگ جاری برای دانش‌آموز
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// بررسی لاگین بودن
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

require_once '../config/db.php';
require_once '../config/helpers.php';

$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    echo json_encode(['active' => false]);
    exit;
}

try {
    // پیدا کردن کلاس آنلاین زنده در تاریخ امروز و ساعت جاری برای کلاسی که دانش‌آموز در آن عضو است
    $stmt = $pdo->prepare("SELECT lc.*, co.course_name, u.full_name as teacher_name 
        FROM live_classes lc
        JOIN class_student cs ON lc.class_id = cs.class_id
        JOIN courses co ON lc.course_id = co.id
        JOIN teachers t ON lc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE cs.student_id = ? 
          AND lc.date = CURRENT_DATE() 
          AND CURRENT_TIME() BETWEEN lc.start_time AND lc.end_time
        LIMIT 1");
        
    $stmt->execute([$student_id]);
    $liveClass = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($liveClass) {
        echo json_encode([
            'active' => true,
            'class' => $liveClass
        ]);
    } else {
        echo json_encode([
            'active' => false
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['active' => false, 'error' => $e->getMessage()]);
}
?>
