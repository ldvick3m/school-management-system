<?php
// فایل پیکربندی پایگاه داده - پیش‌فرض توسعه لوکال
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school-management-system');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // بررسی و ایجاد ستون وضعیت خوانده‌شدن پیام‌ها (is_read) جهت نمایش نوتیفیکیشن
    try {
        $pdo->query("SELECT `is_read` FROM `chat_messages` LIMIT 1");
    } catch (PDOException $ex) {
        $pdo->exec("ALTER TABLE `chat_messages` ADD COLUMN `is_read` TINYINT DEFAULT 0");
    }
    
    // ارتقای طول فیلد آیکون نشان‌ها برای ذخیره‌سازی مسیرهای آپلود فایل
    try {
        $pdo->exec("ALTER TABLE `badges` MODIFY COLUMN `icon` VARCHAR(255) NOT NULL");
    } catch (PDOException $ex) {
        // نادیده گرفتن
    }

    // اضافه کردن ستون تصویر پروفایل به جدول کاربران
    try {
        $pdo->query("SELECT `avatar_path` FROM `users` LIMIT 1");
    } catch (PDOException $ex) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL DEFAULT NULL");
    }

    // اهدای نشان نمونه کتابخوان برتر به دانش‌آموز امیرمحمد کریمی جهت راستی‌آزمایی اولیه
    try {
        $pdo->exec("INSERT IGNORE INTO `student_badges` (`student_id`, `badge_id`, `awarded_by`, `awarded_at`) 
                    VALUES (1, 4, 1, NOW())");
    } catch (PDOException $ex) {
        // نادیده گرفتن
    }
} catch (PDOException $e) {
    // در صورتی که دیتابیس هنوز ساخته نشده باشد، خطای اتصال را قطع نمی‌کنیم تا کاربر بتواند import.php را اجرا کند
    if ($e->getCode() != 1049) { // 1049 is Unknown Database
        die("خطا در اتصال به پایگاه داده: " . $e->getMessage());
    }
}
?>
