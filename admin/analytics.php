<?php
/**
 * گزارش‌ها و آمارهای مدیریتی کلان (Super Admin Analytics)
 */

require_once '../includes/header.php';
check_auth('admin');

try {
    // ۱. گزارش ارزیابی معلمان (تعداد کلاس لایو، فایل‌های آپلودی، سرعت تصحیح تکالیف به ساعت)
    $stmtTeacherEval = $pdo->query("SELECT u.full_name as teacher_name, 
        COUNT(DISTINCT lc.id) as classes_held, 
        COUNT(DISTINCT r.id) as files_uploaded, 
        ROUND(AVG(TIMESTAMPDIFF(HOUR, hs.submitted_at, hs.graded_at)), 1) as avg_grading_hours
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN live_classes lc ON t.id = lc.teacher_id 
        LEFT JOIN class_teacher_course ctc ON t.id = ctc.teacher_id 
        LEFT JOIN topics tp ON ctc.id = tp.class_teacher_course_id 
        LEFT JOIN resources r ON tp.id = r.topic_id 
        LEFT JOIN homeworks hw ON tp.id = hw.topic_id 
        LEFT JOIN homework_submissions hs ON hw.id = hs.homework_id AND hs.status = 'graded'
        GROUP BY t.id");
    $teacherEvaluations = $stmtTeacherEval->fetchAll();

    // ۲. گزارش عملکرد اپراتورها (تعداد تیکت‌های حل شده و میانگین سرعت پاسخ به ساعت)
    $stmtOpEval = $pdo->query("SELECT u.full_name as operator_name, 
        COUNT(DISTINCT tm.ticket_id) as tickets_handled, 
        ROUND(AVG(TIMESTAMPDIFF(HOUR, t.created_at, tm.created_at)), 1) as avg_reply_hours
        FROM users u 
        LEFT JOIN ticket_messages tm ON u.id = tm.sender_id 
        LEFT JOIN tickets t ON tm.ticket_id = t.id 
        WHERE u.role = 'operator' 
        GROUP BY u.id");
    $operatorEvaluations = $stmtOpEval->fetchAll();

    // ۳. گزارش تحصیلی دانش‌آموزان (میانگین نمرات تکالیف و امتحانات به تفکیک پایه)
    $stmtGradeEval = $pdo->query("SELECT s.grade_level, 
        ROUND(AVG(hs.grade), 2) as avg_hw, 
        ROUND(AVG(sq.score), 2) as avg_qz,
        COUNT(DISTINCT s.id) as students_count
        FROM students s 
        LEFT JOIN homework_submissions hs ON s.id = hs.student_id AND hs.status = 'graded'
        LEFT JOIN student_quizzes sq ON s.id = sq.student_id AND sq.score IS NOT NULL
        GROUP BY s.grade_level
        ORDER BY s.grade_level ASC");
    $gradeEvaluations = $stmtGradeEval->fetchAll();

    // ۴. گزارش حضور و غیاب کلان (نرخ غیبت کل مدرسه به تفکیک پایه و کلاس)
    $stmtAttendEval = $pdo->query("SELECT c.class_name, c.grade_level, 
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as abs_count, 
        COUNT(a.id) as total_count
        FROM classes c 
        LEFT JOIN attendance a ON c.id = a.class_id 
        GROUP BY c.id
        ORDER BY c.grade_level ASC, c.class_name ASC");
    $attendanceEvaluations = $stmtAttendEval->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری گزارش‌های تحلیلی کلان: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row g-4">
        <!-- ۱. گزارش ارزیابی معلمان -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-person-badge-fill me-2"></i>گزارش ارزیابی عملکرد معلمان</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>نام دبیر</th>
                                    <th>کلاس آنلاین برگزار شده</th>
                                    <th>منابع آپلود شده</th>
                                    <th>سرعت تصحیح تکلیف</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teacherEvaluations as $te): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($te['teacher_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $te['classes_held'] ?> کلاس</span></td>
                                        <td><span class="badge bg-light text-dark border"><?= $te['files_uploaded'] ?> فایل</span></td>
                                        <td>
                                            <span class="badge bg-<?= ($te['avg_grading_hours'] === null || $te['avg_grading_hours'] > 24) ? 'warning' : 'success' ?>-subtle text-<?= ($te['avg_grading_hours'] === null || $te['avg_grading_hours'] > 24) ? 'warning' : 'success' ?> border">
                                                <?= $te['avg_grading_hours'] !== null ? $te['avg_grading_hours'] . ' ساعت' : 'ارزیابی‌نشده' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ۲. گزارش عملکرد اپراتورها -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-success"><i class="bi bi-headset me-2"></i>گزارش عملکرد تیکتینگ اپراتورها</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>نام اپراتور</th>
                                    <th>تیکت‌های حل شده</th>
                                    <th>سرعت پاسخ به تیکت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($operatorEvaluations as $oe): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($oe['operator_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $oe['tickets_handled'] ?> تیکت</span></td>
                                        <td>
                                            <span class="badge bg-<?= ($oe['avg_reply_hours'] === null || $oe['avg_reply_hours'] > 12) ? 'warning' : 'success' ?>-subtle text-<?= ($oe['avg_reply_hours'] === null || $oe['avg_reply_hours'] > 12) ? 'warning' : 'success' ?> border">
                                                <?= $oe['avg_reply_hours'] !== null ? $oe['avg_reply_hours'] . ' ساعت' : 'پاسخ‌نداده' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ۳. گزارش تحصیلی دانش‌آموزان به تفکیک پایه -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-warning"><i class="bi bi-mortarboard-fill me-2"></i>گزارش تحصیلی دانش‌آموزان به تفکیک پایه</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>پایه تحصیلی</th>
                                    <th>تعداد دانش‌آموزان</th>
                                    <th>میانگین نمرات تکالیف</th>
                                    <th>میانگین نمرات آزمون‌ها</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gradeEvaluations as $ge): ?>
                                    <tr>
                                        <td class="fw-bold">پایه <?= $ge['grade_level'] ?></td>
                                        <td><?= $ge['students_count'] ?> نفر</td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border"><?= $ge['avg_hw'] !== null ? $ge['avg_hw'] : 'ثبت‌نشده' ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border"><?= $ge['avg_qz'] !== null ? $ge['avg_qz'] : 'ثبت‌نشده' ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ۴. گزارش حضور و غیاب کلان (نرخ غیبت کل مدرسه) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-calendar-x-fill me-2"></i>گزارش حضور و غیاب و نرخ غیبت کلاس‌ها</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>نام کلاس</th>
                                    <th>پایه تحصیلی</th>
                                    <th>نرخ غیبت</th>
                                    <th>کل روزهای ثبت‌شده</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceEvaluations as $ae): 
                                    $absRate = 0;
                                    if ($ae['total_count'] > 0) {
                                        $absRate = ($ae['abs_count'] / $ae['total_count']) * 100;
                                    }
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($ae['class_name']) ?></td>
                                        <td>پایه <?= $ae['grade_level'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $absRate > 15 ? 'danger' : ($absRate > 5 ? 'warning' : 'success') ?>-subtle text-<?= $absRate > 15 ? 'danger' : ($absRate > 5 ? 'warning' : 'success') ?> border">
                                                <?= round($absRate, 1) ?>% غیبت
                                            </span>
                                        </td>
                                        <td><?= $ae['total_count'] ?> رکورد</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
