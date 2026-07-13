<?php
/**
 * حضور و غیاب دیجیتال به همراه قفل امنیتی ۲۴ ساعته تغییرات
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$success = '';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$date_param = $_GET['date'] ?? '';
if (empty($date_param)) {
    $gregorian_date = date('Y-m-d');
    $shamsi_date = gregorian_to_jalali(date('Y'), date('m'), date('d'));
} else {
    if (strpos($date_param, '/') !== false) {
        $shamsi_date = $date_param;
        $gregorian_date = parse_shamsi_to_gregorian($date_param);
    } else {
        $gregorian_date = $date_param;
        $parts = explode('-', $gregorian_date);
        $shamsi_date = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
    }
}
$date = $gregorian_date;
$studentsList = [];
$attendanceData = [];
$isLocked = false;
$initialRegistrationTime = null;

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // واکشی لیست کلاس‌های این معلم جهت فیلتر
    $stmtClasses = $pdo->prepare("SELECT DISTINCT c.id, c.class_name 
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        WHERE ctc.teacher_id = ?");
    $stmtClasses->execute([$teacherId]);
    $myClasses = $stmtClasses->fetchAll();

} catch (PDOException $e) {
    die("خطا در لود اطلاعات کلاس‌های دبیر: " . $e->getMessage());
}

// اگر کلاس انتخاب شده باشد
if ($class_id > 0) {
    try {
        // ۱. واکشی تمام دانش‌آموزان این کلاس
        $stmtStudents = $pdo->prepare("SELECT s.id, u.full_name, u.username 
            FROM class_student cs
            JOIN students s ON cs.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE cs.class_id = ? AND u.status = 1
            ORDER BY u.full_name ASC");
        $stmtStudents->execute([$class_id]);
        $studentsList = $stmtStudents->fetchAll();

        // ۲. واکشی وضعیت حضور و غیاب ثبت شده قبلی برای این کلاس و تاریخ
        $stmtAttend = $pdo->prepare("SELECT student_id, status, created_at FROM attendance WHERE class_id = ? AND date = ?");
        $stmtAttend->execute([$class_id, $date]);
        $rows = $stmtAttend->fetchAll();
        
        foreach ($rows as $row) {
            $attendanceData[$row['student_id']] = $row['status'];
            if (!$initialRegistrationTime) {
                $initialRegistrationTime = $row['created_at'];
            }
        }

        // ۳. بررسی قفل ۲۴ ساعته حضور و غیاب
        if ($initialRegistrationTime) {
            $createdTimestamp = strtotime($initialRegistrationTime);
            $currentTimestamp = time();
            $hourDifference = ($currentTimestamp - $createdTimestamp) / 3600;

            if ($hourDifference > 24) {
                $isLocked = true;
                $error = "قفل امنیت داده‌ها: از مهلت ۲۴ ساعته ویرایش حضور و غیاب این روز گذشته است. ویرایش مسدود می‌باشد.";
            }
        }

    } catch (PDOException $e) {
        $error = "خطا در بارگذاری اطلاعات حضور و غیاب: " . $e->getMessage();
    }
}

// هندل کردن ذخیره حضور و غیاب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $class_id > 0) {
    if ($isLocked) {
        $error = "خطا: دسترسی ویرایش به دلیل انقضای مهلت ۲۴ ساعته مسدود است.";
    } else {
        $statuses = $_POST['status'] ?? []; // ساختار: student_id => present|absent

        try {
            $pdo->beginTransaction();

            $stmtUpsert = $pdo->prepare("INSERT INTO `attendance` (`class_id`, `student_id`, `date`, `status`, `registered_by`) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `created_at` = CURRENT_TIMESTAMP()");

            foreach ($studentsList as $student) {
                $sId = $student['id'];
                $status = isset($statuses[$sId]) ? $statuses[$sId] : 'absent'; // پیش‌فرض غایب در صورت عدم ارسال

                $stmtUpsert->execute([$class_id, $sId, $date, $status, $_SESSION['user_id']]);
            }

            $pdo->commit();
            $success = "لیست حضور و غیاب با موفقیت در سیستم ثبت گردید.";
            
            // رفرش داده‌ها
            redirect("attendance.php?class_id=$class_id&date=$shamsi_date&success=1");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "خطا در ثبت حضور و غیاب: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $success = "لیست حضور و غیاب با موفقیت در سیستم ثبت گردید.";
}
?>

<div class="container-fluid">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-shield-lock-fill me-1"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-25 text-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- فیلتر کلاس و تاریخ -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <label class="form-label mb-0 me-2 d-inline-block fw-bold">انتخاب کلاس:</label>
                    <select name="class_id" class="form-select d-inline-block w-auto" required onchange="this.form.submit()">
                        <option value="">-- انتخاب کلاس درس --</option>
                        <?php foreach ($myClasses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $class_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label mb-0 me-2 d-inline-block fw-bold">تاریخ حضور و غیاب:</label>
                    <input type="text" data-jdp name="date" class="form-control d-inline-block w-auto" value="<?= htmlspecialchars($shamsi_date) ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>

    <?php if ($class_id <= 0): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center">
            <div class="card-body">
                <i class="bi bi-calendar-check fs-1 text-secondary mb-3"></i>
                <h5 class="fw-bold">بخش دفتر حضور و غیاب هوشمند</h5>
                <p class="text-secondary small">لطفاً کلاس درس و تاریخ مورد نظر را جهت ورود اطلاعات انتخاب کنید.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- جدول ثبت حضور و غیاب -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">ثبت حضور و غیاب دانش‌آموزان</h5>
                <?php if ($initialRegistrationTime): ?>
                    <small class="text-secondary">ثبت اولیه: <?= to_shamsi($initialRegistrationTime) ?> (ساعت <?= date('H:i', strtotime($initialRegistrationTime)) ?>)</small>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="table custom-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>نام و نام خانوادگی دانش‌آموز</th>
                                    <th>نام کاربری</th>
                                    <th class="text-center" style="width: 200px;">وضعیت حضور</th>
                                    <th class="text-center" style="width: 200px;">وضعیت غیبت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsList as $st): 
                                    $status = isset($attendanceData[$st['id']]) ? $attendanceData[$st['id']] : 'present';
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($st['full_name']) ?></td>
                                        <td><code><?= htmlspecialchars($st['username']) ?></code></td>
                                        <td class="text-center">
                                            <div class="form-check form-check-inline justify-content-center">
                                                <input class="form-check-input" type="radio" name="status[<?= $st['id'] ?>]" id="pres-<?= $st['id'] ?>" value="present" <?= $status === 'present' ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                                                <label class="form-check-label text-success fw-bold" for="pres-<?= $st['id'] ?>">حاضر</label>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-check-inline justify-content-center">
                                                <input class="form-check-input" type="radio" name="status[<?= $st['id'] ?>]" id="abs-<?= $st['id'] ?>" value="absent" <?= $status === 'absent' ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                                                <label class="form-check-label text-danger fw-bold" for="abs-<?= $st['id'] ?>">غایب</label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!$isLocked): ?>
                        <div class="p-3 bg-light text-end">
                            <button type="submit" class="btn btn-primary fw-bold px-4">ذخیره‌سازی اطلاعات حضور و غیاب</button>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-light-subtle text-center text-danger fw-bold small">
                            <i class="bi bi-lock-fill"></i> امکان ویرایش به دلیل اتمام زمان ۲۴ ساعته غیرفعال است.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
