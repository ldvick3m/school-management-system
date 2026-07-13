<?php
/**
 * مدیریت وضعیت کلاس‌ها در پنل مدیر کل
 */

require_once '../includes/header.php';
check_auth(['admin']);

try {
    // دریافت اطلاعات کلاس‌ها به همراه تعداد دانش‌آموزان و تخصیص‌ها
    $classes = $pdo->query("SELECT c.id, c.class_name, c.grade_level,
        (SELECT COUNT(*) FROM class_student cs WHERE cs.class_id = c.id) as students_count,
        (SELECT COUNT(*) FROM class_teacher_course ctc WHERE ctc.class_id = c.id) as allocations_count
        FROM classes c
        ORDER BY c.grade_level ASC, c.class_name ASC")->fetchAll();

} catch (PDOException $e) {
    die("خطا در لود اطلاعات کلاس‌ها: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-house-gear-fill text-primary me-1"></i> وضعیت کلاس‌های مدرسه</h4>
            <p class="text-secondary small mb-0">جهت مشاهده دبیران، برندگان مدال‌ها و تحلیل جزئیات هر کلاس روی نام کلاس کلیک کنید.</p>
        </div>
    </div>

    <!-- جدول لیست کلاس‌های تعریف‌شده با عرض کامل ۱۰۰٪ -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">لیست کلاس‌های مدرسه</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($classes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-house-gear fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">کلاسی در سامانه ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>نام کلاس</th>
                                        <th>پایه تحصیلی</th>
                                        <th>دانش‌آموزان ثبت‌نامی</th>
                                        <th>دروس فعال</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $c): ?>
                                        <tr>
                                            <td>
                                                <a href="../operator/class_details.php?class_id=<?= $c['id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                    <?= htmlspecialchars($c['class_name']) ?>
                                                </a>
                                            </td>
                                            <td>پایه <?= $c['grade_level'] ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $c['students_count'] ?> دانش‌آموز</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $c['allocations_count'] ?> درس/معلم</span>
                                            </td>
                                            <td>
                                                <a href="../operator/class_details.php?class_id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm fw-bold">
                                                    <i class="bi bi-eye-fill"></i> مشاهده وضعیت کلاس
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
</div>

<?php require_once '../includes/footer.php'; ?>
