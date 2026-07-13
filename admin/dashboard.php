<?php
/**
 * داشبورد نظارتی کلان مدیر کل (Super Admin)
 */

require_once '../includes/header.php';
check_auth('admin');

try {
    // ۱. آمار کلی سیستم
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $totalParents = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
    $totalOperators = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'operator'")->fetchColumn();

    // ۲. بررسی تداخل‌های زمانی هفته جاری (تعداد تداخل‌ها)
    // برای سادگی در نمایش، تداخل‌ها را در صفحه calendar.php به تفصیل نشان می‌دهیم
    // در اینجا فقط تعداد کل لایوها را نشان می‌دهیم
    $totalLiveClasses = $pdo->query("SELECT COUNT(*) FROM live_classes")->fetchColumn();

    // ۳. مانیتورینگ عملکرد اپراتورها در پاسخگویی به تیکت‌ها
    $stmtOps = $pdo->prepare("SELECT u.id, u.full_name, u.username,
        (SELECT COUNT(*) FROM ticket_messages WHERE sender_id = u.id) as replies_count,
        (SELECT COUNT(DISTINCT ticket_id) FROM ticket_messages WHERE sender_id = u.id) as handled_tickets
        FROM users u 
        WHERE u.role = 'operator' AND u.status = 1");
    $stmtOps->execute();
    $operatorsPerformance = $stmtOps->fetchAll();

    // ۴. کلاس‌های بدون لینک (کلاس‌هایی که ساعتشان رسیده اما اپراتور لینکی برایشان ثبت نکرده یا لینک نامعتبر است)
    // در سیستم ما لینک پیش‌فرض در صورت خالی بودن بررسی می‌شود (مثلا فاقد https)
    $stmtLinkless = $pdo->prepare("SELECT lc.*, c.class_name, co.course_name, u.full_name as teacher_name 
        FROM live_classes lc
        JOIN classes c ON lc.class_id = c.id
        JOIN courses co ON lc.course_id = co.id
        JOIN teachers t ON lc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE lc.join_link IS NULL OR lc.join_link = '' OR lc.join_link NOT LIKE 'http%'");
    $stmtLinkless->execute();
    $linklessClasses = $stmtLinkless->fetchAll();

} catch (PDOException $e) {
    die("خطا در لود اطلاعات ادمین: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- بخش کارت‌های آمار کلان سیستم -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل دانش‌آموزان</h6>
                    <h3 class="fw-bold mb-0"><?= $totalStudents ?> نفر</h3>
                </div>
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                    <i class="bi bi-people-fill fs-3"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل دبیران</h6>
                    <h3 class="fw-bold mb-0"><?= $totalTeachers ?> نفر</h3>
                </div>
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-person-badge-fill fs-3"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل اولیای فعال</h6>
                    <h3 class="fw-bold mb-0"><?= $totalParents ?> نفر</h3>
                </div>
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                    <i class="bi bi-microsoft-teams fs-3"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">اپراتورهای اجرایی</h6>
                    <h3 class="fw-bold mb-0"><?= $totalOperators ?> نفر</h3>
                </div>
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                    <i class="bi bi-headset fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- جدول مانیتورینگ عملکرد اپراتورها -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-check-fill text-primary me-2"></i>کارنامه عملکرد و پاسخگویی اپراتورها</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($operatorsPerformance)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-person-exclamation fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">اپراتوری در سیستم ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>نام اپراتور</th>
                                        <th>نام کاربری</th>
                                        <th>کل پاسخ‌های ثبت‌شده</th>
                                        <th>تیکت‌های سازمان‌دهی‌شده</th>
                                        <th>وضعیت دسترسی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($operatorsPerformance as $op): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($op['full_name']) ?></td>
                                            <td><code><?= htmlspecialchars($op['username']) ?></code></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $op['replies_count'] ?> پیام</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><?= $op['handled_tickets'] ?> تیکت</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success p-2">مجاز</span>
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

        <!-- کلاس‌های فاقد لینک (کلاس‌های بدون ثبت آدرس لایو) -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-link-45deg text-danger me-2"></i>ردیابی کلاس‌های بدون لینک معتبر</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($linklessClasses)): ?>
                        <div class="text-center py-5 text-success">
                            <i class="bi bi-check-circle-fill fs-1 mb-2"></i>
                            <p class="mb-0 fw-bold">وضعیت عالی است!</p>
                            <small class="text-secondary">تمامی کلاس‌های تعریف شده دارای لینک ورود معتبر می‌باشند.</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark small py-2 mb-3">
                            <i class="bi bi-info-circle-fill"></i> کلاس‌های زیر زمانشان فرارسیده یا ثبت شده‌اند اما آدرس اینترنتی تشکیل جلسه آن‌ها ثبت نشده است.
                        </div>
                        <div class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
                            <?php foreach ($linklessClasses as $lc): ?>
                                <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold text-danger mb-1"><?= htmlspecialchars($lc['title']) ?></h6>
                                        <small class="text-secondary">
                                            کلاس: <?= htmlspecialchars($lc['class_name']) ?> | درس: <?= htmlspecialchars($lc['course_name']) ?> | دبیر: <?= htmlspecialchars($lc['teacher_name']) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-danger">فاقد لینک</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
