<?php
/**
 * هاب ایجکس مدیریت دفترچه یادداشت دیجیتال دانش‌آموز (CRUD کامل)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// بررسی لاگین بودن و نقش دانش‌آموز
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

require_once '../config/db.php';
require_once '../config/helpers.php';

$action = $_REQUEST['action'] ?? '';

try {
    // پیدا کردن شناسه دانش‌آموز
    $studentId = $pdo->query("SELECT id FROM students WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
    if (!$studentId) {
        throw new Exception("دانش‌آموز یافت نشد.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// عملیات‌ها
if ($action === 'list') {
    $note_type = $_GET['note_type'] ?? 'general';
    $course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
    $topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;

    try {
        if ($note_type === 'general') {
            $stmt = $pdo->prepare("SELECT * FROM notes WHERE student_id = ? AND note_type = 'general' ORDER BY created_at DESC");
            $stmt->execute([$studentId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM notes WHERE student_id = ? AND note_type = 'course' AND course_id = ? AND topic_id = ? ORDER BY created_at DESC");
            $stmt->execute([$studentId, $course_id, $topic_id]);
        }
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($notes);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

elseif ($action === 'save') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $note_type = $_POST['note_type'] ?? 'general';
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'وارد کردن عنوان و متن یادداشت الزامی است.']);
        exit;
    }

    try {
        if ($id > 0) {
            // ویرایش یادداشت
            $stmt = $pdo->prepare("UPDATE `notes` SET `title` = ?, `content` = ?, `course_id` = ?, `topic_id` = ? WHERE `id` = ? AND `student_id` = ?");
            $stmt->execute([$title, $content, $course_id ?: null, $topic_id ?: null, $id, $studentId]);
            $success_msg = "یادداشت با موفقیت ویرایش شد.";
        } else {
            // ایجاد یادداشت جدید
            $stmt = $pdo->prepare("INSERT INTO `notes` (`student_id`, `note_type`, `course_id`, `topic_id`, `title`, `content`) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$studentId, $note_type, $course_id ?: null, $topic_id ?: null, $title, $content]);
            $id = $pdo->lastInsertId();
            $success_msg = "یادداشت جدید ثبت شد.";
        }

        echo json_encode(['success' => true, 'id' => $id, 'message' => $success_msg]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

elseif ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه یادداشت نامعتبر است.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM `notes` WHERE `id` = ? AND `student_id` = ?");
        $stmt->execute([$id, $studentId]);
        echo json_encode(['success' => true, 'message' => 'یادداشت با موفقیت حذف شد.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
