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

    // واکشی داده‌های تقویم حضور و غیاب برای ماه جاری
    $months = [
        1 => "فروردین", 2 => "اردیبهشت", 3 => "خرداد", 4 => "تیر", 5 => "مرداد", 6 => "شهریور",
        7 => "مهر", 8 => "آبان", 9 => "آذر", 10 => "دی", 11 => "بهمن", 12 => "اسفند"
    ];
    $today_jalali = gregorian_to_jalali_array(date('Y'), date('m'), date('d'));
    $cy = $today_jalali['y'];
    $cm = $today_jalali['m'];

    $num_days = 30;
    if ($cm >= 1 && $cm <= 6) {
        $num_days = 31;
    } elseif ($cm === 12) {
        $num_days = 29;
    }

    $startDateGreg = jalali_to_gregorian($cy, $cm, 1);
    $endDateGreg = jalali_to_gregorian($cy, $cm, $num_days);

    // روز شروع هفته برای اولین روز ماه جاری
    $gregorian_w = (int)date('w', strtotime($startDateGreg));
    // 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat -> تبدیل به شروع با شنبه (0=Sat)
    $start_w = ($gregorian_w + 1) % 7;

    $stmtMonthly = $pdo->prepare("
        SELECT a.date, a.class_id, c.class_name,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        WHERE a.registered_by = ? 
          AND a.date >= ? AND a.date <= ?
        GROUP BY a.date, a.class_id, c.class_name
    ");
    $stmtMonthly->execute([$_SESSION['user_id'], $startDateGreg, $endDateGreg]);
    $monthlyLogs = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

    $calendarData = [];
    for ($d = 1; $d <= $num_days; $d++) {
        $calendarData[$d] = [];
    }
    foreach ($monthlyLogs as $log) {
        $parts = explode('-', $log['date']);
        $j = gregorian_to_jalali_array($parts[0], $parts[1], $parts[2]);
        if ($j['y'] == $cy && $j['m'] == $cm) {
            $calendarData[$j['d']][] = $log;
        }
    }

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
        <style>
            /* طراحی تقویم کلاسیک Clay UI */
            .calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 10px;
                margin-top: 15px;
            }

            .calendar-header-cell {
                text-align: center;
                font-weight: bold;
                color: var(--text-secondary);
                font-size: 0.85rem;
                padding: 10px 0;
                background-color: var(--bg-light);
                border-radius: 10px;
            }

            .calendar-day-cell {
                background-color: #ffffff;
                border: 1px solid var(--border);
                border-radius: 14px;
                min-height: 110px;
                padding: 8px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transition: all 0.2s ease;
            }

            .calendar-day-cell:hover {
                box-shadow: var(--shadow-sm);
                transform: translateY(-2px);
                border-color: var(--primary);
            }

            .calendar-day-cell.empty {
                background-color: transparent;
                border: none;
            }

            .calendar-day-cell.empty:hover {
                transform: none;
                box-shadow: none;
            }

            .calendar-day-cell.today {
                border: 2px solid var(--primary);
                background-color: rgba(29, 78, 216, 0.03);
            }

            .day-number {
                font-weight: bold;
                font-size: 0.95rem;
                color: var(--text-primary);
                align-self: flex-start;
                width: 26px;
                height: 26px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
            }

            .calendar-day-cell.today .day-number {
                background-color: var(--primary);
                color: #ffffff;
            }

            .day-events {
                margin-top: 6px;
                display: flex;
                flex-direction: column;
                gap: 4px;
                overflow-y: auto;
                max-height: 80px;
                scrollbar-width: none;
            }
            .day-events::-webkit-scrollbar { display: none; }

            .day-event-badge {
                font-size: 0.7rem;
                padding: 4px 8px;
                border-radius: 8px;
                line-height: 1.4;
                text-align: right;
            }
            .hide-scrollbar::-webkit-scrollbar {
                display: none;
            }
            .hide-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar3 text-primary me-2"></i>
                    نمای کلی وضعیت حضور و غیاب ماه جاری (<?= $months[$cm] ?> <?= $cy ?>)
                </h5>
                <span class="badge bg-secondary p-2">امروز: <?= to_shamsi(date('Y-m-d')) ?></span>
            </div>
            <div class="card-body">
                <p class="text-secondary small mb-3">
                    <i class="bi bi-info-circle-fill text-info me-1"></i>
                    جهت ثبت یا ویرایش لیست حضور و غیاب روزانه، لطفاً ابتدا کلاس درس و تاریخ مد نظر خود را از باکس بالا انتخاب کنید.
                </p>
                <div class="table-responsive hide-scrollbar">
                    <div class="calendar-grid" style="min-width: 800px;">
                        <!-- هدرهای روزهای هفته -->
                        <div class="calendar-header-cell">شنبه</div>
                        <div class="calendar-header-cell">یکشنبه</div>
                        <div class="calendar-header-cell">دوشنبه</div>
                        <div class="calendar-header-cell">سه‌شنبه</div>
                        <div class="calendar-header-cell">چهارشنبه</div>
                        <div class="calendar-header-cell">پنج‌شنبه</div>
                        <div class="calendar-header-cell" style="color: var(--danger);">جمعه</div>
                        
                        <!-- خانه‌های خالی شروع ماه -->
                        <?php for ($i = 0; $i < $start_w; $i++): ?>
                            <div class="calendar-day-cell empty"></div>
                        <?php endfor; ?>
                        
                        <!-- روزهای ماه -->
                        <?php for ($d = 1; $d <= $num_days; $d++): 
                            $isToday = ($d == $today_jalali['d']);
                            $logs = $calendarData[$d] ?? [];
                        ?>
                            <div class="calendar-day-cell <?= $isToday ? 'today' : '' ?>">
                                <div class="day-number"><?= $d ?></div>
                                <div class="day-events">
                                    <?php if (empty($logs)): ?>
                                        <span class="text-muted d-block text-center py-3" style="font-size: 0.65rem; opacity: 0.4;">فاقد داده</span>
                                    <?php else: foreach ($logs as $log): ?>
                                        <div class="day-event-badge bg-success bg-opacity-10 text-success border border-success-subtle mb-1">
                                            <div class="fw-bold text-truncate" style="max-width: 100px;"><?= htmlspecialchars($log['class_name']) ?></div>
                                            حضور: <span class="fw-bold"><?= $log['present_count'] ?></span> | غیاب: <span class="fw-bold text-danger"><?= $log['absent_count'] ?></span>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
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
