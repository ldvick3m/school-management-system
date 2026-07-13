<?php
/**
 * هاب ایجکس جستجوی دانش‌آموزان برای اتصال هوشمند به والدین
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// بررسی دسترسی
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['operator', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

require_once '../config/db.php';
require_once '../config/helpers.php';

$query = trim($_GET['query'] ?? '');

if ($query === '') {
    echo json_encode([]);
    exit;
}

try {
    // جستجو بر اساس نام، کدملی یا نام کاربری در بین کاربران با نقش دانش‌آموز
    $sql = "SELECT s.id, u.full_name, u.username, s.grade_level 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            WHERE (u.full_name LIKE :q OR u.username LIKE :q OR u.national_code LIKE :q) 
            LIMIT 10";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در سرچ پایگاه داده: ' . $e->getMessage()]);
}
?>
