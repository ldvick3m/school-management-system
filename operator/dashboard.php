<?php
/**
 * داشبورد پنل اپراتور (مدیریت اجرایی)
 */

require_once '../includes/header.php';

// بررسی دسترسی اپراتور یا ادمین
check_auth(['operator', 'admin']);

// محاسبه آمار کلی برای کارت‌های بالای صفحه
try {
    // ۱. تعداد کل دانش‌آموزان
    $studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    // ۲. تعداد کل معلمان
    $teacherCount = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    // ۳. تعداد کل اولیا
    $parentCount = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
    // ۴. تیکت‌های در انتظار بررسی
    $pendingTickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status != 'closed'")->fetchColumn();

    // لیست کلاس‌های لایو فعال امروز
    $stmtLive = $pdo->prepare("SELECT lc.*, c.class_name, co.course_name, u.full_name as teacher_name 
        FROM live_classes lc
        JOIN classes c ON lc.class_id = c.id
        JOIN courses co ON lc.course_id = co.id
        JOIN teachers t ON lc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE lc.date = CURRENT_DATE()
        ORDER BY lc.start_time ASC");
    $stmtLive->execute();
    $liveClassesToday = $stmtLive->fetchAll();

    // لیست آخرین تیکت‌های باز پشتیبانی
    $stmtTickets = $pdo->prepare("SELECT t.*, u.full_name as creator_name, u.role as creator_role 
        FROM tickets t
        JOIN users u ON t.creator_id = u.id
        WHERE t.status != 'closed'
        ORDER BY t.created_at DESC LIMIT 5");
    $stmtTickets->execute();
    $recentTickets = $stmtTickets->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات داشبورد: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row g-4 mb-4">
        <!-- کارت آمار ۱: تعداد دانش‌آموزان -->
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل دانش‌آموزان</h6>
                    <h3 class="fw-bold mb-0"><?= $studentCount ?></h3>
                </div>
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                    <i class="bi bi-people-fill fs-3"></i>
                </div>
            </div>
        </div>
        
        <!-- کارت آمار ۲: تعداد معلمان -->
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل دبیران</h6>
                    <h3 class="fw-bold mb-0"><?= $teacherCount ?></h3>
                </div>
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-person-workspace fs-3"></i>
                </div>
            </div>
        </div>
        
        <!-- کارت آمار ۳: تعداد والدین -->
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل والدین ثبت‌شده</h6>
                    <h3 class="fw-bold mb-0"><?= $parentCount ?></h3>
                </div>
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                    <i class="bi bi-microsoft-teams fs-3"></i>
                </div>
            </div>
        </div>
        
        <!-- کارت آمار ۴: تیکت‌های فعال -->
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">تیکت‌های در جریان</h6>
                    <h3 class="fw-bold mb-0"><?= $pendingTickets ?></h3>
                </div>
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                    <i class="bi bi-envelope-exclamation-fill fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- کلاس‌های لایو امروز -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-camera-video-fill text-danger me-2"></i>کلاس‌های آنلاین امروز</h5>
                    <a href="live_scheduler.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>تعریف کلاس جدید</a>
                </div>
                <div class="card-body">
                    <?php if (empty($liveClassesToday)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">کلاس آنلاینی برای امروز برنامه‌ریزی نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>عنوان کلاس</th>
                                        <th>درس و کلاس</th>
                                        <th>دبیر</th>
                                        <th>ساعت برگزاری</th>
                                        <th>لینک کلاس</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($liveClassesToday as $lc): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($lc['title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($lc['course_name']) ?></span><br>
                                                <small class="text-secondary"><?= htmlspecialchars($lc['class_name']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($lc['teacher_name']) ?></td>
                                            <td>
                                                <i class="bi bi-clock me-1 text-primary"></i>
                                                <?= to_time($lc['start_time']) ?> الی <?= to_time($lc['end_time']) ?>
                                            </td>
                                            <td>
                                                <a href="<?= htmlspecialchars($lc['join_link']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> پیوستن
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

        <!-- تیکت‌های پشتیبانی در جریان -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-ticket-perforated-fill text-warning me-2"></i>تیکت‌های باز اخیر</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentTickets)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle-fill fs-1 text-success mb-2"></i>
                            <p class="text-secondary mb-0">تمامی تیکت‌ها پاسخ داده شده و بسته هستند.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentTickets as $ticket): ?>
                                <a href="tickets.php?id=<?= $ticket['id'] ?>" class="list-group-item list-group-item-action px-0 py-3">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($ticket['title']) ?></h6>
                                        <span class="badge bg-<?= get_status_class($ticket['status']) ?>-subtle text-<?= get_status_class($ticket['status']) ?> border border-<?= get_status_class($ticket['status']) ?>-subtle">
                                            <?= $ticket['status'] === 'open' ? 'باز' : 'در حال بررسی' ?>
                                        </span>
                                    </div>
                                    <p class="mb-1 text-secondary small">
                                        ارسال‌کننده: <?= htmlspecialchars($ticket['creator_name']) ?> (<?= get_role_fa($ticket['creator_role']) ?>)
                                    </p>
                                    <small class="text-black-50">
                                        <i class="bi bi-calendar-event me-1"></i><?= to_shamsi($ticket['created_at']) ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
