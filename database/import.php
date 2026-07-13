<?php
/**
 * اسکریپت نصب و راه‌اندازی آسان پایگاه داده سامانه مدیریت مدرسه
 */

$message = '';
$error = '';
$installed = false;

// اطلاعات پیش‌فرض اتصال
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'school-management-system';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? 'school-management-system';

    try {
        // ۱. تلاش برای اتصال مستقیم به دیتابیس تعیین شده (جهت دور زدن عدم دسترسی ساخت دیتابیس در هاست)
        $db_exists = true;
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $db_exists = false;
        }

        // اگر دیتابیس وجود نداشت، سعی در ساخت آن می‌کنیم (مخصوص لوکال)
        if (!$db_exists) {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;");
            
            // اتصال مجدد به دیتابیس ساخته شده
            $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        // ۳. خواندن فایل schema.sql
        $sqlPath = __DIR__ . '/schema.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("فایل schema.sql یافت نشد! لطفاً بررسی کنید که فایل در مسیر صحیح قرار دارد.");
        }
        
        $sql = file_get_contents($sqlPath);
        
        // اجرای کوئری‌های فایل schema.sql
        // برای امنیت و عدم بروز خطا در کامندهای چندگانه، جدا می‌کنیم یا با exec اجرا می‌کنیم
        $pdo->exec($sql);
        
        // ۴. ایجاد کاربران پیش‌فرض تستی با پسورد هش‌شده در صورت عدم وجود
        
        $usersToSeed = [
            [
                'username' => 'admin',
                'password' => 'admin123',
                'role' => 'admin',
                'full_name' => 'علیرضا کریمی (مدیر کل)',
                'national_code' => '0012345678',
                'email' => 'admin@school.com',
                'phone' => '09121111111'
            ],
            [
                'username' => 'operator',
                'password' => 'operator123',
                'role' => 'operator',
                'full_name' => 'سارا حسینی (اپراتور)',
                'national_code' => '0022345678',
                'email' => 'operator@school.com',
                'phone' => '09122222222'
            ],
            [
                'username' => 'teacher',
                'password' => 'teacher123',
                'role' => 'teacher',
                'full_name' => 'استاد رضا احمدی (دبیر ریاضی)',
                'national_code' => '0032345678',
                'email' => 'teacher@school.com',
                'phone' => '09123333333'
            ],
            [
                'username' => 'student',
                'password' => 'student123',
                'role' => 'student',
                'full_name' => 'امیرمحمد کریمی (دانش‌آموز پایه هفتم)',
                'national_code' => '0042345678',
                'email' => 'student@school.com',
                'phone' => '09124444444'
            ],
            [
                'username' => 'parent',
                'password' => 'parent123',
                'role' => 'parent',
                'full_name' => 'محمد کریمی (ولی دانش‌آموز)',
                'national_code' => '0052345678',
                'email' => 'parent@school.com',
                'phone' => '09125555555'
            ]
        ];

        // آماده‌سازی درج کاربر
        $stmtUser = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`) 
            VALUES (:national_code, :username, :password, :role, :full_name, :email, :phone)
            ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`)");

        foreach ($usersToSeed as $u) {
            $hashedPassword = password_hash($u['password'], PASSWORD_DEFAULT);
            $stmtUser->execute([
                ':national_code' => $u['national_code'],
                ':username' => $u['username'],
                ':password' => $hashedPassword,
                ':role' => $u['role'],
                ':full_name' => $u['full_name'],
                ':email' => $u['email'],
                ':phone' => $u['phone']
            ]);
            
            // گرفتن ID کاربر ثبت شده
            $userId = $pdo->lastInsertId();
            if (!$userId) {
                $stmtGetId = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
                $stmtGetId->execute([$u['username']]);
                $userId = $stmtGetId->fetchColumn();
            }

            // ثبت اطلاعات جانبی متناسب با نقش
            if ($u['role'] === 'teacher') {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `teachers` WHERE `user_id` = ?");
                $stmtCheck->execute([$userId]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $pdo->prepare("INSERT INTO `teachers` (`user_id`, `bio`) VALUES (?, ?)")
                        ->execute([$userId, 'مدرس ریاضیات دوره متوسطه اول با ۱۰ سال سابقه تدریس.']);
                }
            } elseif ($u['role'] === 'student') {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `students` WHERE `user_id` = ?");
                $stmtCheck->execute([$userId]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $pdo->prepare("INSERT INTO `students` (`user_id`, `grade_level`) VALUES (?, ?)")
                        ->execute([$userId, 7]);
                }
            } elseif ($u['role'] === 'parent') {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `parents` WHERE `user_id` = ?");
                $stmtCheck->execute([$userId]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $pdo->prepare("INSERT INTO `parents` (`user_id`) VALUES (?)")
                        ->execute([$userId]);
                }
            }
        }

        // ۵. ایجاد نمونه داده کلاس، درس و تخصیص و پیوند والد-دانش‌آموز
        
        // پیدا کردن شناسه‌های دانش‌آموز، معلم و والد از جداول تخصصی
        $studentId = $pdo->query("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id WHERE u.username = 'student'")->fetchColumn();
        $teacherId = $pdo->query("SELECT t.id FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.username = 'teacher'")->fetchColumn();
        $parentId = $pdo->query("SELECT p.id FROM parents p JOIN users u ON p.user_id = u.id WHERE u.username = 'parent'")->fetchColumn();

        // پیوند والد به دانش‌آموز (پدر به پسر)
        if ($parentId && $studentId) {
            $pdo->exec("INSERT IGNORE INTO `parent_student` (`parent_id`, `student_id`) VALUES ($parentId, $studentId)");
        }

        // ایجاد یک کلاس پایه هفتم
        $pdo->exec("INSERT INTO `classes` (`id`, `class_name`, `grade_level`) VALUES (1, 'کلاس هفتم الف', 7) ON DUPLICATE KEY UPDATE `class_name` = VALUES(`class_name`)");
        
        // ایجاد درس ریاضی هفتم
        $pdo->exec("INSERT INTO `courses` (`id`, `course_name`, `grade_level`) VALUES (1, 'ریاضی هفتم', 7) ON DUPLICATE KEY UPDATE `course_name` = VALUES(`course_name`)");

        // ثبت‌نام دانش‌آموز در کلاس هفتم الف
        if ($studentId) {
            $pdo->exec("INSERT IGNORE INTO `class_student` (`class_id`, `student_id`) VALUES (1, $studentId)");
        }

        // تخصیص معلم ریاضی به درس ریاضی در کلاس هفتم الف
        if ($teacherId) {
            $pdo->exec("INSERT INTO `class_teacher_course` (`id`, `class_id`, `teacher_id`, `course_id`) VALUES (1, 1, $teacherId, 1) ON DUPLICATE KEY UPDATE `id` = `id`");
        }

        // ۶. ساختن یک فایل کانفیگ db.php آماده با مشخصات وارد شده
        $configContent = "<?php\n"
            . "// فایل پیکربندی پایگاه داده - تولید شده توسط نصاب خودکار\n"
            . "define('DB_HOST', '" . addslashes($host) . "');\n"
            . "define('DB_USER', '" . addslashes($db_user) . "');\n"
            . "define('DB_PASS', '" . addslashes($db_pass) . "');\n"
            . "define('DB_NAME', '" . addslashes($db_name) . "');\n\n"
            . "try {\n"
            . "    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS);\n"
            . "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n"
            . "    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n"
            . "} catch (PDOException \$e) {\n"
            . "    die(\"خطا در اتصال به پایگاه داده: \" . \$e->getMessage());\n"
            . "}\n";
        
        $configDir = dirname(__DIR__) . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        file_put_to_file: file_put_contents($configDir . '/db.php', $configContent);

        // ساخت پوشه آپلودها در صورتی که وجود نداشته باشد
        $uploadsDir = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $installed = true;
        $message = "پایگاه داده با موفقیت راه‌اندازی شد و فایل تنظیمات `config/db.php` ساخته شد.";

    } catch (Exception $e) {
        $error = "خطا در نصب: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب خودکار پایگاه داده سامانه مدرسه هوشمند</title>
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            min-height: 100vh;
            color: #F8FAFC;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
        }
        .form-control {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #F8FAFC;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #0D6EFD;
            color: #F8FAFC;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #0D6EFD;
            border: none;
            padding: 12px;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .badge-account {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="install-card">
    <div class="text-center mb-4">
        <h2 class="fw-bold mb-2">راه‌اندازی پایگاه داده</h2>
        <p class="text-secondary">سامانه مدیریت مدرسه و یادگیری هوشمند</p>
    </div>

    <?php if ($installed): ?>
        <div class="alert alert-success border-0 text-white bg-success bg-opacity-75" role="alert">
            <h5 class="alert-heading fw-bold mb-2">✓ نصب با موفقیت انجام شد!</h5>
            <p class="mb-0"><?= htmlspecialchars($message) ?></p>
        </div>

        <div class="mt-4">
            <h5 class="fw-bold mb-3">حساب‌های کاربری تستی جهت ورود:</h5>
            <div class="row g-2 text-start">
                <div class="col-md-6">
                    <div class="badge-account">
                        <strong>مدیر کل (Super Admin):</strong><br>
                        نام کاربری: <code class="text-info">admin</code><br>
                        کلمه عبور: <code class="text-info">admin123</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="badge-account">
                        <strong>اپراتور (Operator):</strong><br>
                        نام کاربری: <code class="text-info">operator</code><br>
                        کلمه عبور: <code class="text-info">operator123</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="badge-account">
                        <strong>معلم (Teacher):</strong><br>
                        نام کاربری: <code class="text-info">teacher</code><br>
                        کلمه عبور: <code class="text-info">teacher123</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="badge-account">
                        <strong>دانش‌آموز (Student):</strong><br>
                        نام کاربری: <code class="text-info">student</code><br>
                        کلمه عبور: <code class="text-info">student123</code>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="badge-account text-center">
                        <strong>والد (Parent):</strong><br>
                        نام کاربری: <code class="text-info">parent</code> | 
                        کلمه عبور: <code class="text-info">parent123</code>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-grid mt-4">
            <a href="../index.php" class="btn btn-primary btn-lg fw-bold">برو به صفحه ورود سیستم</a>
        </div>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 text-white bg-danger bg-opacity-75" role="alert">
                <strong>خطا:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="host" class="form-label">هاست دیتابیس (MySQL Host)</label>
                <input type="text" class="form-control" id="host" name="host" value="<?= htmlspecialchars($host) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="db_user" class="form-label">نام کاربری دیتابیس (DB Username)</label>
                <input type="text" class="form-control" id="db_user" name="db_user" value="<?= htmlspecialchars($db_user) ?>" required>
            </div>

            <div class="mb-3">
                <label for="db_pass" class="form-label">کلمه عبور دیتابیس (DB Password)</label>
                <input type="password" class="form-control" id="db_pass" name="db_pass" placeholder="خالی (در لوکال هاست)">
            </div>

            <div class="mb-3">
                <label for="db_name" class="form-label">نام دیتابیس (Database Name)</label>
                <input type="text" class="form-control" id="db_name" name="db_name" value="<?= htmlspecialchars($db_name) ?>" required>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg fw-bold">ایجاد دیتابیس و نصب جداول</button>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
