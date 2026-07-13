<?php
/**
 * صفحه ورود یکپارچه به سامانه مدیریت مدرسه هوشمند
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$db_configured = true;

// بررسی وجود فایل کانفیگ دیتابیس
if (!file_exists(__DIR__ . '/config/db.php')) {
    $db_configured = false;
} else {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/config/helpers.php';
    
    // اگر از قبل لاگین شده باشد، هدایت مستقیم به دشبورد مربوطه
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
        redirect($role . '/dashboard.php');
    }
}

// هندل کردن ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_configured) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'لطفاً نام کاربری و کلمه عبور را وارد نمایید.';
    } else {
        try {
            // جستجوی کاربر در دیتابیس
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? AND `status` = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // ایجاد نشست کاربری
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['national_code'] = $user['national_code'];

                // کارهای جانبی متناسب با نقش
                if ($user['role'] === 'parent') {
                    // گرفتن شناسه والد
                    $stmtP = $pdo->prepare("SELECT id FROM parents WHERE user_id = ?");
                    $stmtP->execute([$user['id']]);
                    $parentId = $stmtP->fetchColumn();

                    if ($parentId) {
                        // گرفتن اولین فرزند به عنوان فرزند فعال پیش‌فرض
                        $stmtC = $pdo->prepare("SELECT s.id, u.full_name FROM students s 
                            JOIN users u ON s.user_id = u.id 
                            JOIN parent_student ps ON s.id = ps.student_id 
                            WHERE ps.parent_id = ? LIMIT 1");
                        $stmtC->execute([$parentId]);
                        $child = $stmtC->fetch();
                        
                        if ($child) {
                            $_SESSION['active_child_id'] = $child['id'];
                            $_SESSION['active_child_name'] = $child['full_name'];
                        }
                    }
                }

                // هدایت به دشبورد اختصاصی بر اساس نقش
                redirect($user['role'] . '/dashboard.php');
            } else {
                $error = 'نام کاربری یا کلمه عبور اشتباه است.';
            }
        } catch (PDOException $e) {
            $error = 'خطا در ارتباط با دیتابیس: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه مدیریت مدرسه هوشمند</title>
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
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
        .login-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            max-width: 450px;
            width: 100%;
        }
        .brand-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.8rem;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        .form-control {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #F8FAFC;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.7);
            border-color: #3B82F6;
            color: #F8FAFC;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        .input-group-text {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #94A3B8;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="mb-3" style="width: 90px; height: 90px; margin: 0 auto;">
            <img src="assets/images/logo.png" alt="دبستان هوشمند" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <h3 class="fw-bold mb-1">ورود به سامانه دبستان هوشمند</h3>
        <p class="text-secondary small">مدیریت آموزشی و مدرسه هوشمند</p>
    </div>

    <?php if (!$db_configured): ?>
        <div class="alert alert-warning border-0 bg-warning bg-opacity-25 text-warning mb-4" role="alert">
            <h6 class="alert-heading fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>سیستم نصب نشده است!</h6>
            <p class="mb-3 small">به نظر می‌رسد پایگاه داده هنوز راه‌اندازی نشده است. لطفاً ابتدا اسکریپت نصب خودکار را اجرا نمایید.</p>
            <div class="d-grid">
                <a href="database/import.php" class="btn btn-warning btn-sm fw-bold">شروع راه‌اندازی و نصب دیتابیس</a>
            </div>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger small mb-3" role="alert">
                <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger small mb-3" role="alert">
                <i class="bi bi-shield-lock-fill me-1"></i> شما دسترسی لازم جهت مشاهده این صفحه را ندارید.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label text-white-50 small">نام کاربری</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="نام کاربری خود را وارد کنید" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-white-50 small">کلمه عبور</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="کلمه عبور خود را وارد کنید" required>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">ورود به پنل کاربری</button>
            </div>
        </form>
        
        <div class="text-center mt-4">
            <span class="text-secondary small">پشتیبانی و دیتابیس: </span>
            <a href="database/import.php" class="text-info text-decoration-none small">نصب مجدد دیتابیس</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
