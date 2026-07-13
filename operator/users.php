<?php
/**
 * مدیریت کاربران (معلمان، دانش‌آموزان و اولیا)
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$error = '';
$success = '';

// هندل کردن ثبت کاربر جدید دستی یا آپلود فایل CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_user') {
        // ثبت‌نام دستی کاربر جدید
        $full_name = trim($_POST['full_name'] ?? '');
        $national_code = trim($_POST['national_code'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $grade_level = (int)($_POST['grade_level'] ?? 0);
        $children_ids = $_POST['children_ids'] ?? []; // مخصوص اولیا

        if (empty($full_name) || empty($national_code) || empty($username) || empty($password) || empty($role)) {
            $error = 'پر کردن تمامی فیلدهای ستاره‌دار الزامی است.';
        } else {
            // آپلود و ولیدیشن تصویر پروفایل برای دبیر و دانش‌آموز
            $avatar_path = null;
            if (($role === 'student' || $role === 'teacher') && empty($error)) {
                if (!isset($_FILES['avatar_file']) || $_FILES['avatar_file']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'آپلود تصویر پروفایل برای دانش‌آموز یا معلم الزامی است.';
                } else {
                    $file_tmp = $_FILES['avatar_file']['tmp_name'];
                    $file_name = $_FILES['avatar_file']['name'];
                    $image_info = getimagesize($file_tmp);
                    
                    if (!$image_info) {
                        $error = 'فایل آپلود شده یک تصویر معتبر نیست.';
                    } else {
                        $width = $image_info[0];
                        $height = $image_info[1];
                        $mime = $image_info['mime'];
                        
                        $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/webp'];
                        if (!in_array($mime, $allowed_mimes)) {
                            $error = 'فرمت تصویر باید JPEG یا WebP باشد.';
                        } elseif ($width > 300 || $height > 300) {
                            $error = 'ابعاد تصویر پروفایل نمی‌تواند بزرگتر از ۳۰۰ در ۳۰۰ پیکسل باشد (ابعاد فعلی: ' . $width . 'x' . $height . ').';
                        } else {
                            $target_dir = __DIR__ . '/../uploads/avatars/';
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0755, true);
                            }
                            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                            if (empty($ext)) {
                                $ext = ($mime === 'image/webp') ? 'webp' : 'jpg';
                            }
                            $new_filename = uniqid('avatar_', true) . '.' . $ext;
                            $target_file = $target_dir . $new_filename;
                            
                            if (move_uploaded_file($file_tmp, $target_file)) {
                                $avatar_path = 'uploads/avatars/' . $new_filename;
                            } else {
                                $error = 'خطا در ذخیره‌سازی فایل تصویر پروفایل روی سرور.';
                            }
                        }
                    }
                }
            }

            if (empty($error)) {
                try {
                    $pdo->beginTransaction();

                    // هش کردن کلمه عبور
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // درج در جدول اصلی کاربران
                    $stmt = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `avatar_path`) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$national_code, $username, $hashed_password, $role, $full_name, $email, $phone, $avatar_path]);
                    $newUserId = $pdo->lastInsertId();

                // کارهای تکمیلی بر اساس نقش
                if ($role === 'student') {
                    $stmtS = $pdo->prepare("INSERT INTO `students` (`user_id`, `grade_level`) VALUES (?, ?)");
                    $stmtS->execute([$newUserId, $grade_level]);
                } elseif ($role === 'teacher') {
                    $designation = sanitize($_POST['designation'] ?? 'دبیر');
                    $stmtT = $pdo->prepare("INSERT INTO `teachers` (`user_id`, `bio`) VALUES (?, ?)");
                    $stmtT->execute([$newUserId, $designation]);
                } elseif ($role === 'parent') {
                    $stmtP = $pdo->prepare("INSERT INTO `parents` (`user_id`) VALUES (?)");
                    $stmtP->execute([$newUserId]);
                    $parentId = $pdo->lastInsertId();

                    // پیوند فرزندان به والد
                    if (!empty($children_ids) && $parentId) {
                        $stmtLink = $pdo->prepare("INSERT IGNORE INTO `parent_student` (`parent_id`, `student_id`) VALUES (?, ?)");
                        foreach ($children_ids as $sid) {
                            $stmtLink->execute([$parentId, $sid]);
                        }
                    }
                }

                $pdo->commit();
                $success = "کاربر جدید با موفقیت ثبت‌نام شد.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $error = "کد ملی یا نام کاربری تکراری است.";
                } else {
                    $error = "خطا در ثبت کاربر: " . $e->getMessage();
                }
            }
        }
    }
    } elseif ($action === 'edit_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $national_code = trim($_POST['national_code'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $grade_level = (int)($_POST['grade_level'] ?? 0);
        $designation = trim($_POST['designation'] ?? '');

        if ($userId <= 0 || empty($full_name) || empty($national_code) || empty($username) || empty($role)) {
            $error = 'پر کردن تمامی فیلدهای ستاره‌دار الزامی است.';
        } else {
            // آپلود تصویر پروفایل جدید در صورت انتخاب
            $avatar_path = null;
            $has_new_avatar = isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK;
            
            if ($has_new_avatar) {
                $file_tmp = $_FILES['avatar_file']['tmp_name'];
                $file_name = $_FILES['avatar_file']['name'];
                $image_info = getimagesize($file_tmp);
                
                if (!$image_info) {
                    $error = 'فایل آپلود شده یک تصویر معتبر نیست.';
                } else {
                    $width = $image_info[0];
                    $height = $image_info[1];
                    $mime = $image_info['mime'];
                    
                    $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/webp'];
                    if (!in_array($mime, $allowed_mimes)) {
                        $error = 'فرمت تصویر باید JPEG یا WebP باشد.';
                    } elseif ($width > 300 || $height > 300) {
                        $error = 'ابعاد تصویر پروفایل نمی‌تواند بزرگتر از ۳۰۰ در ۳۰۰ پیکسل باشد (ابعاد فعلی: ' . $width . 'x' . $height . ').';
                    } else {
                        $target_dir = __DIR__ . '/../uploads/avatars/';
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0755, true);
                        }
                        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        if (empty($ext)) {
                            $ext = ($mime === 'image/webp') ? 'webp' : 'jpg';
                        }
                        $new_filename = uniqid('avatar_', true) . '.' . $ext;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $target_file)) {
                            $avatar_path = 'uploads/avatars/' . $new_filename;
                        } else {
                            $error = 'خطا در ذخیره‌سازی فایل تصویر پروفایل روی سرور.';
                        }
                    }
                }
            }

            if (empty($error)) {
                try {
                    $pdo->beginTransaction();

                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        if ($has_new_avatar) {
                            $stmt = $pdo->prepare("UPDATE `users` SET `national_code` = ?, `username` = ?, `password` = ?, `role` = ?, `full_name` = ?, `email` = ?, `phone` = ?, `avatar_path` = ? WHERE `id` = ?");
                            $stmt->execute([$national_code, $username, $hashed_password, $role, $full_name, $email, $phone, $avatar_path, $userId]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE `users` SET `national_code` = ?, `username` = ?, `password` = ?, `role` = ?, `full_name` = ?, `email` = ?, `phone` = ? WHERE `id` = ?");
                            $stmt->execute([$national_code, $username, $hashed_password, $role, $full_name, $email, $phone, $userId]);
                        }
                    } else {
                        if ($has_new_avatar) {
                            $stmt = $pdo->prepare("UPDATE `users` SET `national_code` = ?, `username` = ?, `role` = ?, `full_name` = ?, `email` = ?, `phone` = ?, `avatar_path` = ? WHERE `id` = ?");
                            $stmt->execute([$national_code, $username, $role, $full_name, $email, $phone, $avatar_path, $userId]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE `users` SET `national_code` = ?, `username` = ?, `role` = ?, `full_name` = ?, `email` = ?, `phone` = ? WHERE `id` = ?");
                            $stmt->execute([$national_code, $username, $role, $full_name, $email, $phone, $userId]);
                        }
                    }

                    if ($role === 'student') {
                        $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                        $stmtCheck->execute([$userId]);
                        if ($stmtCheck->fetchColumn()) {
                            $stmtS = $pdo->prepare("UPDATE `students` SET `grade_level` = ? WHERE `user_id` = ?");
                            $stmtS->execute([$grade_level, $userId]);
                        } else {
                            $stmtS = $pdo->prepare("INSERT INTO `students` (`user_id`, `grade_level`) VALUES (?, ?)");
                            $stmtS->execute([$userId, $grade_level]);
                        }
                    } elseif ($role === 'teacher') {
                        $stmtCheck = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
                        $stmtCheck->execute([$userId]);
                        if ($stmtCheck->fetchColumn()) {
                            $stmtT = $pdo->prepare("UPDATE `teachers` SET `bio` = ? WHERE `user_id` = ?");
                            $stmtT->execute([$designation ?: 'دبیر', $userId]);
                        } else {
                            $stmtT = $pdo->prepare("INSERT INTO `teachers` (`user_id`, `bio`) VALUES (?, ?)");
                            $stmtT->execute([$userId, $designation ?: 'دبیر']);
                        }
                    }

                    $pdo->commit();
                    $success = "اطلاعات کاربر با موفقیت ویرایش شد.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if ($e->getCode() == 23000) {
                        $error = "کد ملی یا نام کاربری تکراری است.";
                    } else {
                        $error = "خطا در ویرایش کاربر: " . $e->getMessage();
                    }
                }
            }
        }
    } elseif ($action === 'import_csv') {
        // شبیه‌ساز ایمپورت CSV
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            $success_count = 0;
            $row_idx = 0;
            
            try {
                $pdo->beginTransaction();
                // خواندن سطر به سطر فایل CSV شبیه‌سازی شده
                // فرمت پیش‌فرض: FullName,NationalCode,Username,Password,Role,Phone,GradeLevel
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row_idx++;
                    if ($row_idx === 1) continue; // هدر را نادیده می‌گیریم
                    
                    if (count($data) >= 5) {
                        $full_name = trim($data[0]);
                        $national_code = trim($data[1]);
                        $username = trim($data[2]);
                        $password = trim($data[3]);
                        $role = trim($data[4]);
                        $phone = trim($data[5] ?? '');
                        $grade = (int)($data[6] ?? 7);
                        
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `phone`) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$national_code, $username, $hashed, $role, $full_name, $phone]);
                        $uId = $pdo->lastInsertId();
                        
                        if ($role === 'student') {
                            $pdo->prepare("INSERT INTO `students` (`user_id`, `grade_level`) VALUES (?, ?)")->execute([$uId, $grade]);
                        } elseif ($role === 'teacher') {
                            $pdo->prepare("INSERT INTO `teachers` (`user_id`, `bio`) VALUES (?, ?)")->execute([$uId, 'دبیر ثبت‌شده با CSV']);
                        } elseif ($role === 'parent') {
                            $pdo->prepare("INSERT INTO `parents` (`user_id`) VALUES (?)")->execute([$uId]);
                        }
                        $success_count++;
                    }
                }
                fclose($handle);
                $pdo->commit();
                $success = "تعداد $success_count کاربر با موفقیت از طریق فایل CSV در سامانه ثبت شدند.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "خطا در پردازش فایل CSV: " . $e->getMessage();
            }
        } else {
            $error = "خطا در بارگذاری فایل CSV.";
        }
    }
}

// واکشی لیست کلاس‌ها و دبیران برای منوی فیلترها
try {
    $allClasses = $pdo->query("SELECT id, class_name FROM classes ORDER BY grade_level ASC, class_name ASC")->fetchAll();
    $allTeachers = $pdo->query("SELECT t.id as teacher_id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY u.full_name ASC")->fetchAll();
} catch (PDOException $e) {
    die("خطا در بارگذاری فیلترها: " . $e->getMessage());
}

// فیلتر کردن لیست کاربران بر اساس سرچ یا نوع نقش
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? 'all';
$classFilter = (int)($_GET['class_id'] ?? 0);
$teacherFilter = (int)($_GET['teacher_id'] ?? 0);

// واکشی کاربران
try {
    $sql = "SELECT u.*, s.id as student_id,
            CASE 
                WHEN u.role = 'student' THEN (SELECT grade_level FROM students WHERE user_id = u.id)
                ELSE NULL 
            END as grade_level,
            (SELECT bio FROM teachers WHERE user_id = u.id) as designation
            FROM users u 
            LEFT JOIN students s ON u.id = s.user_id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.national_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($roleFilter !== 'all') {
        $sql .= " AND u.role = ?";
        $params[] = $roleFilter;
    }

    // اعمال فیلتر کلاس برای دانش‌آموزان
    if ($classFilter > 0) {
        $sql .= " AND (u.role = 'student' AND u.id IN (
            SELECT s2.user_id FROM students s2 
            JOIN class_student cs ON s2.id = cs.student_id 
            WHERE cs.class_id = ?
        ))";
        $params[] = $classFilter;
    }

    // اعمال فیلتر دبیر برای دانش‌آموزان
    if ($teacherFilter > 0) {
        $sql .= " AND (u.role = 'student' AND u.id IN (
            SELECT s3.user_id FROM students s3 
            JOIN class_student cs2 ON s3.id = cs2.student_id 
            JOIN class_teacher_course ctc ON cs2.class_id = ctc.class_id 
            WHERE ctc.teacher_id = ?
        ))";
        $params[] = $teacherFilter;
    }

    $sql .= " ORDER BY u.id DESC";
    $stmtUsers = $pdo->prepare($sql);
    $stmtUsers->execute($params);
    $usersList = $stmtUsers->fetchAll();
} catch (PDOException $e) {
    die("خطا در واکشی کاربران: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-25 text-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- سربرگ فیلترها و عملیات دشبورد -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="جستجو نام، کدملی، نام کاربری..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>همه نقش‌ها</option>
                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>دانش‌آموزان</option>
                        <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>دبیران</option>
                        <option value="parent" <?= $roleFilter === 'parent' ? 'selected' : '' ?>>والدین</option>
                        <option value="operator" <?= $roleFilter === 'operator' ? 'selected' : '' ?>>اپراتورها</option>
                    </select>
                </div>
                
                <!-- فیلتر کلاس/پایه برای دانش‌آموزان -->
                <div class="col-md-2">
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">همه کلاس‌ها</option>
                        <?php foreach ($allClasses as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= $classFilter == $cl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- فیلتر دبیر مربوطه برای دانش‌آموزان -->
                <div class="col-md-2">
                    <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">همه دبیران</option>
                        <?php foreach ($allTeachers as $tc): ?>
                            <option value="<?= $tc['teacher_id'] ?>" <?= $teacherFilter == $tc['teacher_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tc['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary w-100">فیلتر</button>
                </div>
                <div class="col-md-2 text-end d-flex gap-2">
                    <button type="button" class="btn btn-primary w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-lg me-1"></i> افزودن کاربر
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول کاربران -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <?php if (empty($usersList)): ?>
                <!-- نمای خالی (Empty State) شبیه کوالا -->
                <div class="empty-state">
                    <svg class="empty-state-img text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="120" height="120" style="fill: currentColor;">
                        <circle cx="32" cy="32" r="30" fill="#E2E8F0" />
                        <path d="M32 10c12 0 16 8 16 14s-4 12-16 12S16 30 16 24s4-14 16-14zm0 2c-10 0-14 6-14 12s3 10 14 10 14-4 14-10-4-12-14-12z" fill="#94A3B8" />
                        <circle cx="26" cy="24" r="3" fill="#64748B" />
                        <circle cx="38" cy="24" r="3" fill="#64748B" />
                        <path d="M32 28c-3 0-5 2-5 4s2 1 5 1 5-1 5-1-2-4-5-4z" fill="#475569" />
                    </svg>
                    <h5 class="empty-state-title">کاربری یافت نشد</h5>
                    <p class="empty-state-description">در حال حاضر هیچ کاربری با فیلترهای اعمال شده در سیستم وجود ندارد. از دکمه «افزودن کاربر» برای ثبت اولین عضو استفاده کنید.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>نام و نام خانوادگی</th>
                                <th>نام کاربری</th>
                                <th>کد ملی</th>
                                <th>نقش کاربری</th>
                                <th>شماره تماس</th>
                                <th>پایه تحصیلی</th>
                                <th>اقدام</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersList as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($user['avatar_path'])): ?>
                                                <img src="../<?= htmlspecialchars($user['avatar_path']) ?>" alt="<?= htmlspecialchars($user['full_name']) ?>" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-secondary" style="width: 40px; height: 40px; font-size: 0.95rem;">
                                                    <?= mb_substr($user['full_name'], 0, 1, 'utf-8') ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                                <small class="text-secondary"><?= htmlspecialchars($user['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                                    <td><?= htmlspecialchars($user['national_code']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= get_status_class($user['role']) ?>-subtle text-<?= get_status_class($user['role']) ?> border border-<?= get_status_class($user['role']) ?>-subtle">
                                            <?= get_role_fa($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['phone'] ?: 'ثبت‌نشده') ?></td>
                                    <td><?= $user['grade_level'] ? 'پایه ' . $user['grade_level'] : '-' ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-light btn-sm text-primary" title="ویرایش پروفایل" 
                                                data-id="<?= $user['id'] ?>"
                                                data-full-name="<?= htmlspecialchars($user['full_name']) ?>"
                                                data-national-code="<?= htmlspecialchars($user['national_code']) ?>"
                                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                                data-role="<?= htmlspecialchars($user['role']) ?>"
                                                data-phone="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                                data-email="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                                data-grade-level="<?= $user['grade_level'] ?? '' ?>"
                                                data-designation="<?= htmlspecialchars($user['designation'] ?? '') ?>"
                                                onclick="openEditUserModal(this)">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php if ($user['role'] === 'student' && $user['student_id']): ?>
                                                <button type="button" class="btn btn-light btn-sm d-flex align-items-center justify-content-center" title="نشان‌های مهارتی و انضباطی" style="padding: 4px; width: 32px; height: 32px;" onclick="openStudentBadgesModal(<?= $user['student_id'] ?>, '<?= htmlspecialchars(addslashes($user['full_name'])) ?>')">
                                                    <img src="../assets/images/medal.png" alt="نشان‌ها" style="width: 20px; height: 20px; object-fit: contain;">
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال ثبت‌نام کاربر جدید به همراه تب‌های دستی و فایل CSV -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addUserModalLabel">افزودن کاربر جدید به سیستم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- سربرگ تب‌ها -->
                <ul class="nav nav-tabs custom-tabs px-3 bg-light border-bottom-0" id="userImportTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button" role="tab" aria-controls="manual-pane" aria-selected="true">
                            <i class="bi bi-person-fill-add me-1"></i> ثبت دستی کاربر
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv-pane" type="button" role="tab" aria-controls="csv-pane" aria-selected="false">
                            <i class="bi bi-filetype-csv me-1"></i> ورود اطلاعات گروهی (CSV)
                        </button>
                    </li>
                </ul>
                
                <!-- بدنه تب‌ها -->
                <div class="tab-content p-4" id="userImportTabContent">
                    <!-- تب ثبت دستی -->
                    <div class="tab-pane fade show active" id="manual-pane" role="tabpanel" aria-labelledby="manual-tab">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_user">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">نام و نام خانوادگی *</label>
                                    <input type="text" name="full_name" class="form-control" placeholder="مثال: رضا احمدی" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">کد ملی *</label>
                                    <input type="text" name="national_code" class="form-control" maxlength="10" placeholder="مثال: 0012345678" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">نام کاربری *</label>
                                    <input type="text" name="username" class="form-control" placeholder="مثال: ahmadi" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">کلمه عبور اولیه *</label>
                                    <input type="password" name="password" class="form-control" placeholder="مثال: 123456" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">نقش کاربر *</label>
                                    <select name="role" class="form-select" id="roleSelect" onchange="toggleRoleFields()" required>
                                        <option value="">انتخاب کنید...</option>
                                        <option value="student">دانش‌آموز</option>
                                        <option value="teacher">دبیر</option>
                                        <option value="parent">ولی دانش‌آموز</option>
                                        <option value="operator">اپراتور</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">شماره همراه</label>
                                    <input type="text" name="phone" class="form-control" placeholder="مثال: 09123456789">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ایمیل</label>
                                    <input type="email" name="email" class="form-control" placeholder="example@mail.com">
                                </div>
                                
                                <!-- فیلد مخصوص معلم -->
                                <div class="col-md-12 d-none" id="teacherFields">
                                    <label class="form-label">عنوان تخصص/سمت</label>
                                    <input type="text" name="designation" class="form-control" placeholder="مثال: دبیر ریاضی، مسئول پژوهش">
                                </div>

                                <!-- فیلد مخصوص دانش‌آموز -->
                                <div class="col-md-12 d-none" id="studentFields">
                                    <label class="form-label">پایه تحصیلی *</label>
                                    <select name="grade_level" class="form-select">
                                        <option value="7">پایه هفتم</option>
                                        <option value="8">پایه هشتم</option>
                                        <option value="9">پایه نهم</option>
                                        <option value="10">پایه دهم</option>
                                        <option value="11">پایه یازدهم</option>
                                        <option value="12">پایه دوازدهم</option>
                                    </select>
                                </div>

                                <!-- فیلد آپلود تصویر پروفایل مخصوص دانش‌آموز و دبیر -->
                                <div class="col-md-12 d-none" id="avatarUploadField">
                                    <label class="form-label fw-bold">تصویر پروفایل * (JPEG یا WebP - حداکثر ابعاد ۳۰۰ در ۳۰۰ پیکسل)</label>
                                    <input type="file" name="avatar_file" class="form-control" accept="image/jpeg, image/jpg, image/webp" id="avatarFileInput">
                                    <small class="text-secondary small">آپلود عکس پرسنلی معتبر الزامی است.</small>
                                </div>

                                <!-- متصل کننده هوشمند والد-دانش‌آموز -->
                                <div class="col-md-12 d-none" id="parentFields">
                                    <label class="form-label text-primary fw-bold"><i class="bi bi-link-45deg"></i> جستجوی هوشمند و اتصال فرزندان به ولی</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="studentSearchInput" class="form-control" placeholder="نام یا کدملی دانش‌آموز را سرچ کنید...">
                                        <button class="btn btn-outline-primary" type="button" onclick="searchStudents()"><i class="bi bi-search"></i> جستجو</button>
                                    </div>
                                    <!-- لیست نتایج جستجو -->
                                    <ul class="list-group mb-3 shadow-sm d-none" id="searchResultsList" style="max-height: 150px; overflow-y: auto;"></ul>
                                    <!-- لیست فرزندان انتخاب شده -->
                                    <div class="border rounded p-3 bg-light">
                                        <label class="fw-bold mb-2 small text-secondary">لیست فرزندان متصل شده:</label>
                                        <div id="selectedChildrenContainer" class="d-flex flex-wrap gap-2">
                                            <span class="text-secondary small">هیچ فرزندی پیوند داده نشده است.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 text-end">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                                <button type="submit" class="btn btn-primary px-4 fw-bold">ثبت نهایی کاربر</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- تب ایمپورت CSV -->
                    <div class="tab-pane fade" id="csv-pane" role="tabpanel" aria-labelledby="csv-tab">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="import_csv">
                            <div class="border rounded-3 p-4 text-center bg-light mb-3">
                                <i class="bi bi-file-earmark-arrow-up text-primary fs-1 mb-2"></i>
                                <h6 class="fw-bold">فایل CSV کاربران را بارگذاری کنید</h6>
                                <p class="text-secondary small mb-3">فرمت فایل باید به صورت ستون‌های مقابل باشد: نام، کدملی، نام کاربری، کلمه عبور، نقش(student|teacher|parent)، شماره همراه، پایه تحصیلی</p>
                                <div class="col-md-6 mx-auto">
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                                <button type="submit" class="btn btn-primary px-4 fw-bold">شروع ایمپورت گروهی</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // بستن و نمایش فیلدهای پویا بر اساس نقش کاربر
    function toggleRoleFields() {
        const role = document.getElementById('roleSelect').value;
        document.getElementById('teacherFields').classList.add('d-none');
        document.getElementById('studentFields').classList.add('d-none');
        document.getElementById('parentFields').classList.add('d-none');
        document.getElementById('avatarUploadField').classList.add('d-none');
        document.getElementById('avatarFileInput').removeAttribute('required');

        if (role === 'teacher') {
            document.getElementById('teacherFields').classList.remove('d-none');
            document.getElementById('avatarUploadField').classList.remove('d-none');
            document.getElementById('avatarFileInput').setAttribute('required', 'required');
        } else if (role === 'student') {
            document.getElementById('studentFields').classList.remove('d-none');
            document.getElementById('avatarUploadField').classList.remove('d-none');
            document.getElementById('avatarFileInput').setAttribute('required', 'required');
        } else if (role === 'parent') {
            document.getElementById('parentFields').classList.remove('d-none');
        }
    }

    // متصل کننده هوشمند: جستجوی دانش‌آموز با AJAX
    function searchStudents() {
        const query = document.getElementById('studentSearchInput').value.trim();
        const resultsList = document.getElementById('searchResultsList');
        if (query === '') return;

        fetch(`../ajax/parent_search.php?query=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                resultsList.innerHTML = '';
                resultsList.classList.remove('d-none');
                
                if (data.length === 0) {
                    resultsList.innerHTML = '<li class="list-group-item text-secondary small">دانش‌آموزی با این مشخصات یافت نشد.</li>';
                } else {
                    data.forEach(student => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center small';
                        li.innerHTML = `
                            <span>${student.full_name} (${student.username}) - پایه ${student.grade_level}</span>
                            <button type="button" class="btn btn-primary btn-sm py-1 px-2" onclick="addChildLink(${student.id}, '${student.full_name}')">
                                <i class="bi bi-plus-lg"></i> اتصال
                            </button>
                        `;
                        resultsList.appendChild(li);
                    });
                }
            })
            .catch(err => console.error("خطا در جستجو:", err));
    }

    const selectedChildren = new Set();

    // اضافه کردن دانش‌آموز به لیست فرزندان متصل
    function addChildLink(id, name) {
        if (selectedChildren.has(id)) return;
        selectedChildren.add(id);

        const container = document.getElementById('selectedChildrenContainer');
        
        // در صورتی که این اولین فرزند باشد متن پیش‌فرض را حذف می‌کنیم
        if (selectedChildren.size === 1) {
            container.innerHTML = '';
        }

        const badge = document.createElement('div');
        badge.className = 'badge bg-primary d-flex align-items-center gap-2 p-2';
        badge.id = `child-badge-${id}`;
        badge.innerHTML = `
            <span>${name}</span>
            <input type="hidden" name="children_ids[]" value="${id}">
            <i class="bi bi-x-circle-fill cursor-pointer" onclick="removeChildLink(${id})"></i>
        `;
        container.appendChild(badge);
        
        // مخفی کردن باکس نتایج
        document.getElementById('searchResultsList').classList.add('d-none');
        document.getElementById('studentSearchInput').value = '';
    }

    // حذف دانش‌آموز از لیست متصل شده
    function removeChildLink(id) {
        selectedChildren.delete(id);
        const badge = document.getElementById(`child-badge-${id}`);
        if (badge) badge.remove();

        if (selectedChildren.size === 0) {
            document.getElementById('selectedChildrenContainer').innerHTML = '<span class="text-secondary small">هیچ فرزندی پیوند داده نشده است.</span>';
        }
    }
</script>

<!-- مودال نشان‌های مهارتی دانش‌آموز -->
<div class="modal fade" id="studentBadgesModal" tabindex="-1" aria-labelledby="studentBadgesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="studentBadgesModalLabel">نشان‌های مهارتی دانش‌آموز</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">دانش‌آموز: <strong id="badgeModalStudentName"></strong></p>
                
                <!-- لیست نشان‌های کسب‌شده فعلی -->
                <h6 class="fw-bold mb-2 text-secondary"><i class="bi bi-award-fill text-warning me-1"></i> نشان‌های کسب شده:</h6>
                <div id="studentBadgesListContainer" class="d-flex flex-wrap gap-2 mb-4 p-3 bg-light rounded-3 border border-dashed">
                    <!-- به صورت داینامیک پر می‌شود -->
                </div>
                
                <!-- فرم اهدای نشان جدید -->
                <h6 class="fw-bold mb-2 text-secondary"><i class="bi bi-plus-circle-fill text-primary me-1"></i> اهدای نشان جدید:</h6>
                <div class="row g-2">
                    <div class="col-8">
                        <select id="selectBadgeToAssign" class="form-select">
                            <!-- نشان‌ها به صورت داینامیک پر می‌شوند -->
                        </select>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-primary w-100 fw-bold btn-sm h-100" onclick="assignBadgeToStudent()">
                            <i class="bi bi-plus-lg me-1"></i> اهدا کردن
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
    let activeStudentIdForBadges = 0;

    function openStudentBadgesModal(studentId, studentName) {
        activeStudentIdForBadges = studentId;
        document.getElementById('badgeModalStudentName').innerText = studentName;
        
        // باز کردن مودال و لود کردن نشان‌ها
        loadStudentBadgesList();
        loadAllAvailableBadgesSelect();
        
        const modalEl = document.getElementById('studentBadgesModal');
        new bootstrap.Modal(modalEl).show();
    }

    function loadStudentBadgesList() {
        const container = document.getElementById('studentBadgesListContainer');
        container.innerHTML = '<div class="text-center py-2 w-100"><span class="spinner-border spinner-border-sm text-secondary" role="status"></span> در حال بارگذاری...</div>';
        
        fetch(`../ajax/student_badges.php?action=list&student_id=${activeStudentIdForBadges}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.badges.length === 0) {
                        container.innerHTML = '<span class="text-secondary small">هنوز نشانی به این دانش‌آموز اهدا نشده است.</span>';
                    } else {
                        container.innerHTML = '';
                        data.badges.forEach(b => {
                            let iconHtml = '';
                            if (b.icon.indexOf('uploads/') === 0) {
                                iconHtml = `<img src="../${b.icon}" alt="${b.name}" style="width: 24px; height: 24px; object-fit: contain; margin-left: 6px;">`;
                            } else {
                                iconHtml = `<i class="bi bi-${b.icon} me-1 fs-5 text-secondary"></i>`;
                            }

                            const badgeItem = document.createElement('div');
                            badgeItem.className = `badge bg-light text-dark border d-inline-flex align-items-center gap-1 p-2 rounded-pill shadow-xs`;
                            badgeItem.innerHTML = `
                                ${iconHtml}
                                <span class="fw-bold">${b.name}</span>
                                <i class="bi bi-x-circle-fill text-danger cursor-pointer ms-1" title="لغو نشان" onclick="revokeBadgeFromStudent(${b.id})"></i>
                            `;
                            container.appendChild(badgeItem);
                        });
                    }
                } else {
                    container.innerHTML = `<span class="text-danger small">خطا: ${data.message}</span>`;
                }
            })
            .catch(err => {
                container.innerHTML = '<span class="text-danger small">خطا در اتصال به سرور.</span>';
            });
    }

    function loadAllAvailableBadgesSelect() {
        const select = document.getElementById('selectBadgeToAssign');
        select.innerHTML = '<option>در حال بارگذاری نشان‌ها...</option>';
        
        fetch(`../ajax/student_badges.php?action=all_badges&student_id=${activeStudentIdForBadges}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.badges.length === 0) {
                        select.innerHTML = '<option value="">تمام نشان‌ها اهدا شده‌اند</option>';
                    } else {
                        select.innerHTML = '';
                        data.badges.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.innerText = `${b.name} (${b.type === 'educational' ? 'آموزشی' : 'انضباطی'})`;
                            select.appendChild(opt);
                        });
                    }
                }
            });
    }

    function assignBadgeToStudent() {
        const select = document.getElementById('selectBadgeToAssign');
        const badgeId = select.value;
        if (!badgeId) return;
        
        fetch(`../ajax/student_badges.php?action=assign`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `student_id=${activeStudentIdForBadges}&badge_id=${badgeId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                loadStudentBadgesList();
                loadAllAvailableBadgesSelect();
            } else {
                alert(data.message);
            }
        });
    }

    function revokeBadgeFromStudent(badgeId) {
        showCustomConfirm(
            'لغو نشان دانش‌آموز',
            'آیا مطمئن هستید که می‌خواهید این نشان را از دانش‌آموز پس بگیرید؟',
            'warning',
            function(confirmed) {
                if (confirmed) {
                    fetch(`../ajax/student_badges.php?action=revoke`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `student_id=${activeStudentIdForBadges}&badge_id=${badgeId}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            loadStudentBadgesList();
                            loadAllAvailableBadgesSelect();
                        } else {
                            alert(data.message);
                        }
                    });
                }
            }
        );
    }
</script>

<!-- مودال ویرایش کاربر -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="editUserModalLabel">ویرایش اطلاعات کاربر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">نام و نام خانوادگی *</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">کد ملی *</label>
                            <input type="text" name="national_code" id="edit_national_code" class="form-control" maxlength="10" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نام کاربری *</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">کلمه عبور جدید (در صورت نیاز به تغییر)</label>
                            <input type="password" name="password" class="form-control" placeholder="برای عدم تغییر خالی بگذارید">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">نقش کاربر *</label>
                            <select name="role" class="form-select" id="editRoleSelect" onchange="toggleEditRoleFields()" required>
                                <option value="student">دانش‌آموز</option>
                                <option value="teacher">دبیر</option>
                                <option value="parent">ولی دانش‌آموز</option>
                                <option value="operator">اپراتور</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">شماره همراه</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ایمیل</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        
                        <!-- فیلد مخصوص معلم -->
                        <div class="col-md-12 d-none" id="editTeacherFields">
                            <label class="form-label">عنوان تخصص/سمت</label>
                            <input type="text" name="designation" id="edit_designation" class="form-control" placeholder="مثال: دبیر ریاضی، مسئول پژوهش">
                        </div>

                        <!-- فیلد مخصوص دانش‌آموز -->
                        <div class="col-md-12 d-none" id="editStudentFields">
                            <label class="form-label">پایه تحصیلی *</label>
                            <select name="grade_level" id="edit_grade_level" class="form-select">
                                <option value="7">پایه هفتم</option>
                                <option value="8">پایه هشتم</option>
                                <option value="9">پایه نهم</option>
                                <option value="10">پایه دهم</option>
                                <option value="11">پایه یازدهم</option>
                                <option value="12">پایه دوازدهم</option>
                            </select>
                        </div>

                        <!-- فیلد آپلود تصویر پروفایل -->
                        <div class="col-md-12 d-none" id="editAvatarUploadField">
                            <label class="form-label fw-bold">تصویر پروفایل جدید (JPEG یا WebP - حداکثر ابعاد ۳۰۰ در ۳۰۰ پیکسل)</label>
                            <input type="file" name="avatar_file" class="form-control" accept="image/jpeg, image/jpg, image/webp" id="editAvatarFileInput">
                            <small class="text-secondary small">جهت ویرایش تصویر قبلی، تصویر جدید انتخاب کنید.</small>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">ثبت تغییرات کاربر</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openEditUserModal(btn) {
        const id = btn.getAttribute('data-id');
        const fullName = btn.getAttribute('data-full-name');
        const nationalCode = btn.getAttribute('data-national-code');
        const username = btn.getAttribute('data-username');
        const role = btn.getAttribute('data-role');
        const phone = btn.getAttribute('data-phone');
        const email = btn.getAttribute('data-email');
        const gradeLevel = btn.getAttribute('data-grade-level');
        const designation = btn.getAttribute('data-designation');
        
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_full_name').value = fullName;
        document.getElementById('edit_national_code').value = nationalCode;
        document.getElementById('edit_username').value = username;
        document.getElementById('editRoleSelect').value = role;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_email').value = email;
        if (gradeLevel) {
            document.getElementById('edit_grade_level').value = gradeLevel;
        } else {
            document.getElementById('edit_grade_level').value = "7";
        }
        if (designation) {
            document.getElementById('edit_designation').value = designation;
        } else {
            document.getElementById('edit_designation').value = "";
        }
        
        toggleEditRoleFields();
        
        const modalEl = document.getElementById('editUserModal');
        new bootstrap.Modal(modalEl).show();
    }

    function toggleEditRoleFields() {
        const role = document.getElementById('editRoleSelect').value;
        document.getElementById('editTeacherFields').classList.add('d-none');
        document.getElementById('editStudentFields').classList.add('d-none');
        document.getElementById('editAvatarUploadField').classList.add('d-none');

        if (role === 'teacher') {
            document.getElementById('editTeacherFields').classList.remove('d-none');
            document.getElementById('editAvatarUploadField').classList.remove('d-none');
        } else if (role === 'student') {
            document.getElementById('editStudentFields').classList.remove('d-none');
            document.getElementById('editAvatarUploadField').classList.remove('d-none');
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
