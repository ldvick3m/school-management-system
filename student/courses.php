<?php
/**
 * لیست دروس دانش‌آموز بر اساس پایه تحصیلی
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

try {
    // ۱. پیدا کردن اطلاعات تحصیلی دانش‌آموز
    $stmtS = $pdo->prepare("SELECT s.id, s.grade_level, cs.class_id, c.class_name 
        FROM students s 
        LEFT JOIN class_student cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $student = $stmtS->fetch();

    if (!$student || !$student['class_id']) {
        die("شما در حال حاضر در هیچ کلاسی ثبت‌نام نشده‌اید. لطفاً با مسئولین اجرایی مدرسه تماس بگیرید.");
    }

    $classId = $student['class_id'];
    $gradeLevel = $student['grade_level'];

    // ۲. واکشی تمام دروس فعال کلاس این دانش‌آموز به همراه نام معلم
    // از جدول class_teacher_course استفاده می‌کنیم تا معلم هر درس در کلاس دانش‌آموز را مشخص کنیم
    $stmtCourses = $pdo->prepare("SELECT ctc.id as allocation_id, co.id as course_id, co.course_name, u.full_name as teacher_name, u.email as teacher_email,
        (SELECT COUNT(*) FROM topics WHERE class_teacher_course_id = ctc.id) as topics_count
        FROM class_teacher_course ctc
        JOIN courses co ON ctc.course_id = co.id
        JOIN teachers t ON ctc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ctc.class_id = ?");
    $stmtCourses->execute([$classId]);
    $myCourses = $stmtCourses->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری دروس فعال: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background-color: #4C1D95;">
        <div class="card-body p-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">کلاس‌های من در نیم‌سال جاری</h4>
                <p class="mb-0 text-white">لیست دروس فعال مربوط به کلاس «<?= htmlspecialchars($student['class_name']) ?>» (پایه <?= $gradeLevel ?>)</p>
            </div>
            <i class="bi bi-journal-bookmark-fill fs-1 opacity-20"></i>
        </div>
    </div>

    <?php if (empty($myCourses)): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center">
            <div class="card-body">
                <i class="bi bi-journal-x fs-1 text-secondary mb-3"></i>
                <h5 class="fw-bold">درسی تعریف نشده است</h5>
                <p class="text-secondary small">در حال حاضر هیچ برنامه درسی یا دبیری برای کلاس شما زمان‌بندی نشده است.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($myCourses as $course): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="course-card d-flex flex-column h-100">
                        <div class="course-card-header" style="background-color: #4C1D95;">
                            <span class="badge mb-2 text-white" style="background-color: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.28);">فعال</span>
                            <h5 class="fw-bold mb-0 text-white"><?= htmlspecialchars($course['course_name']) ?></h5>
                        </div>
                        <div class="course-card-body d-flex flex-column flex-grow-1 justify-content-between">
                            <div class="mb-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-secondary" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                        د
                                    </div>
                                    <div>
                                        <small class="text-secondary d-block">دبیر مربوطه:</small>
                                        <strong class="small"><?= htmlspecialchars($course['teacher_name']) ?></strong>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between small text-secondary border-top pt-2">
                                    <span>تعداد مباحث تدریس:</span>
                                    <strong><?= $course['topics_count'] ?> مبحث</strong>
                                </div>
                            </div>
                            
                            <a href="course_details.php?course_id=<?= $course['course_id'] ?>&allocation_id=<?= $course['allocation_id'] ?>" class="btn btn-primary w-100 fw-bold" style="background-color: #4C1D95; border: none;">
                                <i class="bi bi-folder2-open me-1"></i> ورود به کلاس درس
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
