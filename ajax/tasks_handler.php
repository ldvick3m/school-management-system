<?php
/**
 * کنترلر بک‌اند AJAX برای مدیریت تسک‌ها، کامنت‌ها، تایمر و اعلان‌ها
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'شما لاگین نکرده‌اید.']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'update_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = $_POST['status'] ?? ''; // todo, in_progress, done
        
        if ($taskId <= 0 || !in_array($newStatus, ['todo', 'in_progress', 'done'])) {
            echo json_encode(['status' => 'error', 'message' => 'پارامترهای ارسالی نامعتبر است.']);
            exit;
        }

        // واکشی اطلاعات فعلی تسک
        $stmt = $pdo->prepare("SELECT * FROM `tasks` WHERE `id` = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['status' => 'error', 'message' => 'تسک مورد نظر یافت نشد.']);
            exit;
        }

        // بررسی دسترسی
        if ($currentUserRole !== 'admin' && $currentUserRole !== 'operator' && $task['assigned_to'] != $currentUserId && $task['created_by'] != $currentUserId) {
            echo json_encode(['status' => 'error', 'message' => 'شما دسترسی ویرایش این تسک را ندارید.']);
            exit;
        }

        $pdo->beginTransaction();

        // اگر وضعیت جدید done باشد و تایمر در حال اجرا باشد، آن را متوقف و زمان را ذخیره می‌کنیم
        if ($newStatus === 'done' && $task['is_timer_running'] == 1) {
            $stmtElapsed = $pdo->prepare("SELECT GREATEST(0, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(timer_last_started)) FROM `tasks` WHERE `id` = ?");
            $stmtElapsed->execute([$taskId]);
            $elapsed = (int)$stmtElapsed->fetchColumn();
            $totalSeconds = max(0, (int)$task['timer_seconds']) + $elapsed;

            $stmtUpdate = $pdo->prepare("UPDATE `tasks` SET `status` = 'done', `is_timer_running` = 0, `timer_last_started` = NULL, `timer_seconds` = ? WHERE `id` = ?");
            $stmtUpdate->execute([$totalSeconds, $taskId]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE `tasks` SET `status` = ? WHERE `id` = ?");
            $stmtUpdate->execute([$newStatus, $taskId]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'وضعیت تسک با موفقیت به‌روزرسانی شد.']);
        exit;

    } elseif ($action === 'timer_play') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT * FROM `tasks` WHERE `id` = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task || $task['status'] === 'done') {
            echo json_encode(['status' => 'error', 'message' => 'شروع تایمر برای تسک‌های انجام‌شده ممکن نیست.']);
            exit;
        }

        if ($task['assigned_to'] != $currentUserId && $task['created_by'] != $currentUserId && !in_array($currentUserRole, ['admin', 'operator', 'teacher'])) {
            echo json_encode(['status' => 'error', 'message' => 'شما دسترسی به کنترل تایمر این تسک را ندارید.']);
            exit;
        }

        // شروع تایمر
        $stmtUpdate = $pdo->prepare("UPDATE `tasks` SET `is_timer_running` = 1, `timer_last_started` = NOW(), `status` = 'in_progress' WHERE `id` = ?");
        $stmtUpdate->execute([$taskId]);

        echo json_encode([
            'status' => 'success', 
            'timer_seconds' => $task['timer_seconds'], 
            'is_running' => 1
        ]);
        exit;

    } elseif ($action === 'timer_pause') {
        $taskId = (int)($_POST['task_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM `tasks` WHERE `id` = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task || $task['is_timer_running'] == 0) {
            echo json_encode(['status' => 'error', 'message' => 'تایمر در حال اجرا نیست.']);
            exit;
        }

        // متوقف کردن و محاسبه زمان سپری شده با دیتابیس ( timezone-safe )
        $stmtElapsed = $pdo->prepare("SELECT GREATEST(0, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(timer_last_started)) FROM `tasks` WHERE `id` = ?");
        $stmtElapsed->execute([$taskId]);
        $elapsed = (int)$stmtElapsed->fetchColumn();
        $totalSeconds = max(0, (int)$task['timer_seconds']) + $elapsed;

        $stmtUpdate = $pdo->prepare("UPDATE `tasks` SET `is_timer_running` = 0, `timer_last_started` = NULL, `timer_seconds` = ? WHERE `id` = ?");
        $stmtUpdate->execute([$totalSeconds, $taskId]);

        echo json_encode([
            'status' => 'success', 
            'timer_seconds' => $totalSeconds, 
            'is_running' => 0
        ]);
        exit;

    } elseif ($action === 'add_comment') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $commentText = trim($_POST['comment_text'] ?? '');
        
        if ($taskId <= 0 || empty($commentText)) {
            echo json_encode(['status' => 'error', 'message' => 'وارد کردن متن کامنت الزامی است.']);
            exit;
        }

        // هندل کردن آپلود فایل در کامنت
        $attachmentPath = null;
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            $targetDir = __DIR__ . '/../uploads/tasks/';
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName = basename($_FILES['attachment_file']['name']);
            $newFileName = uniqid('task_comment_', true) . '_' . $fileName;
            if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $targetDir . $newFileName)) {
                $attachmentPath = 'uploads/tasks/' . $newFileName;
            }
        }

        $pdo->beginTransaction();

        // ثبت کامنت
        $stmtComment = $pdo->prepare("INSERT INTO `task_comments` (`task_id`, `user_id`, `comment_text`, `attachment_path`) VALUES (?, ?, ?, ?)");
        $stmtComment->execute([$taskId, $currentUserId, $commentText, $attachmentPath]);

        // واکشی مشخصات تسک
        $stmtTask = $pdo->prepare("SELECT title, assigned_to, created_by FROM `tasks` WHERE `id` = ?");
        $stmtTask->execute([$taskId]);
        $taskInfo = $stmtTask->fetch();

        // جستجو برای منشن‌ها (@username)
        preg_match_all('/@([a-zA-Z0-9_\-\x{0600}-\x{06FF}]+)/u', $commentText, $matches);
        $mentions = array_unique($matches[1] ?? []);

        foreach ($mentions as $username) {
            // پیدا کردن کاربر منشن شده
            $stmtUser = $pdo->prepare("SELECT id, full_name FROM `users` WHERE `username` = ?");
            $stmtUser->execute([$username]);
            $user = $stmtUser->fetch();

            if ($user) {
                // ثبت نوتیفیکیشن منشن
                $stmtNotif = $pdo->prepare("INSERT INTO `task_notifications` (`user_id`, `task_id`, `notification_type`, `message`) VALUES (?, ?, 'mention', ?)");
                $senderName = $_SESSION['full_name'];
                $msg = "کاربر «" . $senderName . "» شما را در وظیفه «" . $taskInfo['title'] . "» منشن کرد.";
                $stmtNotif->execute([$user['id'], $taskId, $msg]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'کامنت با موفقیت ثبت شد.']);
        exit;

    } elseif ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $taskType = $_POST['task_type'] ?? 'custom'; // custom, routine
        $priority = $_POST['priority'] ?? 'normal';
        
        // هندل کردن ددلاین شمسی
        $deadlineDate = $_POST['deadline_date'] ?? '';
        $deadlineTime = $_POST['deadline_time'] ?? '23:59';
        
        if (empty($title) || empty($deadlineDate)) {
            echo json_encode(['status' => 'error', 'message' => 'وارد کردن فیلدهای عنوان و تاریخ ددلاین الزامی است.']);
            exit;
        }

        $gregorianDate = parse_shamsi_to_gregorian($deadlineDate);
        $deadline = $gregorianDate . ' ' . $deadlineTime . ':00';

        // ارزیابی ارجاع‌شوندگان (ارجاع تکی یا گروهی برای معلم)
        $assignedTarget = $_POST['assigned_target'] ?? 'myself'; // myself, students
        $assignedToIds = [];

        if ($currentUserRole === 'teacher' && $assignedTarget === 'students') {
            $studentIds = $_POST['assigned_students'] ?? [];
            if (empty($studentIds) || !is_array($studentIds)) {
                echo json_encode(['status' => 'error', 'message' => 'حداقل یکی از دانش‌آموزان کلاس را جهت ارجاع تسک انتخاب کنید.']);
                exit;
            }
            foreach ($studentIds as $sid) {
                $assignedToIds[] = (int)$sid;
            }
        } else {
            // ارجاع تکی معمولی (برای مدیر، اپراتور یا خود معلم/دانش‌آموز)
            $assignedTo = (int)($_POST['assigned_to'] ?? $currentUserId);
            if ($assignedTo <= 0) {
                $assignedTo = $currentUserId;
            }
            $assignedToIds[] = $assignedTo;
        }

        // هندل کردن آپلود فایل ضمیمه تسک
        $attachmentPath = null;
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            $targetDir = __DIR__ . '/../uploads/tasks/';
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName = basename($_FILES['attachment_file']['name']);
            $newFileName = uniqid('task_', true) . '_' . $fileName;
            if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $targetDir . $newFileName)) {
                $attachmentPath = 'uploads/tasks/' . $newFileName;
            }
        }

        $pdo->beginTransaction();

        foreach ($assignedToIds as $assigneeId) {
            if ($taskType === 'routine') {
                // اگر تسک روتین تکرارشونده تعریف شود، ابتدا یک الگو می‌سازیم
                $frequency = $_POST['frequency'] ?? 'daily';
                
                $stmtTpl = $pdo->prepare("INSERT INTO `task_templates` (`title`, `description`, `created_by`, `assigned_type`, `assigned_to_role`, `frequency`, `priority`) 
                    VALUES (?, ?, ?, 'individual', ?, ?, ?)");
                // دریافت نقش کاربر ارجاع‌شونده
                $stmtRole = $pdo->prepare("SELECT role FROM `users` WHERE `id` = ?");
                $stmtRole->execute([$assigneeId]);
                $assignedRole = $stmtRole->fetchColumn();

                $stmtTpl->execute([$title, $description, $currentUserId, $assignedRole, $frequency, $priority]);
                $tplId = $pdo->lastInsertId();

                // ثبت پیوند کاربر به الگو
                $stmtLink = $pdo->prepare("INSERT INTO `task_template_assignments` (`template_id`, `user_id`) VALUES (?, ?)");
                $stmtLink->execute([$tplId, $assigneeId]);

                // بلافاصله اولین نمونه تسک را ایجاد می‌کنیم
                $stmtTask = $pdo->prepare("INSERT INTO `tasks` (`template_id`, `title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`, `attachment_path`) 
                    VALUES (?, ?, ?, ?, ?, 'routine', ?, 'todo', ?, ?)");
                $stmtTask->execute([$tplId, $title, $description, $currentUserId, $assigneeId, $priority, $deadline, $attachmentPath]);
            } else {
                // تسک سفارشی معمولی
                $stmtTask = $pdo->prepare("INSERT INTO `tasks` (`title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`, `attachment_path`) 
                    VALUES (?, ?, ?, ?, 'custom', ?, 'todo', ?, ?)");
                $stmtTask->execute([$title, $description, $currentUserId, $assigneeId, $priority, $deadline, $attachmentPath]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'تسک با موفقیت ایجاد و تخصیص داده شد.']);
        exit;
    } elseif ($action === 'create_task_bulk') {
        // ایجاد تسک گروهی (مثلا برای تمام معلمان، یا دانش‌آموزان یک کلاس خاص)
        if ($currentUserRole !== 'admin' && $currentUserRole !== 'operator') {
            echo json_encode(['status' => 'error', 'message' => 'شما دسترسی ثبت وظایف گروهی را ندارید.']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $taskType = $_POST['task_type'] ?? 'custom';
        $priority = $_POST['priority'] ?? 'normal';
        
        $deadlineDate = $_POST['deadline_date'] ?? '';
        $deadlineTime = $_POST['deadline_time'] ?? '23:59';
        
        $targetGroup = $_POST['target_group'] ?? ''; // all_teachers, all_students, class_students
        $classId = (int)($_POST['class_id'] ?? 0);

        if (empty($title) || empty($deadlineDate) || empty($targetGroup)) {
            echo json_encode(['status' => 'error', 'message' => 'وارد کردن فیلدهای الزامی برای ثبت گروهی اجباری است.']);
            exit;
        }

        $gregorianDate = parse_shamsi_to_gregorian($deadlineDate);
        $deadline = $gregorianDate . ' ' . $deadlineTime . ':00';

        // پیدا کردن تمام کاربران عضو گروه هدف
        $targetUserIds = [];
        if ($targetGroup === 'all_teachers') {
            $stmt = $pdo->query("SELECT user_id FROM `teachers`");
            $targetUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($targetGroup === 'all_students') {
            $stmt = $pdo->query("SELECT user_id FROM `students`");
            $targetUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($targetGroup === 'class_students' && $classId > 0) {
            $stmt = $pdo->prepare("SELECT s.user_id FROM `students` s JOIN `class_student` cs ON s.id = cs.student_id WHERE cs.class_id = ?");
            $stmt->execute([$classId]);
            $targetUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($targetUserIds)) {
            echo json_encode(['status' => 'error', 'message' => 'هیچ کاربری در گروه هدف یافت نشد.']);
            exit;
        }

        // هندل کردن آپلود فایل ضمیمه تسک
        $attachmentPath = null;
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            $targetDir = __DIR__ . '/../uploads/tasks/';
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName = basename($_FILES['attachment_file']['name']);
            $newFileName = uniqid('task_bulk_', true) . '_' . $fileName;
            if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $targetDir . $newFileName)) {
                $attachmentPath = 'uploads/tasks/' . $newFileName;
            }
        }

        $pdo->beginTransaction();

        if ($taskType === 'routine') {
            $frequency = $_POST['frequency'] ?? 'daily';
            // تعریف الگو گروهی
            $stmtTpl = $pdo->prepare("INSERT INTO `task_templates` (`title`, `description`, `created_by`, `assigned_type`, `assigned_class_id`, `frequency`, `priority`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtTpl->execute([$title, $description, $currentUserId, $targetGroup, $classId, $frequency, $priority]);
            $tplId = $pdo->lastInsertId();

            // پیوند تمام کاربران هدف به الگو
            $stmtLink = $pdo->prepare("INSERT INTO `task_template_assignments` (`template_id`, `user_id`) VALUES (?, ?)");
            // درج تسک‌های نمونه اولیه
            $stmtTask = $pdo->prepare("INSERT INTO `tasks` (`template_id`, `title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`, `attachment_path`) 
                VALUES (?, ?, ?, ?, ?, 'routine', ?, 'todo', ?, ?)");

            foreach ($targetUserIds as $uid) {
                $stmtLink->execute([$tplId, $uid]);
                $stmtTask->execute([$tplId, $title, $description, $currentUserId, $uid, $priority, $deadline, $attachmentPath]);
            }
        } else {
            // تسک‌های سفارشی گروهی
            $stmtTask = $pdo->prepare("INSERT INTO `tasks` (`title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`, `attachment_path`) 
                VALUES (?, ?, ?, ?, 'custom', ?, 'todo', ?, ?)");
            foreach ($targetUserIds as $uid) {
                $stmtTask->execute([$title, $description, $currentUserId, $uid, $priority, $deadline, $attachmentPath]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'وظایف گروهی با موفقیت برای ' . count($targetUserIds) . ' نفر ثبت و ارسال شد.']);
        exit;
    } elseif ($action === 'edit_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $deadlineDate = $_POST['deadline_date'] ?? '';
        $deadlineTime = $_POST['deadline_time'] ?? '23:59';

        if ($taskId <= 0 || empty($title) || empty($deadlineDate)) {
            echo json_encode(['status' => 'error', 'message' => 'وارد کردن فیلدهای عنوان و تاریخ ددلاین الزامی است.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM `tasks` WHERE `id` = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['status' => 'error', 'message' => 'تسک مورد نظر یافت نشد.']);
            exit;
        }

        if ($currentUserRole !== 'admin' && $currentUserRole !== 'operator' && $task['created_by'] != $currentUserId) {
            echo json_encode(['status' => 'error', 'message' => 'شما دسترسی ویرایش این تسک را ندارید.']);
            exit;
        }

        $gregorianDate = parse_shamsi_to_gregorian($deadlineDate);
        $deadline = $gregorianDate . ' ' . $deadlineTime . ':00';

        $stmtUpdate = $pdo->prepare("UPDATE `tasks` SET `title` = ?, `description` = ?, `priority` = ?, `deadline` = ? WHERE `id` = ?");
        $stmtUpdate->execute([$title, $description, $priority, $deadline, $taskId]);

        echo json_encode(['status' => 'success', 'message' => 'تسک با موفقیت ویرایش شد.']);
        exit;
    } elseif ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);

        if ($taskId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'شناسه تسک نامعتبر است.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM `tasks` WHERE `id` = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['status' => 'error', 'message' => 'تسک مورد نظر یافت نشد.']);
            exit;
        }

        // بررسی دسترسی: فقط ادمین، اپراتور، یا سازنده تسک اجازه حذف دارند
        if ($currentUserRole !== 'admin' && $currentUserRole !== 'operator' && $task['created_by'] != $currentUserId) {
            echo json_encode(['status' => 'error', 'message' => 'شما دسترسی حذف این تسک را ندارید.']);
            exit;
        }

        $pdo->beginTransaction();

        // حذف پیام‌ها/کامنت‌های مرتبط با تسک ابتدا
        $stmtDeleteComments = $pdo->prepare("DELETE FROM `task_comments` WHERE `task_id` = ?");
        $stmtDeleteComments->execute([$taskId]);

        // حذف نوتیفیکیشن‌های مرتبط با تسک
        $stmtDeleteNotifs = $pdo->prepare("DELETE FROM `task_notifications` WHERE `task_id` = ?");
        $stmtDeleteNotifs->execute([$taskId]);

        // حذف خود تسک
        $stmtDeleteTask = $pdo->prepare("DELETE FROM `tasks` WHERE `id` = ?");
        $stmtDeleteTask->execute([$taskId]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'تسک با موفقیت حذف شد.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'عملیات نامشخص است.']);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'خطا در اجرای سرور: ' . $e->getMessage()]);
    exit;
}
