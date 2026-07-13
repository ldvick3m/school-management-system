<?php
/**
 * اسکریپت شبیه‌ساز کرون جاب تنبل (Lazy Cron) برای تولید وظایف روتین و هشدارهای زمانی
 */

// جلوگیری از اجرای همزمان چند بازدید در صورتی که به صورت مستقیم فراخوانی شود
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// لود کردن فایل‌های اصلی اتصال در صورت لزوم
$cron_root = __DIR__ . '/../';
require_once $cron_root . 'config/db.php';

// جلوگیری از اجرای مکرر در یک ریکوئست: ذخیره در سشن که در هر لود فقط یک بار اجرا شود
if (isset($_SESSION['lazy_cron_last_run']) && (time() - $_SESSION['lazy_cron_last_run']) < 300) {
    // اگر کمتر از ۵ دقیقه پیش اجرا شده، کاری انجام نده
    return;
}
$_SESSION['lazy_cron_last_run'] = time();

try {
    // ۱. دریافت الگوهای روتین فعال
    $stmtTpl = $pdo->prepare("SELECT * FROM `task_templates` WHERE `is_active` = 1");
    $stmtTpl->execute();
    $templates = $stmtTpl->fetchAll();

    foreach ($templates as $tpl) {
        $tplId = $tpl['id'];
        $freq = $tpl['frequency'];
        
        // بررسی اینکه آیا قبلاً در بازه زمانی مربوطه تولید شده است یا خیر
        $should_generate = false;
        if ($freq === 'daily') {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `template_id` = ? AND DATE(`created_at`) = CURDATE()");
            $stmtCheck->execute([$tplId]);
            $should_generate = ($stmtCheck->fetchColumn() == 0);
        } elseif ($freq === 'weekly') {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `template_id` = ? AND YEARWEEK(`created_at`, 1) = YEARWEEK(CURDATE(), 1)");
            $stmtCheck->execute([$tplId]);
            $should_generate = ($stmtCheck->fetchColumn() == 0);
        } elseif ($freq === 'monthly') {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `template_id` = ? AND YEAR(`created_at`) = YEAR(CURDATE()) AND MONTH(`created_at`) = MONTH(CURDATE())");
            $stmtCheck->execute([$tplId]);
            $should_generate = ($stmtCheck->fetchColumn() == 0);
        }

        if ($should_generate) {
            // استخراج کاربران مقصد بر اساس نوع تخصیص الگو
            $assigned_users = [];
            $assignedType = $tpl['assigned_type'];

            if ($assignedType === 'all_teachers') {
                $stmtU = $pdo->query("SELECT user_id FROM `teachers`");
                $assigned_users = $stmtU->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($assignedType === 'all_students') {
                $stmtU = $pdo->query("SELECT user_id FROM `students`");
                $assigned_users = $stmtU->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($assignedType === 'class_students' && $tpl['assigned_class_id'] > 0) {
                $stmtU = $pdo->prepare("SELECT s.user_id FROM `students` s JOIN `class_student` cs ON s.id = cs.student_id WHERE cs.class_id = ?");
                $stmtU->execute([$tpl['assigned_class_id']]);
                $assigned_users = $stmtU->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($assignedType === 'specific_teachers' || $assignedType === 'individual') {
                $stmtU = $pdo->prepare("SELECT user_id FROM `task_template_assignments` WHERE `template_id` = ?");
                $stmtU->execute([$tplId]);
                $assigned_users = $stmtU->fetchAll(PDO::FETCH_COLUMN);
            }

            // تعیین مهلت انجام (ددلاین پیش‌فرض بر اساس فرکانس)
            $deadline = date('Y-m-d H:i:s', strtotime('+1 day'));
            if ($freq === 'weekly') {
                $deadline = date('Y-m-d H:i:s', strtotime('+7 days'));
            } elseif ($freq === 'monthly') {
                $deadline = date('Y-m-d H:i:s', strtotime('+30 days'));
            }

            // درج تسک برای تک تک کاربران مقصد
            $stmtInsert = $pdo->prepare("INSERT INTO `tasks` (`template_id`, `title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`) 
                VALUES (?, ?, ?, ?, ?, 'routine', ?, 'todo', ?)");
            foreach ($assigned_users as $uid) {
                $stmtInsert->execute([
                    $tplId,
                    $tpl['title'],
                    $tpl['description'],
                    $tpl['created_by'],
                    $uid,
                    $tpl['priority'],
                    $deadline
                ]);
            }
        }
    }

    // ۲. بررسی و ثبت هشدارهای زمانی (۲۴ ساعت مانده به ددلاین و کارهای معوقه)
    // وظایفی که کمتر از ۲۴ ساعت مانده‌اند و انجام نشده‌اند
    $stmtWarning = $pdo->prepare("SELECT * FROM `tasks` 
        WHERE `status` != 'done' 
        AND `deadline` > NOW() 
        AND `deadline` <= DATE_ADD(NOW(), INTERVAL 24 HOUR)");
    $stmtWarning->execute();
    $warningTasks = $stmtWarning->fetchAll();

    foreach ($warningTasks as $task) {
        // بررسی وجود هشدار قبلی
        $stmtChkNotif = $pdo->prepare("SELECT COUNT(*) FROM `task_notifications` WHERE `task_id` = ? AND `notification_type` = 'deadline_warning'");
        $stmtChkNotif->execute([$task['id']]);
        if ($stmtChkNotif->fetchColumn() == 0) {
            // ثبت هشدار برای انجام‌دهنده تسک
            $stmtInsertNotif = $pdo->prepare("INSERT INTO `task_notifications` (`user_id`, `task_id`, `notification_type`, `message`) VALUES (?, ?, 'deadline_warning', ?)");
            $msg = "هشدار: کمتر از ۲۴ ساعت به مهلت انجام وظیفه «" . $task['title'] . "» باقی مانده است.";
            $stmtInsertNotif->execute([$task['assigned_to'], $task['id'], $msg]);
            
            // ثبت هشدار برای مدیر (ایجادکننده) در صورت تمایل
            if ($task['created_by'] != $task['assigned_to']) {
                $stmtInsertNotif->execute([$task['created_by'], $task['id'], $msg]);
            }
        }
    }

    // وظایفی که ددلاین آن‌ها گذشته و هنوز انجام نشده‌اند (معوقه)
    $stmtOverdue = $pdo->prepare("SELECT * FROM `tasks` 
        WHERE `status` != 'done' 
        AND `deadline` < NOW()");
    $stmtOverdue->execute();
    $overdueTasks = $stmtOverdue->fetchAll();

    foreach ($overdueTasks as $task) {
        // بررسی وجود هشدار قبلی
        $stmtChkNotif = $pdo->prepare("SELECT COUNT(*) FROM `task_notifications` WHERE `task_id` = ? AND `notification_type` = 'overdue_warning'");
        $stmtChkNotif->execute([$task['id']]);
        if ($stmtChkNotif->fetchColumn() == 0) {
            // ثبت هشدار قرمز برای انجام‌دهنده تسک
            $stmtInsertNotif = $pdo->prepare("INSERT INTO `task_notifications` (`user_id`, `task_id`, `notification_type`, `message`) VALUES (?, ?, 'overdue_warning', ?)");
            $msg = "هشدار قرمز: مهلت انجام وظیفه «" . $task['title'] . "» به پایان رسیده و این کار معوقه شده است.";
            $stmtInsertNotif->execute([$task['assigned_to'], $task['id'], $msg]);

            // ثبت هشدار قرمز برای مدیر (ایجادکننده)
            if ($task['created_by'] != $task['assigned_to']) {
                $stmtInsertNotif->execute([$task['created_by'], $task['id'], $msg]);
            }
        }
    }

} catch (PDOException $e) {
    // لاگ خطای دیتابیس برای جلوگیری از شکستن صفحه اصلی سیستم در صورت شکست کرون
    error_log("Lazy Cron Error: " . $e->getMessage());
}
