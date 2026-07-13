<?php
/**
 * برنامه‌ریزی کلاس‌های لایو آنلاین و شبیه‌ساز پیامک به والدین
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$error = '';
$success = '';
$sms_log = [];

// هندل کردن ثبت کلاس لایو جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $date = parse_shamsi_to_gregorian($_POST['date'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $join_link = trim($_POST['join_link'] ?? '');

    if ($class_id <= 0 || $course_id <= 0 || $teacher_id <= 0 || empty($title) || empty($date) || empty($start_time) || empty($end_time) || empty($join_link)) {
        $error = 'لطفاً تمامی فیلدها را با دقت پر کنید.';
    } else {
        try {
            $pdo->beginTransaction();

            // ۱. ثبت کلاس آنلاین در دیتابیس
            $stmt = $pdo->prepare("INSERT INTO `live_classes` (`class_id`, `teacher_id`, `course_id`, `title`, `date`, `start_time`, `end_time`, `join_link`, `sms_sent`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$class_id, $teacher_id, $course_id, $title, $date, $start_time, $end_time, $join_link]);
            
            // ۲. پیدا کردن والدین دانش‌آموزان این کلاس جهت شبیه‌سازی ارسال پیامک
            $stmtParents = $pdo->prepare("SELECT DISTINCT pu.full_name as parent_name, pu.phone as parent_phone, su.full_name as student_name, co.course_name 
                FROM class_student cs
                JOIN students s ON cs.student_id = s.id
                JOIN users su ON s.user_id = su.id
                JOIN parent_student ps ON s.id = ps.student_id
                JOIN parents p ON ps.parent_id = p.id
                JOIN users pu ON p.user_id = pu.id
                JOIN courses co ON co.id = ?
                WHERE cs.class_id = ? AND pu.phone IS NOT NULL AND pu.phone != ''");
            $stmtParents->execute([$course_id, $class_id]);
            $parentsToNotify = $stmtParents->fetchAll();

            // شبیه‌ساز ارسال پیامک (SMS Web Service Simulation)
            foreach ($parentsToNotify as $pn) {
                $smsText = "ولی محترم جناب {$pn['parent_name']}، فرزند شما {$pn['student_name']} کلاس آنلاین جدید در درس {$pn['course_name']} با عنوان '{$title}' در تاریخ {$date} ساعت {$start_time} دارد. لینک ورود: {$join_link}";
                
                // در پروژه‌های واقعی در این قسمت کد وب‌سرویس پیامکی قرار می‌گیرد:
                // sms_send_api($pn['parent_phone'], $smsText);
                
                // ذخیره گزارش پیامک‌ها در دیتابیس یا فایل لاگ سرور
                error_log("SMS Sent to {$pn['parent_phone']}: " . $smsText);
                $sms_log[] = [
                    'recipient' => $pn['parent_name'] . ' (' . $pn['parent_phone'] . ')',
                    'text' => $smsText
                ];
            }

            $pdo->commit();
            
            $success = "کلاس آنلاین با موفقیت برنامه‌ریزی شد. سیستم ارسال پیامک برای والدین شبیه‌سازی گردید.";
            if (!empty($sms_log)) {
                $_SESSION['sms_log'] = $sms_log; // ذخیره پیامک‌ها در سشن جهت نمایش در صفحه بعد از لود
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "خطا در ثبت کلاس آنلاین: " . $e->getMessage();
        }
    }
}

// واکشی پیامک‌های ارسال شده از سشن
if (isset($_SESSION['sms_log'])) {
    $sms_log = $_SESSION['sms_log'];
    unset($_SESSION['sms_log']);
}

// واکشی داده‌های جدول کلاس‌ها برای فرم زمان‌بند
try {
    // کلاس‌های تعریف شده
    $classes = $pdo->query("SELECT * FROM classes ORDER BY grade_level ASC")->fetchAll();
    // دبیران فعال
    $teachers = $pdo->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 1")->fetchAll();
    // دروس
    $courses = $pdo->query("SELECT * FROM courses ORDER BY grade_level ASC")->fetchAll();

    // لیست کلاس‌های آنلاین زمان‌بندی شده با فیلترها
    $search = trim($_GET['search'] ?? '');
    $classFilter = (int)($_GET['class_id'] ?? 0);
    $courseFilter = (int)($_GET['course_id'] ?? 0);
    $hideExpired = isset($_GET['hide_expired']) ? (int)$_GET['hide_expired'] : 1; 

    $sql = "SELECT lc.*, c.class_name, co.course_name, u.full_name as teacher_name 
        FROM live_classes lc
        JOIN classes c ON lc.class_id = c.id
        JOIN courses co ON lc.course_id = co.id
        JOIN teachers t ON lc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND lc.title LIKE ?";
        $params[] = "%$search%";
    }
    if ($classFilter > 0) {
        $sql .= " AND lc.class_id = ?";
        $params[] = $classFilter;
    }
    if ($courseFilter > 0) {
        $sql .= " AND lc.course_id = ?";
        $params[] = $courseFilter;
    }
    if ($hideExpired === 1) {
        // مخفی کردن کلاس‌هایی که ۲۴ ساعت از زمان شروع آن‌ها گذشته است
        $sql .= " AND DATE_ADD(CONCAT(lc.date, ' ', lc.start_time), INTERVAL 24 HOUR) >= NOW()";
    }

    $sql .= " ORDER BY lc.date DESC, lc.start_time DESC";
    $stmtLiveClasses = $pdo->prepare($sql);
    $stmtLiveClasses->execute($params);
    $allScheduledClasses = $stmtLiveClasses->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات زمان‌بند کلاس لایو: " . $e->getMessage());
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

    <!-- نمایش لاگ شبیه‌ساز پیامک -->
    <?php if (!empty($sms_log)): ?>
        <div class="card border-0 bg-info bg-opacity-10 border-info border shadow-sm rounded-3 mb-4">
            <div class="card-header bg-transparent border-0 py-3">
                <h6 class="fw-bold mb-0 text-info"><i class="bi bi-chat-left-text-fill me-1"></i> لاگ شبیه‌ساز وب‌سرویس پیامکی (ارسال شده به والدین)</h6>
            </div>
            <div class="card-body pt-0">
                <div class="small">
                    <?php foreach ($sms_log as $log): ?>
                        <div class="mb-2 p-2 rounded bg-white border border-info border-opacity-25">
                            <strong>گیرنده: </strong> <span class="text-secondary"><?= htmlspecialchars($log['recipient']) ?></span><br>
                            <strong>متن پیامک ارسالی: </strong> <span class="text-dark"><?= htmlspecialchars($log['text']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

<style>
/* استایل دکمه سوئیچ طرح اپل */
.apple-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    margin-bottom: 0;
    vertical-align: middle;
}
.apple-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.apple-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #e2e8f0;
    transition: .3s;
    border-radius: 24px;
}
.apple-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
input:checked + .apple-slider {
    background-color: #34c759;
}
input:checked + .apple-slider:before {
    transform: translateX(20px);
}
</style>

    <!-- فرم برنامه‌ریزی لایو جدید به صورت افقی (۲ سطر) در بالای صفحه -->
    <div class="card border-0 shadow-sm rounded-3 mb-4" style="overflow: visible !important;">
        <div class="card-header bg-white py-3 border-bottom-0">
            <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-clock-history me-1"></i> برنامه‌ریزی کلاس آنلاین جدید</h5>
        </div>
        <div class="card-body pt-0" style="overflow: visible !important;">
            <form method="POST" action="">
                <!-- سطر اول فرم -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">عنوان جلسه آنلاین</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: رفع اشکال مبحث اتحادها" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">انتخاب کلاس هدف</label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">انتخاب درس</label>
                        <select name="course_id" class="form-select" required>
                            <?php foreach ($courses as $co): ?>
                                <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['course_name']) ?> (پایه <?= $co['grade_level'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">انتخاب دبیر برگزارکننده</label>
                        <select name="teacher_id" class="form-select" required>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- سطر دوم فرم -->
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">تاریخ برگزاری</label>
                        <input type="text" data-jdp name="date" class="form-control" placeholder="۱۴۰۵/۰۴/۱۰" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">ساعت شروع</label>
                        <input type="text" name="start_time" class="form-control custom-time-picker" value="12:00" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">ساعت پایان</label>
                        <input type="text" name="end_time" class="form-control custom-time-picker" value="13:30" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">لینک پیوستن (Skyroom, Adobe Connect,...)</label>
                        <input type="url" name="join_link" class="form-control" placeholder="https://skyroom.online/ch/..." required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-danger w-100 fw-bold" style="height: 38px;"><i class="bi bi-megaphone me-1"></i>ثبت و اطلاع‌رسانی</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ردیف پایینی: لیست کلاس‌های ثبت شده با عرض کامل و فیلترهای هوشمند -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0">کلاس‌های آنلاین ثبت شده در سیستم</h5>
                </div>
                <!-- بخش فیلترهای هوشمند و سرچ بالای لیست -->
                <div class="card-body bg-light border-bottom border-top py-3">
                    <form method="GET" action="" id="liveClassesFilterForm" class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="جستجوی عنوان کلاس..." value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="class_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">همه کلاس‌های هدف</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">همه دروس</option>
                                <?php foreach ($courses as $co): ?>
                                    <option value="<?= $co['id'] ?>" <?= $courseFilter == $co['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($co['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- سوئیچ استایل اپل برای فیلتر کلاس‌های منقضی شده -->
                        <div class="col-md-3 d-flex align-items-center justify-content-md-end gap-2">
                            <span class="small fw-bold text-secondary">پنهان کردن کلاس‌های منقضی شده</span>
                            <label class="apple-switch" title="کلاس‌هایی که بیش از ۲۴ ساعت از زمان شروع آن‌ها گذشته است را نشان نمی‌دهد">
                                <input type="checkbox" name="hide_expired" value="1" <?= $hideExpired === 1 ? 'checked' : '' ?> onchange="submitFilterForm(this)">
                                <span class="apple-slider"></span>
                            </label>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($allScheduledClasses)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-camera-video-off fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">کلاس آنلاینی با مشخصات فوق در سیستم یافت نشد.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>عنوان کلاس</th>
                                        <th>درس / کلاس</th>
                                        <th>دبیر</th>
                                        <th>تاریخ برگزاری</th>
                                        <th>ساعت</th>
                                        <th>وضعیت پیامک</th>
                                        <th>لینک کلاس</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allScheduledClasses as $lc): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($lc['title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($lc['course_name']) ?></span><br>
                                                <small class="text-secondary"><?= htmlspecialchars($lc['class_name']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($lc['teacher_name']) ?></td>
                                            <td><i class="bi bi-calendar3 me-1 text-secondary"></i> <?= to_shamsi($lc['date']) ?></td>
                                            <td><?= to_time($lc['start_time']) ?> الی <?= to_time($lc['end_time']) ?></td>
                                            <td>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-check-all"></i> ارسال شد
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= htmlspecialchars($lc['join_link']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> لینک ورود
                                                </a>
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
    </div>

<script>
    function submitFilterForm(checkbox) {
        const form = document.getElementById('liveClassesFilterForm');
        
        // پاک کردن فیلد مخفی قبلی در صورت وجود
        const oldHidden = document.getElementById('hide_expired_hidden');
        if (oldHidden) oldHidden.remove();
        
        if (!checkbox.checked) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'hide_expired';
            hiddenInput.value = '0';
            hiddenInput.id = 'hide_expired_hidden';
            form.appendChild(hiddenInput);
            checkbox.disabled = true; // غیرفعال کردن موقت تا مقدار صفر ارسال شود
        }
        form.submit();
    }
</script>
</div>

<?php require_once '../includes/footer.php'; ?>
