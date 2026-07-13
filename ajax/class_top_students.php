<?php
/**
 * دریافت سه دانش‌آموز برتر کلاس بر اساس معدل نمرات
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';
require_once '../config/helpers.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'کاربر وارد نشده است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'شناسه کلاس نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // محاسبه معدل کل هر دانش‌آموز در این کلاس بر اساس تکالیف تصحیح‌شده و آزمون‌ها
    $stmt = $pdo->prepare("SELECT u.full_name, u.avatar_path,
        COALESCE(
            (
                COALESCE((SELECT SUM(grade) FROM homework_submissions hs WHERE hs.student_id = s.id AND hs.status = 'graded'), 0) +
                COALESCE((SELECT SUM(score) FROM student_quizzes sq WHERE sq.student_id = s.id AND sq.score IS NOT NULL), 0)
            ) / 
            NULLIF(
                (SELECT COUNT(*) FROM homework_submissions hs WHERE hs.student_id = s.id AND hs.status = 'graded') +
                (SELECT COUNT(*) FROM student_quizzes sq WHERE sq.student_id = s.id AND sq.score IS NOT NULL),
                0
            ),
            0
        ) as total_avg
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN class_student cs ON s.id = cs.student_id
        WHERE cs.class_id = ?
        ORDER BY total_avg DESC
        LIMIT 3");
    $stmt->execute([$classId]);
    $topStudents = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'students' => $topStudents
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'خطا در ارتباط با دیتابیس: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
