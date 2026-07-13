<?php
/**
 * هاب ایجکس ارسال و دریافت پیام‌های چت‌روم‌ها
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

$action = $_REQUEST['action'] ?? '';
$chat_user_id = (int)($_REQUEST['chat_user_id'] ?? 0);

// بررسی و محدودسازی دسترسی دبیر به دانش‌آموزان و اولیای خودش
if (($action === 'get' || $action === 'send') && $chat_user_id > 0) {
    $role = $_SESSION['role'];
    if ($role === 'teacher') {
        // پیدا کردن شناسه معلم
        $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmtT->execute([$_SESSION['user_id']]);
        $teacherId = $stmtT->fetchColumn();
        
        if ($teacherId) {
            $stmtCUser = $pdo->prepare("
                SELECT role FROM users u
                WHERE u.id = ? AND u.status = 1
                  AND (
                    -- حالت اول: کاربر دانش‌آموز این معلم باشد
                    (u.role = 'student' AND EXISTS (
                        SELECT 1 FROM class_student cs
                        JOIN students s ON cs.student_id = s.id
                        JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
                        WHERE s.user_id = u.id AND ctc.teacher_id = ?
                    ))
                    OR
                    -- حالت دوم: کاربر ولیِ دانش‌آموز این معلم باشد
                    (u.role = 'parent' AND EXISTS (
                        SELECT 1 FROM class_student cs
                        JOIN students s ON cs.student_id = s.id
                        JOIN parent_student ps ON s.id = ps.student_id
                        JOIN parents p ON ps.parent_id = p.id
                        JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
                        WHERE p.user_id = u.id AND ctc.teacher_id = ?
                    ))
                  )
            ");
            $stmtCUser->execute([$chat_user_id, $teacherId, $teacherId]);
            $isAuthorized = $stmtCUser->fetchColumn();
            
            if (!$isAuthorized) {
                http_response_code(403);
                echo json_encode(['error' => 'دسترسی غیرمجاز به گفتگو با این کاربر']);
                exit;
            }
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'دبیر معتبر یافت نشد']);
            exit;
        }
    }
}

if ($action === 'get') {
    $last_id = (int)($_GET['last_id'] ?? 0);
    $my_id = $_SESSION['user_id'];

    if ($chat_user_id <= 0) {
        echo json_encode([]);
        exit;
    }

    try {
        // واکشی پیام‌های دو طرفه با مرتب‌سازی زمانی
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, message_text, created_at 
            FROM chat_messages 
            WHERE id > ? 
              AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            ORDER BY created_at ASC");
        
        $stmt->execute([$last_id, $my_id, $chat_user_id, $chat_user_id, $my_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // فرمت کردن زمان‌ها به فارسی جهت نمایش روان
        foreach ($messages as &$msg) {
            $msg['time_fa'] = date('H:i', strtotime($msg['created_at']));
        }

        // علامت‌گذاری پیام‌های مخاطب به عنوان خوانده‌شده
        $stmtRead = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmtRead->execute([$chat_user_id, $my_id]);

        echo json_encode($messages);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

elseif ($action === 'send') {
    $chat_user_id = (int)($_POST['chat_user_id'] ?? 0);
    $message_text = trim($_POST['message_text'] ?? '');
    $my_id = $_SESSION['user_id'];

    if ($chat_user_id <= 0 || empty($message_text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اطلاعات ارسالی ناقص است.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO `chat_messages` (`sender_id`, `receiver_id`, `message_text`) VALUES (?, ?, ?)");
        $stmt->execute([$my_id, $chat_user_id, $message_text]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

elseif ($action === 'unread_counts') {
    $my_id = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT sender_id, COUNT(*) as unread_count 
            FROM chat_messages 
            WHERE receiver_id = ? AND is_read = 0 
            GROUP BY sender_id");
        $stmt->execute([$my_id]);
        $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($counts);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
