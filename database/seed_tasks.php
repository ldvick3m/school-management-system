<?php
/**
 * اسکریپت ایجاد داده‌های دمو غنی برای ماژول مدیریت تسک‌ها
 */

require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // ۱. دریافت برخی کاربران موجود در سیستم جهت تخصیص وظایف
    // مدیر
    $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
    // اپراتور
    $operatorId = $pdo->query("SELECT id FROM users WHERE role = 'operator' LIMIT 1")->fetchColumn();
    // معلمان تستی
    $teachers = $pdo->query("SELECT user_id FROM teachers LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    // دانش‌آموزان تستی
    $students = $pdo->query("SELECT user_id FROM students LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);

    if (!$adminId || !$operatorId || empty($teachers) || empty($students)) {
        throw new Exception("Core users (admin, operator, teachers, or students) not found in the database. Please seed users first.");
    }

    // پاک کردن داده‌های قبلی تسک‌ها برای جلوگیری از تکرار مجدد
    $pdo->exec("DELETE FROM `task_notifications`");
    $pdo->exec("DELETE FROM `task_comments`");
    $pdo->exec("DELETE FROM `tasks`");
    $pdo->exec("DELETE FROM `task_template_assignments`");
    $pdo->exec("DELETE FROM `task_templates`");

    echo "Cleared old task data.\n";

    // ۲. ایجاد الگوهای تسک‌های روتین (Templates)
    // الگوی روتین روزانه برای همه معلمان (ایجاد شده توسط مدیر)
    $stmtTpl = $pdo->prepare("INSERT INTO `task_templates` (`title`, `description`, `created_by`, `assigned_type`, `assigned_to_role`, `frequency`, `priority`) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // الگوی ۱: ثبت حضور و غیاب روزانه کلاس‌ها
    $stmtTpl->execute([
        'ثبت حضور و غیاب روزانه کلاس‌ها',
        'لطفاً لیست حضور و غیاب روزانه دانش‌آموزان کلاس خود را حداکثر تا ساعت ۱۳ در سیستم ثبت نمایید.',
        $adminId,
        'all_teachers',
        'teacher',
        'daily',
        'urgent'
    ]);
    $tpl1 = $pdo->lastInsertId();

    // الگوی ۲: تصحیح و ثبت نمرات تکالیف هفتگی
    $stmtTpl->execute([
        'تصحیح و ثبت نمرات تکالیف هفتگی',
        'بررسی و ثبت فیدبک تکالیف ارسال شده دانش‌آموزان در طول هفته جاری.',
        $adminId,
        'all_teachers',
        'teacher',
        'weekly',
        'normal'
    ]);
    $tpl2 = $pdo->lastInsertId();

    // الگوی ۳: پیش‌خوانی مباحث هفته آینده (روتین هفتگی برای دانش‌آموزان)
    $stmtTpl->execute([
        'پیش‌خوانی مبحث جدید درس ریاضی',
        'فیلم آموزشی و جزوه پیوست مبحث جدید درس ریاضی را قبل از کلاس روز شنبه مطالعه و پیش‌خوانی کنید.',
        $teachers[0], // ایجاد شده توسط اولین معلم
        'all_students',
        'student',
        'weekly',
        'normal'
    ]);
    $tpl3 = $pdo->lastInsertId();

    // ۳. ایجاد نمونه‌های وظایف (Tasks) برای معلمان، اپراتورها و دانش‌آموزان
    
    // الف) وظایف دبیر اول (معلم 1)
    // ۱. تسک روتین انجام شده (Done) با زمان ثبت شده
    $stmtTask = $pdo->prepare("INSERT INTO `tasks` (`template_id`, `title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`, `timer_seconds`, `is_timer_running`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    
    $stmtTask->execute([
        $tpl1,
        'ثبت حضور و غیاب روزانه کلاس‌ها',
        'لطفاً لیست حضور و غیاب روزانه دانش‌آموزان کلاس خود را حداکثر تا ساعت ۱۳ در سیستم ثبت نمایید.',
        $adminId,
        $teachers[0],
        'routine',
        'urgent',
        'done',
        date('Y-m-d 13:00:00', strtotime('yesterday')),
        1850 // ۳۰ دقیقه و ۵۰ ثانیه
    ]);

    // ۲. تسک روتین در حال انجام (In Progress) با زمان نصفه کارکرد
    $stmtTask->execute([
        $tpl2,
        'تصحیح و ثبت نمرات تکالیف هفتگی',
        'بررسی و ثبت فیدبک تکالیف ارسال شده دانش‌آموزان در طول هفته جاری.',
        $adminId,
        $teachers[0],
        'routine',
        'normal',
        'in_progress',
        date('Y-m-d 23:59:59', strtotime('+3 days')),
        4200 // ۱ ساعت و ۱۰ دقیقه
    ]);

    // ۳. تسک سفارشی معوقه (Overdue) - ددلاین ۳ روز پیش گذشته و انجام نشده
    $stmtTask->execute([
        null,
        'طراحی سوالات آزمون میان‌ترم دوم علوم',
        'سوالات آزمون علوم تجربی پایه هشتم طراحی شده و فایل PDF آن ضمیمه شود.',
        $operatorId,
        $teachers[0],
        'custom',
        'critical',
        'todo',
        date('Y-m-d H:i:s', strtotime('-3 days')),
        0
    ]);
    $overdueTaskId = $pdo->lastInsertId();

    // ۴. تسک سفارشی در شرف انقضا (نزدیک به ددلاین < ۲۴ ساعت)
    $stmtTask->execute([
        null,
        'ارسال گزارش ماهانه وضعیت درسی کلاس ۹ الف',
        'ارائه خلاصه تحلیل کیفی از سطح علمی دانش‌آموزان کلاس ۹ الف به اپراتور.',
        $operatorId,
        $teachers[0],
        'custom',
        'normal',
        'in_progress',
        date('Y-m-d H:i:s', strtotime('+12 hours')),
        900 // ۱۵ دقیقه
    ]);

    // ب) وظایف اپراتور
    // ۱. تسک سفارشی در حال انجام ارجاع شده توسط مدیر
    $stmtTask->execute([
        null,
        'بررسی و تایید نهایی درخواست‌های مرخصی و برنامه‌های هفتگی معلمان',
        'پوشه مرخصی‌ها و برنامه‌های هفتگی بارگذاری شده توسط دبیران را بررسی و نتیجه را به مدیر اعلام کنید.',
        $adminId,
        $operatorId,
        'custom',
        'urgent',
        'in_progress',
        date('Y-m-d 17:00:00', strtotime('+1 day')),
        7200 // ۲ ساعت
    ]);

    // ۲. تسک شخصی اپراتور برای خودش
    $stmtTask->execute([
        null,
        'برنامه‌ریزی و چک نهایی تداخل‌های کلاس‌های لایو شنبه',
        'لیست تداخل‌های کلاس آنلاین شنبه را بررسی و تایید کنید.',
        $operatorId,
        $operatorId,
        'custom',
        'normal',
        'todo',
        date('Y-m-d 12:00:00', strtotime('+2 days')),
        0
    ]);

    // ج) وظایف دانش‌آموزان
    // ۱. وظیفه مدرسه‌ای دانش‌آموز اول (ارجاع شده توسط دبیر) - Todo
    $stmtTask->execute([
        $tpl3,
        'پیش‌خوانی مبحث جدید درس ریاضی',
        'فیلم آموزشی و جزوه پیوست مبحث جدید درس ریاضی را قبل از کلاس روز شنبه مطالعه و پیش‌خوانی کنید.',
        $teachers[0],
        $students[0],
        'routine',
        'normal',
        'todo',
        date('Y-m-d 08:00:00', strtotime('+5 days')),
        0
    ]);

    // ۲. کار شخصی دانش‌آموز اول برای خودش (Personal) - In Progress
    $stmtTask->execute([
        null,
        'حل تمرین‌های دوره فصل ۳ علوم',
        'مرور سوالات تشریحی و تست‌های پایان فصل ۳ علوم تجربی.',
        $students[0],
        $students[0],
        'custom',
        'normal',
        'in_progress',
        date('Y-m-d H:i:s', strtotime('+1 day')),
        1200 // ۲۰ دقیقه
    ]);

    // ۴. ایجاد کامنت‌ها و شبیه‌سازی سیستم منشن
    $stmtComment = $pdo->prepare("INSERT INTO `task_comments` (`task_id`, `user_id`, `comment_text`) VALUES (?, ?, ?)");
    
    // کامنت اول روی تسک معوقه علوم
    $stmtComment->execute([
        $overdueTaskId,
        $teachers[0],
        'سلام. طراحی سوالات تمام شده است، فردا صبح فایل را آپلود می‌کنم. @operator_1'
    ]);
    
    // ثبت نوتیفیکیشن منشن برای اپراتور
    $stmtNotif = $pdo->prepare("INSERT INTO `task_notifications` (`user_id`, `task_id`, `notification_type`, `message`) VALUES (?, ?, 'mention', ?)");
    $stmtNotif->execute([
        $operatorId,
        $overdueTaskId,
        "کاربر «دبیر تستی» شما را در وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» منشن کرد."
    ]);

    // کامنت دوم روی تسک معوقه علوم
    $stmtComment->execute([
        $overdueTaskId,
        $operatorId,
        'بسیار عالی. لطفاً در صورت امکان داکیومنت پاسخ تشریحی را هم ضمیمه کنید. تشکر. @teacher_1'
    ]);
    
    // ثبت نوتیفیکیشن منشن برای معلم
    $stmtNotif->execute([
        $teachers[0],
        $overdueTaskId,
        "کاربر «اپراتور تستی» شما را در وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» منشن کرد."
    ]);

    // ۵. ثبت نوتیفیکیشن هشدار ددلاین گذشته (معوقه) برای تسک معوقه دبیر
    $stmtNotif->execute([
        $teachers[0],
        $overdueTaskId,
        "هشدار قرمز: مهلت انجام وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» به پایان رسیده و این کار معوقه شده است."
    ]);

    $pdo->commit();
    echo "SUCCESS: Seeded task management demo data successfully!\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: Seeding tasks failed: " . $e->getMessage() . "\n";
}
