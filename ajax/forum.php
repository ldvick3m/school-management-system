<?php
/**
 * هاب ایجکس تالار گفتگو مباحث درسی (Lesson Forum)
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

if ($action === 'get') {
    $topic_id = (int)($_GET['topic_id'] ?? 0);
    $last_id = (int)($_GET['last_id'] ?? 0);

    if ($topic_id <= 0) {
        echo json_encode([]);
        exit;
    }

    try {
        // واکشی پیام‌ها بعد از آی‌دی مشخص شده
        $stmt = $pdo->prepare("SELECT fm.id, fm.sender_id, fm.message_text, fm.created_at, u.full_name, u.role 
            FROM forum_messages fm
            JOIN users u ON fm.sender_id = u.id
            WHERE fm.topic_id = ? AND fm.id > ?
            ORDER BY fm.id ASC");
        
        $stmt->execute([$topic_id, $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // آماده‌سازی فیلدهای فارسی
        foreach ($messages as &$msg) {
            $msg['role_fa'] = get_role_fa($msg['role']);
            $msg['time_fa'] = date('H:i', strtotime($msg['created_at']));
        }

        echo json_encode($messages);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

elseif ($action === 'send') {
    $topic_id = (int)($_POST['topic_id'] ?? 0);
    $message_text = trim($_POST['message_text'] ?? '');
    $sender_id = $_SESSION['user_id'];

    if ($topic_id <= 0 || empty($message_text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO `forum_messages` (`topic_id`, `sender_id`, `message_text`) VALUES (?, ?, ?)");
        $stmt->execute([$topic_id, $sender_id, $message_text]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
