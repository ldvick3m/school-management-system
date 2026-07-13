<?php
/**
 * دشبورد پنل والدین (سوییچر، وضعیت زنده حضور، اعلانات و کلاس آنلاین فعال فرزند)
 */

require_once '../includes/header.php';
check_auth(['parent', 'admin']);

$activeChildId = $_SESSION['active_child_id'] ?? 0;
$activeChildName = $_SESSION['active_child_name'] ?? '';

$attendanceToday = null;
$liveClassNow = null;
$nextClass = null;
$announcements = [];

if ($activeChildId > 0) {
    try {
        // ۱. رادار وضعیت زنده فرزند: حضور و غیاب امروز زنگ جاری
        $stmtAttend = $pdo->prepare("SELECT status FROM attendance WHERE student_id = ? AND date = CURRENT_DATE() LIMIT 1");
        $stmtAttend->execute([$activeChildId]);
        $attendanceToday = $stmtAttend->fetchColumn();

        // ۲. رادار وضعیت زنده فرزند: کلاس آنلاین در حال برگزاری
        $stmtLive = $pdo->prepare("SELECT lc.*, co.course_name, u.full_name as teacher_name 
            FROM live_classes lc
            JOIN class_student cs ON lc.class_id = cs.class_id
            JOIN courses co ON lc.course_id = co.id
            JOIN teachers t ON lc.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE cs.student_id = ? 
              AND lc.date = CURRENT_DATE() 
              AND CURRENT_TIME() BETWEEN lc.start_time AND lc.end_time
            LIMIT 1");
        $stmtLive->execute([$activeChildId]);
        $liveClassNow = $stmtLive->fetch();

        // ۳. کلاس بعدی فرزند امروز
        if (!$liveClassNow) {
            $stmtNext = $pdo->prepare("SELECT lc.*, co.course_name, u.full_name as teacher_name 
                FROM live_classes lc
                JOIN class_student cs ON lc.class_id = cs.class_id
                JOIN courses co ON lc.course_id = co.id
                JOIN teachers t ON lc.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                WHERE cs.student_id = ? 
                  AND lc.date = CURRENT_DATE() 
                  AND lc.start_time > CURRENT_TIME()
                ORDER BY lc.start_time ASC LIMIT 1");
            $stmtNext->execute([$activeChildId]);
            $nextClass = $stmtNext->fetch();
        }

        // ۴. واکشی پایه تحصیلی فرزند
        $gradeLevel = $pdo->query("SELECT grade_level FROM students WHERE id = $activeChildId")->fetchColumn();

        // ۵. بررسی اینکه آیا فرزند تکلیف معوقه دارد
        $stmtOverdue = $pdo->prepare("SELECT COUNT(*) 
            FROM homeworks hw 
            JOIN topics t ON hw.topic_id = t.id
            JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
            JOIN class_student cs ON ctc.class_id = cs.class_id
            LEFT JOIN homework_submissions hs ON hw.id = hs.homework_id AND hs.student_id = ?
            WHERE cs.student_id = ? AND hs.id IS NULL AND hw.deadline < NOW()");
        $stmtOverdue->execute([$activeChildId, $activeChildId]);
        $overdueCount = $stmtOverdue->fetchColumn();

        // ۶. واکشی اعلانات مجاز (شامل اعلانات عمومی، ویژه اولیا، و اعلانات با فیلتر تکالیف معوقه در صورت تطبیق)
        $sqlAnn = "SELECT * FROM announcements 
            WHERE target_role = 'all' OR target_role = 'parent' OR target_role = :grade";
        
        if ($overdueCount > 0) {
            $sqlAnn .= " OR target_role = 'parents_overdue_homework'";
        }
        
        $sqlAnn .= " ORDER BY created_at DESC LIMIT 5";
        
        $stmtAnn = $pdo->prepare($sqlAnn);
        $stmtAnn->execute([':grade' => 'grade_' . $gradeLevel]);
        $announcements = $stmtAnn->fetchAll();

    } catch (PDOException $e) {
        die("خطا در بارگذاری اطلاعات دشبورد والدین: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">
    <?php if ($activeChildId <= 0): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center">
            <div class="card-body">
                <i class="bi bi-people fs-1 text-secondary mb-3"></i>
                <h5 class="fw-bold">دانش‌آموزی متصل نشده است</h5>
                <p class="text-secondary small">هیچ فرزندی در سیستم به اکانت شما پیوند داده نشده است. لطفاً با اپراتور مدرسه هماهنگ نمایید.</p>
            </div>
        </div>
    <?php else: ?>
        
        <!-- بنر اعلان وضعیت فرزند فعال -->
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-primary text-white">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">رصد وضعیت فرزند: <?= htmlspecialchars($activeChildName) ?></h4>
                    <p class="mb-0 text-white-50">خلاصه وضعیت لایو و اعلانات جاری فرزند فعال انتخاب شده شما در سیستم.</p>
                </div>
                <i class="bi bi-shield-fill-check fs-1 opacity-20"></i>
            </div>
        </div>

        <!-- بنر اعلان بسیار مهم و چشم‌نواز در صورت برگزاری کلاس آنلاین فعال فرزند -->
        <?php if ($liveClassNow): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger shadow-sm p-4 rounded-3 mb-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="spinner-grow text-danger mt-1" role="status" style="width: 1.5rem; height: 1.5rem; flex-shrink: 0;"></div>
                    <div>
                        <h5 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i>اطلاعیه حضور در کلاس آنلاین فرزند</h5>
                        <p class="mb-0 text-dark small" style="line-height: 1.7;">
                            کلاس آنلاین درس **«<?= htmlspecialchars($liveClassNow['course_name']) ?>»** (عنوان مبحث: **<?= htmlspecialchars($liveClassNow['title']) ?>**) با تدریس **<?= htmlspecialchars($liveClassNow['teacher_name']) ?>** هم‌اکنون برای فرزند شما **<?= htmlspecialchars($activeChildName) ?>** در حال برگزاری است. 
                            <br>
                            <span class="text-danger-emphasis fw-bold"><i class="bi bi-info-circle-fill me-1"></i>توضیح امنیتی: امکان ورود به محیط کلاس درس تنها برای اکانت دانش‌آموزی مجاز بوده و دسترسی ورود مستقیم برای حساب کاربری اولیا غیرفعال است.</span>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- رادار وضعیت زنده فرزند (ستون راست) -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-activity text-primary me-2"></i>رادار وضعیت زنده فرزند</h5>
                    </div>
                    <div class="card-body">
                        <!-- ۱. حضور و غیاب امروز -->
                        <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded bg-light">
                            <div>
                                <h6 class="fw-bold mb-1">وضعیت حضور در مدرسه (امروز)</h6>
                                <small class="text-secondary">ثبت شده توسط دبیر زنگ جاری</small>
                            </div>
                            <span class="badge bg-<?= $attendanceToday ? get_status_class($attendanceToday) : 'secondary' ?> fs-6 p-2 px-3">
                                <?php 
                                    if ($attendanceToday === 'present') echo 'حاضر';
                                    elseif ($attendanceToday === 'absent') echo 'غایب';
                                    else echo 'ثبت‌نشده';
                                ?>
                            </span>
                        </div>

                        <!-- ۲. وضعیت کلاس آنلاین جاری -->
                        <div class="p-3 rounded border">
                            <h6 class="fw-bold mb-3"><i class="bi bi-camera-video me-1"></i> کلاس آنلاین فعال فرزند</h6>
                            
                            <?php if ($liveClassNow): ?>
                                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-dark mb-0 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="spinner-grow text-danger" role="status" style="width: 1rem; height: 1rem;"></div>
                                        <div>
                                            <strong>کلاس آنلاین در حال برگزاری است!</strong>
                                            <p class="mb-0 small text-secondary mt-1">
                                                درس: <?= htmlspecialchars($liveClassNow['course_name']) ?> | عنوان: <?= htmlspecialchars($liveClassNow['title']) ?>
                                            </p>
                                            <span class="badge bg-secondary-subtle text-secondary border mt-2 small d-inline-block p-2"><i class="bi bi-lock-fill"></i> لینک ورود مخصوص دانش‌آموز است</span>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($nextClass): ?>
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 text-dark mb-0 py-3">
                                    <strong>کلاس آنلاین بعدی امروز:</strong>
                                    <p class="mb-0 small text-secondary mt-1">
                                        درس: <?= htmlspecialchars($nextClass['course_name']) ?> | شروع ساعت: <?= to_time($nextClass['start_time']) ?>
                                    </p>
                                    <span class="badge bg-secondary-subtle text-secondary border mt-2 small d-inline-block p-2"><i class="bi bi-lock-fill"></i> لینک ورود مخصوص دانش‌آموز است</span>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 text-secondary small">
                                    کلاس آنلاینی برای امروز برنامه‌ریزی نشده یا به اتمام رسیده است.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- هشدار کارهای معوقه -->
                <?php if ($overdueCount > 0): ?>
                    <div class="card border-0 bg-danger bg-opacity-10 border border-danger shadow-sm rounded-3">
                        <div class="card-body py-3 d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-octagon-fill text-danger fs-3"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-danger">هشدار تکالیف معوقه فرزند!</h6>
                                <p class="mb-0 small text-secondary">فرزند شما تعداد <strong><?= $overdueCount ?></strong> تکلیف ارسال‌نشده با زمان سپری‌شده دارد.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- اعلانات و پیام‌های مدرسه (ستون چپ) -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill text-warning me-2"></i>تابلو اعلانات مدرسه (ویژه اولیا)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-5 text-secondary small">اطلاعیه جدیدی یافت نشد.</div>
                        <?php else: foreach ($announcements as $ann): ?>
                            <div class="border-bottom py-3 <?= $ann['target_role'] === 'parents_overdue_homework' ? 'bg-danger bg-opacity-5 p-3 rounded border border-danger border-opacity-10 mb-3' : '' ?>">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <?= htmlspecialchars($ann['title']) ?>
                                        <?php if ($ann['target_role'] === 'parents_overdue_homework'): ?>
                                            <span class="badge bg-danger ms-2">هشدار تکلیف معوقه</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-secondary"><?= to_shamsi($ann['created_at']) ?></small>
                                </div>
                                <p class="mb-0 text-secondary small mt-2" style="line-height: 1.6;">
                                    <?= htmlspecialchars($ann['content']) ?>
                                </p>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
